<?php
namespace tests\unit;

use models\AdminModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — AdminModel
 *
 * Couvre les méthodes ajoutées dans la milestone Finition :
 *  #21 — listerPatients(), compterPatients()
 *  #30 — listerMedecins(), compterMedecins(), findMedecinParId(),
 *          modifierMedecin(), changerStatutUtilisateur(), supprimerMedecin()
 *  #29 — listerUtilisateursGestion(), compterUtilisateursGestion(), attribuerRole()
 *  #20 — creerTokenReset(), trouverTokenReset(), reinitialiserMotDePasse()
 */
class AdminModelTest extends TestCase
{
    private \PDO $pdo;
    private AdminModel $model;

    protected function setUp(): void
    {
        $this->pdo   = $GLOBALS['pdo'];
        $this->model = new AdminModel($this->pdo);
    }

    // ====================================================================
    // #21 — Liste patients
    // ====================================================================

    public function testListerPatientsRetourneUniquementLesPatients(): void
    {
        $patients = $this->model->listerPatients(10, 0);

        // La requête ne retourne pas la colonne role (WHERE role='patient' est le filtre).
        // On vérifie en recroisant avec la BDD.
        foreach ($patients as $p) {
            $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
            $stmt->execute([':id' => $p['id']]);
            $this->assertSame('patient', $stmt->fetchColumn(),
                "Chaque ligne de listerPatients() doit appartenir à un utilisateur de rôle 'patient'");
        }
    }

    public function testListerPatientsRetourneLesBonnesColonnes(): void
    {
        $patients = $this->model->listerPatients(10, 0);

        if (empty($patients)) {
            $this->markTestSkipped('Aucun patient en base de test.');
        }

        $attendues = ['id', 'nom', 'prenom', 'email', 'telephone', 'ville', 'statut', 'created_at'];
        foreach ($attendues as $col) {
            $this->assertArrayHasKey($col, $patients[0], "Colonne '$col' manquante dans listerPatients()");
        }
    }

    public function testCompterPatientsRetourneUnEntier(): void
    {
        $nb = $this->model->compterPatients();
        $this->assertIsInt($nb);
        $this->assertGreaterThanOrEqual(0, $nb);
    }

    public function testCompterPatientsCorrespondAListerPatients(): void
    {
        $total    = $this->model->compterPatients();
        $patients = $this->model->listerPatients(1000, 0);
        $this->assertSame(count($patients), $total,
            "compterPatients() doit correspondre au nombre de lignes retournées");
    }

    public function testListerPatientsPaginationLimite(): void
    {
        // Si au moins 2 patients en base, la limite=1 ne doit retourner qu'1 ligne
        $tous = $this->model->listerPatients(1000, 0);
        if (count($tous) < 2) {
            $this->markTestSkipped('Moins de 2 patients pour tester la pagination.');
        }

        $page1 = $this->model->listerPatients(1, 0);
        $this->assertCount(1, $page1, "Limite=1 doit retourner exactement 1 résultat");
    }

    // ====================================================================
    // #30 — Gestion médecins
    // ====================================================================

    public function testListerMedecinsRetourneLesBonnesDonnees(): void
    {
        $medecins = $this->model->listerMedecins(10, 0);

        foreach ($medecins as $m) {
            $this->assertArrayHasKey('specialisation', $m);
            $this->assertArrayHasKey('numero_rpps', $m);
            $this->assertArrayHasKey('email', $m);
        }
    }

    public function testCompterMedecinsEstCohérent(): void
    {
        $total    = $this->model->compterMedecins();
        $medecins = $this->model->listerMedecins(1000, 0);
        $this->assertSame(count($medecins), $total);
    }

    public function testFindMedecinParIdRetourneLeBonMedecin(): void
    {
        // Récupère l'ID utilisateur du médecin de test (alice@test.fr)
        $stmt = $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'alice@test.fr' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Médecin de test introuvable.');
        }

        $medecin = $this->model->findMedecinParId($userId);

        $this->assertNotNull($medecin, 'findMedecinParId() doit trouver alice@test.fr');
        $this->assertSame('alice@test.fr', $medecin['email']);
        $this->assertSame('Cardiologie', $medecin['specialisation']);
        $this->assertArrayHasKey('duree_rdv', $medecin);
    }

    public function testFindMedecinParIdRetourneNullSiInexistant(): void
    {
        $result = $this->model->findMedecinParId(999999);
        $this->assertNull($result, "ID inexistant doit retourner null");
    }

    public function testFindMedecinParIdRefuseUnPatient(): void
    {
        $stmt   = $this->pdo->query("SELECT id FROM utilisateurs WHERE role = 'patient' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Aucun patient en base de test.');
        }

        $result = $this->model->findMedecinParId($userId);
        $this->assertNull($result, "Un patient ne doit pas être retourné par findMedecinParId()");
    }

    public function testModifierMedecinMetsAJourLesDeuxTables(): void
    {
        $stmt   = $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'alice@test.fr' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Médecin de test introuvable.');
        }

        $data = [
            'nom'             => 'Martin_Modifié',
            'prenom'          => 'Alice_Modifié',
            'email'           => 'alice@test.fr',
            'telephone'       => '0700000001',
            'adresse'         => '1 rue Test',
            'ville'           => 'Lyon',
            'specialisation'  => 'Neurologie',
            'adresse_cabinet' => '10 rue B, Lyon',
            'duree_rdv'       => 45,
        ];

        $this->model->modifierMedecin($userId, $data);

        $medecin = $this->model->findMedecinParId($userId);
        $this->assertNotNull($medecin);
        $this->assertSame('Martin_Modifié', $medecin['nom']);
        $this->assertSame('Neurologie', $medecin['specialisation']);
        $this->assertSame(45, (int) $medecin['duree_rdv']);
    }

    public function testChanterStatutUtilisateurSuspend(): void
    {
        $stmt   = $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'jean@test.fr' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Patient de test introuvable.');
        }

        $this->model->changerStatutUtilisateur($userId, 'suspendu');

        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('suspendu', $stmt->fetchColumn());

        // Rétablir pour ne pas casser d'autres tests
        $this->model->changerStatutUtilisateur($userId, 'actif');
    }

    public function testSupprimerMedecinElimineLesDeuxLignes(): void
    {
        // Créer un médecin temporaire pour la suppression
        $this->pdo->exec(
            "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut)
             VALUES ('TmpDoc', 'Test', 'tmp_doc@test.fr',
                     '\$2y\$10\$hash_tmp', 'medecin', 'actif')"
        );
        $userId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO medecins (utilisateur_id, specialisation, numero_rpps, adresse_cabinet)
             VALUES ($userId, 'Test', '00000000001', 'Cabinet Test')"
        );

        $this->model->supprimerMedecin($userId);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), "L'utilisateur doit être supprimé");

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM medecins WHERE utilisateur_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), "Le profil médecin doit être supprimé en cascade");
    }

    // ====================================================================
    // #29 — Attribution rôles
    // ====================================================================

    public function testListerUtilisateursGestionExclutAdminsEtMedecins(): void
    {
        $users = $this->model->listerUtilisateursGestion(100, 0);

        foreach ($users as $u) {
            $this->assertNotSame('admin',   $u['role'], "Admin ne doit pas apparaître dans listerUtilisateursGestion()");
            $this->assertNotSame('medecin', $u['role'], "Médecin ne doit pas apparaître dans listerUtilisateursGestion()");
        }
    }

    public function testCompterUtilisateursGestionEstCoherent(): void
    {
        $total = $this->model->compterUtilisateursGestion();
        $users = $this->model->listerUtilisateursGestion(1000, 0);
        $this->assertSame(count($users), $total);
    }

    public function testAttribuerRoleModifieLeBonUtilisateur(): void
    {
        $stmt   = $this->pdo->query("SELECT id FROM utilisateurs WHERE email = 'jean@test.fr' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Patient jean@test.fr introuvable.');
        }

        $this->model->attribuerRole($userId, 'pharmacie');

        $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('pharmacie', $stmt->fetchColumn());

        // Remettre en patient
        $this->model->attribuerRole($userId, 'patient');
    }

    public function testAttribuerRoleNeToucheJamaisUnAdmin(): void
    {
        $stmt   = $this->pdo->query("SELECT id FROM utilisateurs WHERE role = 'admin' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Admin de test introuvable.');
        }

        $this->model->attribuerRole($userId, 'patient');

        $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('admin', $stmt->fetchColumn(),
            "attribuerRole() ne doit pas modifier le rôle d'un admin");
    }

    // ====================================================================
    // #20 — Reset mot de passe
    // ====================================================================

    public function testCreerTokenResetReussitPourEmailValide(): void
    {
        $token  = bin2hex(random_bytes(32));
        $result = $this->model->creerTokenReset('jean@test.fr', $token);

        $this->assertTrue($result, "creerTokenReset() doit retourner true pour un email existant");

        // Nettoyage
        $this->pdo->exec("DELETE FROM reset_tokens WHERE token = '$token'");
    }

    public function testCreerTokenResetEchouePourEmailInexistant(): void
    {
        $token  = bin2hex(random_bytes(32));
        $result = $this->model->creerTokenReset('inexistant@nowhere.fr', $token);

        $this->assertFalse($result, "creerTokenReset() doit retourner false pour un email inconnu");
    }

    public function testCreerTokenResetEchouePourEmailAdmin(): void
    {
        // Les admins ne doivent pas pouvoir réinitialiser leur mot de passe via ce flux
        $token  = bin2hex(random_bytes(32));
        $result = $this->model->creerTokenReset('admin@test.fr', $token);

        $this->assertFalse($result, "creerTokenReset() doit refuser les comptes admin");
    }

    public function testCreerTokenResetRemplaceLAncienToken(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->model->creerTokenReset('jean@test.fr', $token1);
        $this->model->creerTokenReset('jean@test.fr', $token2);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM reset_tokens WHERE email = 'jean@test.fr'");
        $this->assertSame(1, (int) $stmt->fetchColumn(),
            "Un seul token doit exister par email après remplacement");

        // Nettoyage
        $this->pdo->exec("DELETE FROM reset_tokens WHERE email = 'jean@test.fr'");
    }

    public function testTrouverTokenResetRetourneLEmailPourTokenValide(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->creerTokenReset('jean@test.fr', $token);

        $email = $this->model->trouverTokenReset($token);
        $this->assertSame('jean@test.fr', $email);

        // Nettoyage
        $this->pdo->exec("DELETE FROM reset_tokens WHERE token = '$token'");
    }

    public function testTrouverTokenResetRetourneNullPourTokenInexistant(): void
    {
        $result = $this->model->trouverTokenReset('token_qui_nexiste_pas_du_tout_abc123');
        $this->assertNull($result);
    }

    public function testTrouverTokenResetRetourneNullPourTokenExpire(): void
    {
        $token = bin2hex(random_bytes(32));
        // Insérer un token déjà expiré
        $stmt = $this->pdo->prepare(
            "INSERT INTO reset_tokens (email, token, expire_le)
             VALUES ('jean@test.fr', :token, DATE_SUB(NOW(), INTERVAL 1 HOUR))"
        );
        $stmt->execute([':token' => $token]);

        $result = $this->model->trouverTokenReset($token);
        $this->assertNull($result, "Un token expiré doit retourner null");

        // Nettoyage
        $stmt = $this->pdo->prepare("DELETE FROM reset_tokens WHERE token = :token");
        $stmt->execute([':token' => $token]);
    }

    public function testReinitialiserMotDePasseChangeLeMdpEtSupprimeLeLToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->creerTokenReset('jean@test.fr', $token);

        $ok = $this->model->reinitialiserMotDePasse($token, 'NouveauMdp2024!');

        $this->assertTrue($ok, "reinitialiserMotDePasse() doit retourner true");

        // Vérifier que le mot de passe a bien changé
        $stmt = $this->pdo->query("SELECT mot_de_passe_hash FROM utilisateurs WHERE email = 'jean@test.fr'");
        $hash = $stmt->fetchColumn();
        $this->assertTrue(
            password_verify('NouveauMdp2024!', $hash),
            "Le nouveau mot de passe doit être vérifié correctement"
        );

        // Vérifier que le token est supprimé (usage unique)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reset_tokens WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), "Le token doit être supprimé après usage");

        // Remettre le mdp de test pour ne pas casser d'autres tests
        $hash = password_hash('Test1234', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET mot_de_passe_hash = :hash WHERE email = 'jean@test.fr'");
        $stmt->execute([':hash' => $hash]);
    }

    public function testReinitialiserMotDePasseRetourneFalsePourTokenInvalide(): void
    {
        $ok = $this->model->reinitialiserMotDePasse('token_invalide_xyz', 'NouveauMdp!');
        $this->assertFalse($ok, "Token invalide → false attendu");
    }

    // ====================================================================
    // Cas limites / sécurité injection SQL
    // ====================================================================

    public function testCreerTokenResetResisteLInjectionSQL(): void
    {
        $emailXss = "' OR '1'='1";
        $result   = $this->model->creerTokenReset($emailXss, bin2hex(random_bytes(32)));
        $this->assertFalse($result, "Injection SQL dans l'email doit retourner false (aucun utilisateur)");
    }

    public function testTrouverTokenResetResisteLInjectionSQL(): void
    {
        $result = $this->model->trouverTokenReset("' OR '1'='1' --");
        $this->assertNull($result, "Injection SQL dans le token doit retourner null");
    }

    public function testAttribuerRoleAvecIdZeroNeCrashePas(): void
    {
        // Doit s'exécuter sans exception (0 lignes affectées, pas d'erreur)
        $this->model->attribuerRole(0, 'patient');
        $this->assertTrue(true);
    }
}
