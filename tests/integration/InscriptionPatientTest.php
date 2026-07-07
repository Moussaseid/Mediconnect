<?php
namespace tests\integration;

use PHPUnit\Framework\TestCase;
use models\PatientModel;
use services\AuthService;

/**
 * Tests d'intégration — Issue #3 : Inscription patient
 *
 * Scénario complet : un nouveau visiteur remplit le formulaire d'inscription,
 * le PatientModel insère le patient, puis AuthService peut l'authentifier.
 *
 * Couvre :
 *  - Flux nominal : inscription + connexion immédiate
 *  - Email dupliqué : findByEmail() détecte la collision
 *  - Mot de passe hashé en base (non stocké en clair)
 *  - Rôle et statut corrects après inscription
 */
class InscriptionPatientTest extends TestCase
{
    private \PDO $pdo;
    private PatientModel $patientModel;
    private AuthService $authService;

    protected function setUp(): void
    {
        $this->pdo          = $GLOBALS['pdo'];
        $this->patientModel = new PatientModel($this->pdo);
        $this->authService  = new AuthService($this->pdo);
    }

    private function donneesNouveauPatient(string $email = 'nouveau_patient@test.fr'): array
    {
        return [
            'nom'        => 'Moreau',
            'prenom'     => 'Claire',
            'email'      => $email,
            'mot_de_passe' => 'Secure!999',
            'telephone'  => '0700000001',
            'adresse'    => '12 rue de la Paix',
            'ville'      => 'Bordeaux',
        ];
    }

    // ---------------------------------------------------------- flux nominal

    public function testFluxNominalInscriptionPuisConnexion(): void
    {
        $data = $this->donneesNouveauPatient();

        // 1. Pas encore inscrit
        $this->assertNull($this->patientModel->findByEmail($data['email']));

        // 2. Inscription
        $ok = $this->patientModel->creer($data);
        $this->assertTrue($ok);

        // 3. L'utilisateur existe désormais
        $row = $this->patientModel->findByEmail($data['email']);
        $this->assertNotNull($row);

        // 4. AuthService peut le connecter
        $session = $this->authService->login($data['email'], $data['mot_de_passe']);
        $this->assertNotNull($session);
        $this->assertSame('patient', $session['role']);
    }

    // ---------------------------------------------------------- email dupliqué

    public function testEmailDejaInscritEstDetecte(): void
    {
        // jean@test.fr est dans le seed
        $existing = $this->patientModel->findByEmail('jean@test.fr');
        $this->assertNotNull($existing, 'jean@test.fr doit exister dans le seed');

        // La logique du contrôleur appelle findByEmail() avant creer()
        // : on simule le comportement attendu
        $collision = $this->patientModel->findByEmail('jean@test.fr');
        $this->assertNotNull($collision);
    }

    // ---------------------------------------------------------- intégrité du hash

    public function testMotDePasseStockeEnHashEtJamaisEnClair(): void
    {
        $mdp  = 'PlainText123!';
        $data = $this->donneesNouveauPatient('hash_test@test.fr');
        $data['mot_de_passe'] = $mdp;

        $this->patientModel->creer($data);

        $row = $this->patientModel->findByEmail('hash_test@test.fr');
        $this->assertNotSame($mdp, $row['mot_de_passe_hash']);
        $this->assertTrue(password_verify($mdp, $row['mot_de_passe_hash']));
    }

    // ---------------------------------------------------------- rôle et statut

    public function testRoleEtStatutApresInscription(): void
    {
        $data = $this->donneesNouveauPatient('role_statut@test.fr');
        $this->patientModel->creer($data);

        $row = $this->patientModel->findByEmail('role_statut@test.fr');
        $this->assertSame('patient', $row['role']);
        $this->assertSame('actif',   $row['statut']);
    }

    // ---------------------------------------------------------- connexion impossible après inscription échouée

    public function testConnexionImpossibleAvecMauvaisMotDePasse(): void
    {
        $data = $this->donneesNouveauPatient('mauvais_mdp@test.fr');
        $this->patientModel->creer($data);

        $result = $this->authService->login('mauvais_mdp@test.fr', 'PasLesBonMdp!');
        $this->assertNull($result);
    }
}
