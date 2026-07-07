<?php
namespace controllers\api;

use services\MongoLogService;

/**
 * AnalyticsApiController — Données analytiques pour Power BI (P2 Sprint 3)
 *
 * GET /api/analytics/recherches  → stats sur search_logs MongoDB
 * GET /api/analytics/geo         → densité médecins par ville (MySQL)
 * GET /api/analytics/specialites → distribution des spécialités (MySQL)
 */
class AnalyticsApiController
{
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->mongoLog = new MongoLogService();
    }

    // ── GET /api/analytics/recherches ───────────────────────────────────────
    public function recherches(array $params = []): void
    {
        $stats = $this->mongoLog->statsRecherches();
        $this->ok($stats);
    }

    // ── GET /api/analytics/geo ───────────────────────────────────────────────
    public function geo(array $params = []): void
    {
        // Densité médecins par ville (depuis MySQL)
        $stmt = $this->pdo->query("
            SELECT
                COALESCE(NULLIF(TRIM(u.ville), ''), 'Inconnu') AS ville,
                COUNT(m.id)                                     AS nb_medecins,
                COUNT(DISTINCT m.specialisation)                AS nb_specialites
            FROM medecins m
            JOIN utilisateurs u ON u.id = m.utilisateur_id
            WHERE u.statut = 'actif'
              AND m.latitude IS NOT NULL
            GROUP BY ville
            ORDER BY nb_medecins DESC
        ");
        $densiteVilles = $stmt->fetchAll();

        // Pharmacies par ville
        $stmt2 = $this->pdo->query("
            SELECT
                COALESCE(NULLIF(TRIM(ville), ''), 'Inconnu') AS ville,
                COUNT(id) AS nb_pharmacies
            FROM pharmacies
            WHERE actif = 1
            GROUP BY ville
            ORDER BY nb_pharmacies DESC
        ");
        $densitePharmacies = $stmt2->fetchAll();

        // Centres par type
        $stmt3 = $this->pdo->query("
            SELECT 'sante'   AS type, COUNT(id) AS total FROM centres_sante   WHERE actif = 1
            UNION ALL
            SELECT 'analyse' AS type, COUNT(id) AS total FROM centres_analyse WHERE actif = 1
        ");
        $centres = $stmt3->fetchAll();

        $this->ok([
            'densiteVilles'     => array_map(fn($r) => [
                'ville'         => $r['ville'],
                'nbMedecins'    => (int) $r['nb_medecins'],
                'nbSpecialites' => (int) $r['nb_specialites'],
            ], $densiteVilles),
            'densitePharmacies' => array_map(fn($r) => [
                'ville'         => $r['ville'],
                'nbPharmacies'  => (int) $r['nb_pharmacies'],
            ], $densitePharmacies),
            'centres'           => array_map(fn($r) => [
                'type'  => $r['type'],
                'total' => (int) $r['total'],
            ], $centres),
        ]);
    }

    // ── GET /api/analytics/specialites ──────────────────────────────────────
    public function specialites(array $params = []): void
    {
        $stmt = $this->pdo->query("
            SELECT
                s.libelle                                    AS specialite,
                COUNT(m.id)                                  AS nb_medecins,
                ROUND(AVG(m.duree_rdv))                     AS duree_rdv_moy,
                COALESCE(NULLIF(TRIM(u.ville), ''), 'Inconnu') AS ville
            FROM medecins m
            JOIN utilisateurs u ON u.id = m.utilisateur_id
            JOIN specialite s   ON s.id = m.specialisation
            WHERE u.statut = 'actif'
            GROUP BY s.id, s.libelle, ville
            ORDER BY nb_medecins DESC
        ");
        $rows = $stmt->fetchAll();

        $data = array_map(fn($r) => [
            'specialite'   => $r['specialite'],
            'nbMedecins'   => (int)   $r['nb_medecins'],
            'dureeRdvMoy'  => (int)   $r['duree_rdv_moy'],
            'ville'        => $r['ville'],
        ], $rows);

        $this->ok($data, total: count($data));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function ok(mixed $data, int $code = 200, ?int $total = null): never
    {
        http_response_code($code);
        $body = ['data' => $data];
        if ($total !== null) $body['total'] = $total;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}