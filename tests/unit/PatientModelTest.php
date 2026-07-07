<?php
namespace tests\unit;

use PHPUnit\Framework\TestCase;
use models\PatientModel;

/**
 * Tests unitaires — PatientModel (Issue #3 & #4)
 *
 * Couvre :
 *  - findByEmail() retourne null si email inconnu
 *  - findByEmail() retourne le bon utilisateur
 *  - creer() insère un nouveau patient dans utilisateurs
 *  - creer() hash le mot de passe (jamais en clair)
 *  - creer() affecte le rôle patient et le statut actif
 */
class PatientModelTest extends TestCase
{
    private \PDO $pdo;
    private PatientModel $model;

    protected function setUp(): void
    {
        $this->pdo   = $GLOBALS['pdo'];
        $this->model = new PatientModel($this->pdo);
    }

    // -------------------------------------------------------------- findByEmail()

    public function testFindByEmailEmailInconnuRetourneNull(): void
    {
        $result = $this->model->findByEmail('nope@nowhere.fr');
        $this->assertNull($result);
    }

    public function testFindByEmailRetourneLeBonUtilisateur(): void
    {
        $result = $this->model->findByEmail('jean@test.fr');
        $this->assertNotNull($result);
        $this->assertSame('Dupont', $result['nom']);
        $this->assertSame('patient', $result['role']);
    }

    // -------------------------------------------------------------- creer()

    public function testCreerInsereUnNouveauPatient(): void
    {
        $data = [
            'nom'        => 'Durand',
            'prenom'     => 'Sophie',
            'email'      => 'sophie@nouveau.fr',
            'mot_de_passe' => 'MonMotDePasse1!',
            'telephone'  => '0600000099',
            'adresse'    => '10 rue Test',
            'ville'      => 'Lyon',
        ];

        $ok = $this->model->creer($data);
        $this->assertTrue($ok);

        $inserted = $this->model->findByEmail('sophie@nouveau.fr');
        $this->assertNotNull($inserted);
        $this->assertSame('Durand', $inserted['nom']);
    }

    public function testCreerHashLeMotDePasse(): void
    {
        $mdp = 'SuperSecret99!';
        $this->model->creer([
            'nom'        => 'Hash',
            'prenom'     => 'Test',
            'email'      => 'hash@test.fr',
            'mot_de_passe' => $mdp,
            'telephone'  => '',
            'adresse'    => '',
            'ville'      => '',
        ]);

        $row = $this->model->findByEmail('hash@test.fr');
        // Le hash doit être différent du mot de passe en clair
        $this->assertNotSame($mdp, $row['mot_de_passe_hash']);
        // Et le hash doit valider le mot de passe original
        $this->assertTrue(password_verify($mdp, $row['mot_de_passe_hash']));
    }

    public function testCreerAffecteRolePatientEtStatutActif(): void
    {
        $this->model->creer([
            'nom'        => 'Role',
            'prenom'     => 'Test',
            'email'      => 'role@test.fr',
            'mot_de_passe' => 'Test1234',
            'telephone'  => '',
            'adresse'    => '',
            'ville'      => '',
        ]);

        $row = $this->model->findByEmail('role@test.fr');
        $this->assertSame('patient', $row['role']);
        $this->assertSame('actif',   $row['statut']);
    }
}
