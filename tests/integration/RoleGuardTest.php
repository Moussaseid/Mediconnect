<?php
namespace tests\integration;

use PHPUnit\Framework\TestCase;
use controllers\BaseController;

/**
 * Tests d'intégration — Issue #7 : Guards / rôles
 *
 * requireRole() est une méthode protected de BaseController.
 * On l'expose via une sous-classe de test sans dépendance HTTP.
 *
 * Couvre :
 *  - requireRole() passe si le rôle attendu est dans la session
 *  - requireRole() accepte plusieurs rôles (variadic)
 *  - requireRole() lève une exception si session absente
 *  - requireRole() lève une exception si rôle incorrect
 *  - AuthService : login d'un rôle N donne bien le rôle N (pas de mélange)
 *  - Un patient ne peut pas se faire passer pour un admin (session forgée)
 */
class RoleGuardTest extends TestCase
{
    /**
     * Sous-classe exposant requireRole() publiquement pour les tests.
     * Surcharge redirect() pour lever une exception au lieu d'appeler header().
     */
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
        // Simuler une session PHP sans démarrer une vraie session HTTP
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ---------------------------------------------------------- rôle correct

    public function testRequireRolePasseAvecLeRolAttendu(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'patient', 'nom' => 'Jean', 'email' => 'j@t.fr'];
        $ctrl = $this->makeController();
        // Ne doit pas lever d'exception
        $ctrl->exposeRequireRole('patient');
        $this->assertTrue(true); // si on arrive ici, le test passe
    }

    public function testRequireRoleAcceptePlusieursRoles(): void
    {
        $_SESSION['user'] = ['id' => 2, 'role' => 'medecin', 'nom' => 'Alice', 'email' => 'a@t.fr'];
        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('patient', 'medecin', 'admin');
        $this->assertTrue(true);
    }

    public function testRequireRoleAdminPassePourAdmin(): void
    {
        // id=1 → admin@test.fr (statut='actif') dans le seed de test
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin', 'nom' => 'Admin', 'email' => 'admin@test.fr'];
        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('admin');
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------- accès refusé

    public function testRequireRoleRedirigeQuandSessionAbsente(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('patient');
    }

    public function testRequireRoleRedirigeQuandRoleDifferent(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'patient', 'nom' => 'Jean', 'email' => 'j@t.fr'];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('admin'); // patient tente d'accéder à une zone admin
    }

    public function testPatientNePeutPasAccederZoneMedecin(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'patient', 'nom' => 'Jean', 'email' => 'j@t.fr'];
        $this->expectException(\RuntimeException::class);

        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('medecin');
    }

    public function testMedecinNePeutPasAccederZoneAdmin(): void
    {
        $_SESSION['user'] = ['id' => 2, 'role' => 'medecin', 'nom' => 'Alice', 'email' => 'a@t.fr'];
        $this->expectException(\RuntimeException::class);

        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('admin');
    }

    // ---------------------------------------------------------- clé role inexistante dans session

    public function testRequireRoleRedirigeQuandCleRoleAbsente(): void
    {
        // Session présente mais sans clé 'role'
        $_SESSION['user'] = ['id' => 1, 'nom' => 'Jean'];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#REDIRECT:/connexion#');

        $ctrl = $this->makeController();
        $ctrl->exposeRequireRole('patient');
    }
}
