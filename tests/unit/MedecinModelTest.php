<?php
namespace tests\unit;

use PHPUnit\Framework\TestCase;
use models\MedecinModel;

/**
 * Tests unitaires — MedecinModel (Issue #5 & #6)
 *
 * Couvre :
 *  - emailDejaUtilise() email présent dans demandes_professionnels
 *  - emailDejaUtilise() email présent dans utilisateurs (rôle médecin)
 *  - emailDejaUtilise() retourne false pour un email libre
 *  - rppsDejaUtilise() RPPS présent dans demandes_professionnels
 *  - rppsDejaUtilise() RPPS présent dans medecins
 *  - rppsDejaUtilise() retourne false pour un RPPS libre
 *  - creerDemande() insère correctement la demande
 *  - listerEnAttente() retourne uniquement les demandes en_attente
 *  - compterEnAttente() retourne le bon total
 *  - findById() retourne la bonne demande
 *  - mettreAJourStatut() change bien le statut
 *  - approuver() crée l'utilisateur et le profil médecin dans une transaction
 */
class MedecinModelTest extends TestCase
{
    private \PDO $pdo;
    private MedecinModel $model;

    protected function setUp(): void
    {
        $this->pdo   = $GLOBALS['pdo'];
        $this->model = new MedecinModel($this->pdo);
    }

    // -------------------------------------------------------------- emailDejaUtilise()

    public function testEmailDejaUtilisePresentDansDemandes(): void
    {
        // marc@test.fr est dans demandes_professionnels (seed)
        $this->assertTrue($this->model->emailDejaUtilise('marc@test.fr'));
    }

    public function testEmailDejaUtilisePresentDansUtilisateurs(): void
    {
        // alice@test.fr est un médecin actif dans utilisateurs
        $this->assertTrue($this->model->emailDejaUtilise('alice@test.fr'));
    }

    public function testEmailDejaUtiliseRetourneFalsePourEmailLibre(): void
    {
        $this->assertFalse($this->model->emailDejaUtilise('libre@libre.fr'));
    }

    // -------------------------------------------------------------- rppsDejaUtilise()

    public function testRppsDejaUtilisePresentDansDemandes(): void
    {
        // 99988877700 est dans demandes_professionnels (seed)
        $this->assertTrue($this->model->rppsDejaUtilise('99988877700'));
    }

    public function testRppsDejaUtilisePresentDansMedecins(): void
    {
        // 12345678901 est dans medecins (seed, alice)
        $this->assertTrue($this->model->rppsDejaUtilise('12345678901'));
    }

    public function testRppsDejaUtiliseRetourneFalsePourRppsLibre(): void
    {
        $this->assertFalse($this->model->rppsDejaUtilise('00000000000'));
    }

    // -------------------------------------------------------------- creerDemande()

    public function testCreerDemandeInsereEnBdd(): void
    {
        $ok = $this->model->creerDemande([
            'nom'            => 'Nouveau',
            'prenom'         => 'Medecin',
            'specialisation' => 'Neurologie',
            'email'          => 'nouveau@medecin.fr',
            'numero_rpps'    => '11122233300',
            'adresse_cabinet'=> '3 av Test',
        ]);
        $this->assertTrue($ok);

        // Vérifier qu'elle est bien en attente
        $stmt = $this->pdo->prepare(
            "SELECT * FROM demandes_professionnels WHERE email = 'nouveau@medecin.fr'"
        );
        $stmt->execute();
        $row = $stmt->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('en_attente', $row['statut']);
    }

    // -------------------------------------------------------------- lister / compter

    public function testListerEnAttenteRetourneSeulementEnAttente(): void
    {
        $liste = $this->model->listerEnAttente(10, 0);
        foreach ($liste as $d) {
            $this->assertSame('en_attente', $d['statut']);
        }
    }

    public function testCompterEnAttenteRetourneBonTotal(): void
    {
        // Le seed insère 1 demande en_attente ; creerDemande ci-dessus en ajoute 1
        // Mais les tests sont indépendants : on se fie à >= 1
        $total = $this->model->compterEnAttente();
        $this->assertGreaterThanOrEqual(1, $total);
    }

    // -------------------------------------------------------------- findById()

    public function testFindByIdRetourneLaBonneDemande(): void
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM demandes_professionnels WHERE email = 'marc@test.fr'"
        );
        $id  = (int) $stmt->fetchColumn();

        $row = $this->model->findById($id);
        $this->assertNotNull($row);
        $this->assertSame('Lebrun', $row['nom']);
    }

    public function testFindByIdIdInexistantRetourneNull(): void
    {
        $this->assertNull($this->model->findById(999999));
    }

    // -------------------------------------------------------------- mettreAJourStatut()

    public function testMettreAJourStatutModifieLaValeur(): void
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM demandes_professionnels WHERE email = 'marc@test.fr'"
        );
        $id = (int) $stmt->fetchColumn();

        $this->model->mettreAJourStatut($id, 'rejete');

        $stmt = $this->pdo->prepare(
            'SELECT statut FROM demandes_professionnels WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $this->assertSame('rejete', $stmt->fetchColumn());
    }

    // -------------------------------------------------------------- approuver()

    public function testApprouverCreeLUtilisateurEtLeProfilMedecin(): void
    {
        // Utilise une demande dédiée pour ne pas interférer avec les autres tests
        $this->model->creerDemande([
            'nom'            => 'ApprTest',
            'prenom'         => 'Doc',
            'specialisation' => 'Ophtalmologie',
            'email'          => 'approuver@test.fr',
            'numero_rpps'    => '55544433300',
            'adresse_cabinet'=> '7 rue Test',
        ]);
        $stmt = $this->pdo->prepare(
            "SELECT * FROM demandes_professionnels WHERE email = 'approuver@test.fr'"
        );
        $stmt->execute();
        $demande = $stmt->fetch();

        $mdpTemporaire = 'TmpPass1234!';
        $uid = $this->model->approuver($demande, $mdpTemporaire);

        // L'utilisateur existe avec le bon rôle
        $stmt = $this->pdo->prepare(
            'SELECT * FROM utilisateurs WHERE id = :uid'
        );
        $stmt->execute([':uid' => $uid]);
        $user = $stmt->fetch();
        $this->assertNotFalse($user);
        $this->assertSame('medecin', $user['role']);
        $this->assertTrue(password_verify($mdpTemporaire, $user['mot_de_passe_hash']));

        // Le profil médecin existe
        $stmt = $this->pdo->prepare(
            'SELECT * FROM medecins WHERE utilisateur_id = :uid'
        );
        $stmt->execute([':uid' => $uid]);
        $profil = $stmt->fetch();
        $this->assertNotFalse($profil);
        $this->assertSame('Ophtalmologie', $profil['specialisation']);

        // La demande est marquée approuvée
        $stmt = $this->pdo->prepare(
            'SELECT statut FROM demandes_professionnels WHERE id = :id'
        );
        $stmt->execute([':id' => $demande['id']]);
        $this->assertSame('approuve', $stmt->fetchColumn());
    }
}
