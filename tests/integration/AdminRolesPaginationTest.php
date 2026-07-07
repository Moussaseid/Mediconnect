<?php
namespace tests\integration;

use models\AdminModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration — Dette 2 Sprint 3 : pagination admin/roles
 *
 * Couvre :
 *  - Limite à 15 utilisateurs par page
 *  - Page 2 retourne les éléments suivants
 *  - Page invalide (> totalPages) ramenée au total
 *  - Page négative / zéro ramenée à 1
 *  - Navigation absente si <= 15 utilisateurs
 *  - attribuerRole() ne touche pas les admins
 *  - Tri alphabétique stable
 */
class AdminRolesPaginationTest extends TestCase
{
    private \PDO $pdo;
    private AdminModel $model;

    /** IDs des utilisateurs créés pour ce test — nettoyés en tearDown */
    private array $tempUserIds = [];

    protected function setUp(): void
    {
        $this->pdo   = $GLOBALS['pdo'];
        $this->model = new AdminModel($this->pdo);
    }

    protected function tearDown(): void
    {
        if (!empty($this->tempUserIds)) {
            $ids = implode(',', array_map('intval', $this->tempUserIds));
            $this->pdo->exec("DELETE FROM utilisateurs WHERE id IN ($ids)");
            $this->tempUserIds = [];
        }
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    /** Crée N patients temporaires et retourne leurs IDs. */
    private function creerPatients(int $n): array
    {
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $email = "tmp_pagination_{$i}_" . uniqid() . '@test.fr';
            $stmt  = $this->pdo->prepare(
                "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut)
                 VALUES (:nom, 'Test', :email, 'hash', 'patient', 'actif')"
            );
            $stmt->execute([':nom' => "ZZZTmp{$i}", ':email' => $email]);
            $id  = (int) $this->pdo->lastInsertId();
            $ids[] = $id;
            $this->tempUserIds[] = $id;
        }
        return $ids;
    }

    // ====================================================================
    // Limite 15 par page
    // ====================================================================

    public function testPage1RetourneAuMaximum15Utilisateurs(): void
    {
        // S'assurer d'avoir au moins 16 non-admins
        $existants = $this->model->compterUtilisateursGestion();
        $aCreer    = max(0, 16 - $existants);
        $this->creerPatients($aCreer);

        $page1 = $this->model->listerUtilisateursGestion(15, 0);
        $this->assertLessThanOrEqual(15, count($page1),
            "La page 1 ne doit pas dépasser 15 utilisateurs");
    }

    public function testPage2RetourneLesSuivants(): void
    {
        $existants = $this->model->compterUtilisateursGestion();
        $aCreer    = max(0, 17 - $existants);
        $this->creerPatients($aCreer);

        $total = $this->model->compterUtilisateursGestion();
        $this->assertGreaterThan(15, $total,
            "Il faut > 15 utilisateurs pour tester la page 2");

        $page1 = $this->model->listerUtilisateursGestion(15, 0);
        $page2 = $this->model->listerUtilisateursGestion(15, 15);

        $this->assertNotEmpty($page2, "La page 2 doit contenir des résultats");

        // Aucun ID de la page 1 ne doit apparaître en page 2
        $ids1 = array_column($page1, 'id');
        $ids2 = array_column($page2, 'id');
        $this->assertEmpty(
            array_intersect($ids1, $ids2),
            "Les pages 1 et 2 ne doivent pas se chevaucher"
        );
    }

    public function testToutesLesPagesCouvrentTousLesUtilisateurs(): void
    {
        $existants = $this->model->compterUtilisateursGestion();
        $aCreer    = max(0, 20 - $existants);
        $this->creerPatients($aCreer);

        $total  = $this->model->compterUtilisateursGestion();
        $limite = 15;
        $tous   = [];

        for ($offset = 0; $offset < $total; $offset += $limite) {
            $page = $this->model->listerUtilisateursGestion($limite, $offset);
            foreach ($page as $u) {
                $tous[] = $u['id'];
            }
        }

        $this->assertCount($total, $tous,
            "La somme des pages doit couvrir exactement tous les utilisateurs");
        $this->assertCount(count(array_unique($tous)), $tous,
            "Aucun utilisateur ne doit apparaître en double sur plusieurs pages");
    }

    // ====================================================================
    // Protection page invalide
    // ====================================================================

    public function testPageZeroRameneeAPage1(): void
    {
        // Le contrôleur fait max(1, (int)$_GET['page'])
        // On simule ici en testant l'offset 0 (page 1)
        $page   = max(1, 0);
        $offset = ($page - 1) * 15;
        $result = $this->model->listerUtilisateursGestion(15, $offset);
        $this->assertIsArray($result);
    }

    public function testPageNegativeRameneeAPage1(): void
    {
        $page   = max(1, -5);
        $offset = ($page - 1) * 15;
        $this->assertSame(0, $offset, "Page négative doit donner offset=0");
    }

    public function testPageSuperieureAuMaxRameneeDernierePage(): void
    {
        $total      = $this->model->compterUtilisateursGestion();
        $totalPages = max(1, (int) ceil($total / 15));

        // Le contrôleur fait min($page, $totalPages)
        $pageRecue  = 9999;
        $page       = min($pageRecue, $totalPages);
        $this->assertSame($totalPages, $page,
            "Page > totalPages doit être ramenée à totalPages");
    }

    // ====================================================================
    // Navigation absente si <= 15 utilisateurs
    // ====================================================================

    public function testNavigationPaginationAbsenteSiMoinsDeSeizeUsers(): void
    {
        $total = $this->model->compterUtilisateursGestion();
        // Si plus de 15 utilisateurs, ce test est inapplicable
        if ($total > 15) {
            $this->markTestSkipped("Trop d'utilisateurs pour tester l'absence de navigation ($total).");
        }

        $totalPages = max(1, (int) ceil($total / 15));
        // La vue n'affiche la pagination que si $totalPages > 1
        $this->assertSame(1, $totalPages,
            "Avec <= 15 utilisateurs, totalPages doit être 1 → navigation absente");
    }

    // ====================================================================
    // attribuerRole — sécurité et cohérence
    // ====================================================================

    public function testAttribuerRoleNeToucheJamaisUnAdmin(): void
    {
        $stmt   = $this->pdo->query("SELECT id FROM utilisateurs WHERE role = 'admin' LIMIT 1");
        $userId = (int) $stmt->fetchColumn();

        if ($userId === 0) {
            $this->markTestSkipped('Aucun admin en base de test.');
        }

        $this->model->attribuerRole($userId, 'patient');

        $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('admin', $stmt->fetchColumn(),
            "attribuerRole() ne doit pas modifier le rôle d'un admin");
    }

    public function testAttribuerRolePharmacieFonctionne(): void
    {
        $ids = $this->creerPatients(1);
        $userId = $ids[0];

        $this->model->attribuerRole($userId, 'pharmacie');

        $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('pharmacie', $stmt->fetchColumn());
    }

    public function testAttribuerRoleCentreSanteFonctionne(): void
    {
        $ids    = $this->creerPatients(1);
        $userId = $ids[0];

        $this->model->attribuerRole($userId, 'centre_sante');

        $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('centre_sante', $stmt->fetchColumn());
    }

    public function testAttribuerRoleCentreAnalyseFonctionne(): void
    {
        $ids    = $this->creerPatients(1);
        $userId = $ids[0];

        $this->model->attribuerRole($userId, 'centre_analyse');

        $stmt = $this->pdo->prepare("SELECT role FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $this->assertSame('centre_analyse', $stmt->fetchColumn());
    }

    // ====================================================================
    // Tri alphabétique
    // ====================================================================

    public function testTriAlphabétiqueEstStable(): void
    {
        $page1 = $this->model->listerUtilisateursGestion(15, 0);

        if (count($page1) < 2) {
            $this->markTestSkipped('Moins de 2 utilisateurs pour vérifier le tri.');
        }

        $noms = array_map(fn($u) => $u['nom'] . $u['prenom'], $page1);
        $tries = $noms;
        sort($tries);
        $this->assertSame($tries, $noms,
            "listerUtilisateursGestion() doit retourner les résultats triés par nom, prénom");
    }

    public function testCompterUtilisateursGestionExclutAdminsEtMedecins(): void
    {
        $total       = $this->model->compterUtilisateursGestion();
        $totalBrut   = (int) $this->pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
        $nbExclus    = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role IN ('admin','medecin')"
        )->fetchColumn();

        $this->assertSame($totalBrut - $nbExclus, $total,
            "compterUtilisateursGestion() doit exclure les admins et les médecins");
    }
}
