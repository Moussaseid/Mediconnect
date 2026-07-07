<?php
namespace tests\unit;

use models\MedecinModel;
use services\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — Dette technique Sprint 3 : statut dupliqué
 *
 * Vérifie que :
 *  - utilisateurs.statut est la SEULE source de vérité pour l'accès
 *  - La connexion est refusée si statut != 'actif' (AuthService)
 *  - La validation admin met bien à jour utilisateurs.statut
 *  - medecins n'a plus de colonne statut (refactoring vérifié en BDD)
 */
class StatutCoherenceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['pdo'];
    }

    // ====================================================================
    // Source de vérité unique : utilisateurs.statut
    // ====================================================================

    public function testMedecinsNAPlusDeColonneStatut(): void
    {
        // Vérifier que la colonne statut n'existe plus dans medecins
        $stmt   = $this->pdo->query("DESCRIBE medecins");
        $colonnes = array_column($stmt->fetchAll(), 'Field');

        $this->assertNotContains(
            'statut',
            $colonnes,
            "medecins.statut doit avoir été supprimé — source de vérité unique : utilisateurs.statut"
        );
    }

    public function testUtilisateursALaColonneStatut(): void
    {
        $stmt     = $this->pdo->query("DESCRIBE utilisateurs");
        $colonnes = array_column($stmt->fetchAll(), 'Field');

        $this->assertContains(
            'statut',
            $colonnes,
            "utilisateurs.statut doit exister — c'est la source de vérité"
        );
    }

    // ====================================================================
    // Connexion bloquée si utilisateurs.statut != 'actif'
    // ====================================================================

    public function testConnexionRefuseeAvecStatutInactif(): void
    {
        $authService = new AuthService($this->pdo);

        // bloq@test.fr a statut='inactif' dans les fixtures de test
        $result = $authService->login('bloq@test.fr', 'Test1234');

        $this->assertNull(
            $result,
            "La connexion doit être refusée quand utilisateurs.statut != 'actif'"
        );
    }

    public function testConnexionReussieAvecStatutActif(): void
    {
        $authService = new AuthService($this->pdo);

        $result = $authService->login('jean@test.fr', 'Test1234');

        $this->assertNotNull(
            $result,
            "La connexion doit réussir quand utilisateurs.statut = 'actif'"
        );
    }

    // ====================================================================
    // Validation admin → utilisateurs.statut mis à jour
    // ====================================================================

    public function testValidationAdminMetsAJourUtilisateursStatut(): void
    {
        $medecinModel = new MedecinModel($this->pdo);

        // Récupérer la demande en attente (marc@test.fr dans les fixtures)
        $stmt   = $this->pdo->query("SELECT * FROM demandes_professionnels WHERE statut = 'en_attente' LIMIT 1");
        $demande = $stmt->fetch();

        if (!$demande) {
            $this->markTestSkipped('Aucune demande en_attente dans les fixtures.');
        }

        $mdpTemporaire = 'TestMdp2025!';
        $userId        = $medecinModel->approuver($demande, $mdpTemporaire, 0);

        // Vérifier dans utilisateurs.statut (PAS dans medecins)
        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $statut = $stmt->fetchColumn();

        $this->assertSame(
            'actif',
            $statut,
            "Après approbation, utilisateurs.statut doit être 'actif'"
        );

        // Vérifier que medecins n'a PAS de colonne statut à vérifier
        $stmt = $this->pdo->prepare("SELECT id FROM medecins WHERE utilisateur_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $medecinRow = $stmt->fetch();
        $this->assertNotFalse($medecinRow, "Le profil médecin doit exister");
        $this->assertArrayNotHasKey(
            'statut',
            $medecinRow,
            "La ligne medecins ne doit pas contenir de colonne statut"
        );
    }

    // ====================================================================
    // Cohérence : aucune incohérence possible (colonne supprimée)
    // ====================================================================

    public function testAucuneIncohérenceStatutPossible(): void
    {
        // Avant : on devait vérifier WHERE u.statut != m.statut
        // Après : la requête est impossible — medecins n'a plus de statut
        // On vérifie qu'une requête sur medecins.statut lève bien une erreur PDO

        try {
            $this->pdo->query("SELECT statut FROM medecins LIMIT 1");
            $this->fail(
                "La requête sur medecins.statut doit lever une exception — colonne supprimée"
            );
        } catch (\PDOException $e) {
            $this->assertStringContainsString(
                'Unknown column',
                $e->getMessage(),
                "L'exception doit signaler que la colonne statut n'existe plus dans medecins"
            );
        }
    }

    public function testUtilisateursStatutEstLaSeuleReferenceEnCode(): void
    {
        // Vérification architecturale : les deux points de contrôle d'accès
        // lisent uniquement utilisateurs.statut

        // 1. AuthService::login() → lit utilisateurs.statut
        // 2. BaseController::requireRole() → lit utilisateurs.statut
        // On vérifie que les deux fichiers ne référencent pas medecins.statut

        $authServiceCode    = file_get_contents(ROOT . '/src/services/AuthService.php');
        $baseControllerCode = file_get_contents(ROOT . '/src/controllers/BaseController.php');

        // Aucune référence à "medecins.statut" ou "m.statut" dans les fichiers d'accès
        $this->assertStringNotContainsString(
            'medecins.statut',
            $authServiceCode,
            "AuthService ne doit pas référencer medecins.statut"
        );
        $this->assertStringNotContainsString(
            'medecins.statut',
            $baseControllerCode,
            "BaseController ne doit pas référencer medecins.statut"
        );

        // Les deux fichiers référencent bien utilisateurs (via la requête SQL)
        $this->assertStringContainsString('utilisateurs', $authServiceCode);
        $this->assertStringContainsString('utilisateurs', $baseControllerCode);
    }
}
