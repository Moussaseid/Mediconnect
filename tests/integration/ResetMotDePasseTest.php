<?php
namespace tests\integration;

use models\AdminModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration — Issue #20 : Réinitialisation de mot de passe
 *
 * Vérifie le flux complet :
 *   1. Création du token (creerTokenReset)
 *   2. Vérification du token (trouverTokenReset)
 *   3. Application du nouveau mot de passe (reinitialiserMotDePasse)
 *   4. Invalidation du token après usage
 *   5. Rejet des tokens expirés
 *   6. Protection des comptes admin
 */
class ResetMotDePasseTest extends TestCase
{
    private \PDO $pdo;
    private AdminModel $model;

    protected function setUp(): void
    {
        $this->pdo   = $GLOBALS['pdo'];
        $this->model = new AdminModel($this->pdo);

        // Nettoyer les tokens de test restants
        $this->pdo->exec("DELETE FROM reset_tokens WHERE email IN ('jean@test.fr', 'admin@test.fr')");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM reset_tokens WHERE email IN ('jean@test.fr', 'admin@test.fr')");
    }

    // ====================================================================
    // Flux nominal complet
    // ====================================================================

    public function testFluxCompletResetMotDePasse(): void
    {
        $token = bin2hex(random_bytes(32));

        // 1. Créer le token
        $ok = $this->model->creerTokenReset('jean@test.fr', $token);
        $this->assertTrue($ok, "Étape 1 : creerTokenReset doit réussir");

        // 2. Token retrouvable et non expiré
        $email = $this->model->trouverTokenReset($token);
        $this->assertSame('jean@test.fr', $email, "Étape 2 : trouverTokenReset doit retourner l'email");

        // 3. Réinitialiser le mot de passe
        $ok = $this->model->reinitialiserMotDePasse($token, 'Nouveau_Mdp_2025!');
        $this->assertTrue($ok, "Étape 3 : reinitialiserMotDePasse doit réussir");

        // 4. Nouveau mot de passe en base
        $stmt = $this->pdo->prepare("SELECT mot_de_passe_hash FROM utilisateurs WHERE email = :email");
        $stmt->execute([':email' => 'jean@test.fr']);
        $hash = $stmt->fetchColumn();
        $this->assertTrue(
            password_verify('Nouveau_Mdp_2025!', $hash),
            "Étape 4 : le nouveau mot de passe doit être correct"
        );

        // 5. Token invalidé (usage unique)
        $emailApres = $this->model->trouverTokenReset($token);
        $this->assertNull($emailApres, "Étape 5 : le token doit être supprimé après usage");

        // Remettre le mot de passe de test
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET mot_de_passe_hash = :h WHERE email = 'jean@test.fr'");
        $stmt->execute([':h' => '$2y$10$6Vr9Eovr3fulP2PRhMIrQeQRmCApUZAPLkKjxPMdLyvSY6BZMrzKK']);
    }

    // ====================================================================
    // Isolation : token usage unique
    // ====================================================================

    public function testTokenUsageUnique(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->creerTokenReset('jean@test.fr', $token);
        $this->model->reinitialiserMotDePasse($token, 'Mdp_Test_A');

        // Tenter une 2e réinitialisation avec le même token
        $ok = $this->model->reinitialiserMotDePasse($token, 'Mdp_Test_B');
        $this->assertFalse($ok, "Le token ne doit pas être réutilisable");

        // Remettre le mot de passe
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET mot_de_passe_hash = :h WHERE email = 'jean@test.fr'");
        $stmt->execute([':h' => '$2y$10$6Vr9Eovr3fulP2PRhMIrQeQRmCApUZAPLkKjxPMdLyvSY6BZMrzKK']);
    }

    // ====================================================================
    // Remplacement : nouveau token invalide l'ancien
    // ====================================================================

    public function testNouveauTokenRemplaceLAncien(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->model->creerTokenReset('jean@test.fr', $token1);
        $this->model->creerTokenReset('jean@test.fr', $token2);

        $this->assertNull($this->model->trouverTokenReset($token1),
            "L'ancien token doit être invalidé");
        $this->assertSame('jean@test.fr', $this->model->trouverTokenReset($token2),
            "Le nouveau token doit être valide");

        // Nettoyage
        $this->pdo->exec("DELETE FROM reset_tokens WHERE email = 'jean@test.fr'");
    }

    // ====================================================================
    // Sécurité : admin non réinitialisable via ce flux
    // ====================================================================

    public function testAdminNePeutPasReinitialiseSonMdpViaTokenFlow(): void
    {
        $token = bin2hex(random_bytes(32));
        $ok    = $this->model->creerTokenReset('admin@test.fr', $token);

        $this->assertFalse($ok,
            "Un admin ne doit pas pouvoir réinitialiser son mot de passe via le flux token");
    }

    // ====================================================================
    // Token expiré
    // ====================================================================

    public function testTokenExpireEstRejete(): void
    {
        $token = bin2hex(random_bytes(32));

        // Insérer manuellement un token expiré
        $stmt = $this->pdo->prepare(
            "INSERT INTO reset_tokens (email, token, expire_le)
             VALUES ('jean@test.fr', :token, DATE_SUB(NOW(), INTERVAL 2 HOUR))"
        );
        $stmt->execute([':token' => $token]);

        $this->assertNull($this->model->trouverTokenReset($token),
            "Token expiré doit être rejeté");

        $ok = $this->model->reinitialiserMotDePasse($token, 'NouveauMdp!');
        $this->assertFalse($ok, "reinitialiserMotDePasse avec token expiré doit retourner false");
    }

    // ====================================================================
    // Email inexistant — message neutre
    // ====================================================================

    public function testEmailInexistantRetourneFalseSansLeverException(): void
    {
        try {
            $ok = $this->model->creerTokenReset('nobody@nowhere.fr', bin2hex(random_bytes(32)));
            $this->assertFalse($ok);
        } catch (\Throwable $e) {
            $this->fail("creerTokenReset() ne doit pas lever d'exception: " . $e->getMessage());
        }
    }
}
