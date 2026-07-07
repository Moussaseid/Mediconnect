<?php
namespace tests\unit;

use PHPUnit\Framework\TestCase;
use services\SlotService;

/**
 * Tests unitaires — SlotService
 *
 * Couvre :
 *  - genererSemaine() sans horaires → tableau vide
 *  - genererSemaine() avec horaires → bons créneaux (nb + heures)
 *  - Un RDV déjà posé rend le créneau indisponible
 *  - Une indisponibilité rend les créneaux couverts indisponibles
 */
class SlotServiceTest extends TestCase
{
    private \PDO $pdo;
    private int  $medecinId;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['pdo'];

        $stmt = $this->pdo->prepare(
            "SELECT m.id FROM medecins m
             JOIN utilisateurs u ON u.id = m.utilisateur_id
             WHERE u.email = 'alice@test.fr' LIMIT 1"
        );
        $stmt->execute();
        $this->medecinId = (int) $stmt->fetchColumn();

        // Garantit duree_rdv=30 (peut avoir été modifié par AdminModelTest)
        $this->pdo->exec(
            "UPDATE medecins SET duree_rdv = 30 WHERE id = {$this->medecinId}"
        );
    }

    // ── Sans horaires → vide ──────────────────────────────────────────────────

    public function testGenererSemaineSansHorairesRetourneTableauVide(): void
    {
        // Crée un médecin temporaire sans aucun horaire
        $this->pdo->exec(
            "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut)
             VALUES ('Tmp','Doc','tmp.slot@test.fr','hash','medecin','actif')"
        );
        $uid = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO medecins (utilisateur_id, specialisation, numero_rpps, duree_rdv)
             VALUES ($uid,'Test','00011100011',30)"
        );
        $tmpId = (int) $this->pdo->lastInsertId();

        $lundi  = new \DateTimeImmutable('2027-01-04');
        $result = SlotService::genererSemaine($this->pdo, $tmpId, $lundi);

        $this->assertSame([], $result);

        // Nettoyage
        $this->pdo->exec("DELETE FROM medecins   WHERE id = $tmpId");
        $this->pdo->exec("DELETE FROM utilisateurs WHERE id = $uid");
    }

    // ── Avec horaires → bons créneaux ────────────────────────────────────────

    public function testGenererSemaineAvecHorairesRetourneCreneaux(): void
    {
        // Alice : Lun–Ven 09h–12h, duree_rdv=30 → 6 créneaux/jour × 5 jours
        $lundi  = new \DateTimeImmutable('2027-01-04'); // lundi lointain → tous futurs
        $result = SlotService::genererSemaine($this->pdo, $this->medecinId, $lundi);

        $this->assertCount(5, $result, '5 jours ouvrés attendus');

        $premierJour = reset($result);
        $this->assertArrayHasKey('creneaux', $premierJour);
        $this->assertCount(6, $premierJour['creneaux'], '6 créneaux de 30 min entre 09h et 12h');

        $heures = array_column($premierJour['creneaux'], 'debut');
        $this->assertSame(['09:00','09:30','10:00','10:30','11:00','11:30'], $heures);
    }

    // ── RDV existant → slot occupé ────────────────────────────────────────────

    public function testSlotAvecRdvExistantEstIndisponible(): void
    {
        // Le seed place un RDV le lundi 3 janvier 2028 à 09:00 pour alice
        $lundi  = new \DateTimeImmutable('2028-01-03');
        $result = SlotService::genererSemaine($this->pdo, $this->medecinId, $lundi);

        $this->assertArrayHasKey('2028-01-03', $result);
        $creneaux = $result['2028-01-03']['creneaux'];

        $slot900 = current(array_filter($creneaux, fn($c) => $c['debut'] === '09:00'));
        $this->assertFalse($slot900['disponible'], 'Créneau 09:00 doit être occupé (RDV seed)');

        $slot930 = current(array_filter($creneaux, fn($c) => $c['debut'] === '09:30'));
        $this->assertTrue($slot930['disponible'], 'Créneau 09:30 doit être libre');
    }

    // ── Indisponibilité → créneaux couverts indisponibles ────────────────────

    public function testSlotsPendantIndispoSontIndisponibles(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO indisponibilite (medecin_id, date_debut, date_fin, motif)
             VALUES (:mid, '2027-01-04 09:00:00', '2027-01-04 10:30:00', '__test_indispo__')"
        );
        $stmt->execute([':mid' => $this->medecinId]);

        $lundi  = new \DateTimeImmutable('2027-01-04');
        $result = SlotService::genererSemaine($this->pdo, $this->medecinId, $lundi);

        $creneaux = $result['2027-01-04']['creneaux'];

        foreach (['09:00', '09:30', '10:00'] as $h) {
            $slot = current(array_filter($creneaux, fn($c) => $c['debut'] === $h));
            $this->assertFalse($slot['disponible'], "Créneau $h doit être indisponible (indispo)");
        }
        foreach (['10:30', '11:00', '11:30'] as $h) {
            $slot = current(array_filter($creneaux, fn($c) => $c['debut'] === $h));
            $this->assertTrue($slot['disponible'], "Créneau $h doit être disponible");
        }

        // Nettoyage
        $this->pdo->exec("DELETE FROM indisponibilite WHERE motif = '__test_indispo__'");
    }
}