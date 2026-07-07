<?php
namespace tests\integration;

use PHPUnit\Framework\TestCase;
use services\AuthService;

/**
 * Tests d'intégration — Issue #4 : Connexion patient (formulaire unifié)
 *
 * La connexion passe désormais par AuthService::login() qui interroge
 * la table utilisateurs unifiée quel que soit le rôle.
 *
 * Couvre :
 *  - Connexion patient réussie
 *  - Connexion médecin réussie (même formulaire)
 *  - Connexion admin réussie (même formulaire)
 *  - Email inconnu → null
 *  - Mot de passe incorrect → null
 *  - Compte inactif → null
 *  - Structure de session retournée (jamais de hash exposé)
 *  - urlParRole() oriente correctement après login
 */
class ConnexionPatientTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->auth = new AuthService($GLOBALS['pdo']);
    }

    // ---------------------------------------------------------- succès

    public function testConnexionPatientReussie(): void
    {
        $result = $this->auth->login('jean@test.fr', 'Test1234');
        $this->assertNotNull($result);
        $this->assertSame('patient', $result['role']);
        $this->assertSame('jean@test.fr', $result['email']);
    }

    public function testConnexionMedecinReussie(): void
    {
        $result = $this->auth->login('alice@test.fr', 'Test1234');
        $this->assertNotNull($result);
        $this->assertSame('medecin', $result['role']);
    }

    public function testConnexionAdminReussie(): void
    {
        $result = $this->auth->login('admin@test.fr', 'Test1234');
        $this->assertNotNull($result);
        $this->assertSame('admin', $result['role']);
    }

    // ---------------------------------------------------------- échecs

    public function testEmailInconnuRetourneNull(): void
    {
        $this->assertNull($this->auth->login('fantome@test.fr', 'Test1234'));
    }

    public function testMotDePasseIncorrectRetourneNull(): void
    {
        $this->assertNull($this->auth->login('jean@test.fr', 'MauvaisMdp!'));
    }

    public function testCompteInactifRetourneNull(): void
    {
        // bloq@test.fr a statut = inactif dans le seed
        $this->assertNull($this->auth->login('bloq@test.fr', 'Test1234'));
    }

    // ---------------------------------------------------------- structure de session

    public function testStructureSessionRetourneeCorrecte(): void
    {
        $result = $this->auth->login('jean@test.fr', 'Test1234');
        $this->assertIsInt($result['id']);
        $this->assertIsString($result['nom']);
        $this->assertIsString($result['email']);
        $this->assertIsString($result['role']);
        $this->assertArrayNotHasKey('mot_de_passe_hash', $result);
        $this->assertArrayNotHasKey('statut',            $result);
    }

    // ---------------------------------------------------------- redirection post-login

    public function testRedirectionPostLoginPatient(): void
    {
        $result = $this->auth->login('jean@test.fr', 'Test1234');
        $url    = $this->auth->urlParRole($result['role']);
        $this->assertSame('/patient/dashboard', $url);
    }

    public function testRedirectionPostLoginMedecin(): void
    {
        $result = $this->auth->login('alice@test.fr', 'Test1234');
        $url    = $this->auth->urlParRole($result['role']);
        $this->assertSame('/medecin/dashboard', $url);
    }

    public function testRedirectionPostLoginAdmin(): void
    {
        $result = $this->auth->login('admin@test.fr', 'Test1234');
        $url    = $this->auth->urlParRole($result['role']);
        $this->assertSame('/admin/dashboard', $url);
    }
}
