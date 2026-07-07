<?php
namespace tests\unit;

use PHPUnit\Framework\TestCase;
use services\AuthService;

/**
 * Tests unitaires — AuthService (Issue #4 & #7)
 *
 * Couvre :
 *  - login() retourne null si email inconnu
 *  - login() retourne null si mot de passe incorrect
 *  - login() retourne null si statut != actif
 *  - login() retourne le bon tableau sur succès
 *  - La structure du tableau retourné (id int, nom, email, role)
 *  - urlParRole() retourne la bonne URL selon le rôle
 *  - urlParRole() retourne /connexion pour un rôle inconnu
 */
class AuthServiceTest extends TestCase
{
    private \PDO $pdo;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->pdo  = $GLOBALS['pdo'];
        $this->auth = new AuthService($this->pdo);
    }

    // -------------------------------------------------------------- login()

    public function testLoginEmailInconnuRetourneNull(): void
    {
        $result = $this->auth->login('inconnu@nowhere.fr', 'Test1234');
        $this->assertNull($result);
    }

    public function testLoginMauvaisMotDePasseRetourneNull(): void
    {
        $result = $this->auth->login('jean@test.fr', 'MauvaisMotDePasse!');
        $this->assertNull($result);
    }

    public function testLoginStatutInactifRetourneNull(): void
    {
        // bloq@test.fr a statut = inactif
        $result = $this->auth->login('bloq@test.fr', 'Test1234');
        $this->assertNull($result);
    }

    public function testLoginPatientActifRetourneTableau(): void
    {
        $result = $this->auth->login('jean@test.fr', 'Test1234');
        $this->assertNotNull($result);
        $this->assertSame('jean@test.fr', $result['email']);
        $this->assertSame('patient', $result['role']);
        $this->assertIsInt($result['id']);
    }

    public function testLoginAdminRetourneTableauAvecRoleAdmin(): void
    {
        $result = $this->auth->login('admin@test.fr', 'Test1234');
        $this->assertNotNull($result);
        $this->assertSame('admin', $result['role']);
    }

    public function testLoginMedecinRetourneTableauAvecRoleMedecin(): void
    {
        $result = $this->auth->login('alice@test.fr', 'Test1234');
        $this->assertNotNull($result);
        $this->assertSame('medecin', $result['role']);
    }

    public function testLoginRetourneStructureAttendue(): void
    {
        $result = $this->auth->login('jean@test.fr', 'Test1234');
        $this->assertArrayHasKey('id',    $result);
        $this->assertArrayHasKey('nom',   $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('role',  $result);
        // La clé mot_de_passe_hash ne doit PAS être exposée
        $this->assertArrayNotHasKey('mot_de_passe_hash', $result);
    }

    // -------------------------------------------------------------- urlParRole()

    public function testUrlParRolePatient(): void
    {
        $this->assertSame('/patient/dashboard', $this->auth->urlParRole('patient'));
    }

    public function testUrlParRoleMedecin(): void
    {
        $this->assertSame('/medecin/dashboard', $this->auth->urlParRole('medecin'));
    }

    public function testUrlParRoleAdmin(): void
    {
        $this->assertSame('/admin/dashboard', $this->auth->urlParRole('admin'));
    }

    public function testUrlParRoleInconnuRetourneConnexion(): void
    {
        $this->assertSame('/connexion', $this->auth->urlParRole('superadmin'));
    }
}
