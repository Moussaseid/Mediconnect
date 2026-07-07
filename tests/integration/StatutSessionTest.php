<?php
namespace tests\integration;

use PHPUnit\Framework\TestCase;
use controllers\BaseController;

/**
 * Tests d'intégration — Re-vérification du statut en base (Issue #7)
 *
 * requireRole() interroge maintenant utilisateurs.statut à chaque requête
 * protégée. Si le compte est suspendu après connexion, la session doit être
 * détruite et l'accès refusé (HTTP 403 + redirect /connexion).
 *
 * Couvre :
 *  - requireRole() passe si statut = 'actif' en base
 *  - requireRole() redirige et détruit la session si statut = 'inactif'
 *  - requireRole() redirige et détruit la session si statut = 'suspendu' (post-connexion)
 *  - Après redirection : $_SESSION['user'] est absent (session détruite)
 */
class StatutSessionTest extends TestCase
{
    private \PDO $pdo;

    private function makeController(): object
    {
        return new class extends BaseController {
            public function exposeRequireRole(string ...$roles): void
            {
                $this->requireRole(...$roles);
            }

            protected function redirect(string $url): never
            {
                throw new \RuntimeException('REDIRECT:' . $url);
            }
        };
    }

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['pdo'];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Restaurer le statut des utilisateurs modifiés pendant les tests
        $this->pdo->exec("UPDATE utilisateurs SET statut = 'actif' WHERE email IN ('jean@test.fr', 'alice@test.fr')");
        $_SESSION = [];
    }

    // --------------------------------------------------------- statut actif → accès accordé

    public function testAccesAccordeSiStatutActif(): void
    {
        // jean@test.fr est actif dans le seed (id=2)
        $id = (int) $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'jean@test.fr'")->fetchColumn();
        $_SESSION['user'] = ['id' => $id, 'role' => 'patient', 'nom' => 'Jean', 'email' => 'jean@test.fr'];

        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('patient');

        $this->assertTrue(true, 'requireRole() ne doit pas lever d\'exception si statut = actif.');
    }

    // --------------------------------------------------------- statut inactif → accès refusé

    public function testAccesRefuseSiStatutInactif(): void
    {
        // bloq@test.fr est déjà inactif dans le seed (id=3)
        $id = (int) $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'bloq@test.fr'")->fetchColumn();
        $_SESSION['user'] = ['id' => $id, 'role' => 'patient', 'nom' => 'Bloq', 'email' => 'bloq@test.fr'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposeRequireRole('patient');
    }

    // --------------------------------------------------------- suspension post-connexion

    public function testCompteSuspenduApresConnexionEstDeconnecte(): void
    {
        // Simuler un compte actif au moment de la connexion
        $id = (int) $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'jean@test.fr'")->fetchColumn();
        $_SESSION['user'] = ['id' => $id, 'role' => 'patient', 'nom' => 'Jean', 'email' => 'jean@test.fr'];

        // Suspension du compte après connexion (opération admin)
        $this->pdo->exec("UPDATE utilisateurs SET statut = 'suspendu' WHERE email = 'jean@test.fr'");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposeRequireRole('patient');
    }

    public function testSessionDetruiteSiCompteSuspendu(): void
    {
        $id = (int) $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'jean@test.fr'")->fetchColumn();
        $_SESSION['user'] = ['id' => $id, 'role' => 'patient', 'nom' => 'Jean', 'email' => 'jean@test.fr'];

        $this->pdo->exec("UPDATE utilisateurs SET statut = 'suspendu' WHERE email = 'jean@test.fr'");

        try {
            $this->makeController()->exposeRequireRole('patient');
        } catch (\RuntimeException $e) {
            // Exception attendue — on vérifie l'état de la session
        }

        // Après suspension : session doit être détruite
        $this->assertArrayNotHasKey('user', $_SESSION, 'La session doit être détruite après détection d\'un compte suspendu.');
    }

    public function testMedecinSuspenduNePeutPlusAccederSonDashboard(): void
    {
        $id = (int) $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'alice@test.fr'")->fetchColumn();
        $_SESSION['user'] = ['id' => $id, 'role' => 'medecin', 'nom' => 'Alice', 'email' => 'alice@test.fr'];

        $this->pdo->exec("UPDATE utilisateurs SET statut = 'suspendu' WHERE email = 'alice@test.fr'");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $this->makeController()->exposeRequireRole('medecin');
    }
}
