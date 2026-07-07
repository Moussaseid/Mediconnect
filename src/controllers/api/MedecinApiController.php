<?php
namespace controllers\api;

use services\JwtService;
use services\MongoLogService;

/**
 * MedecinApiController — Endpoint REST recherche médecins
 *
 * GET /api/medecins?lat=&lng=&rayon=&specialiteId=
 *   → liste de médecins actifs avec distance Haversine si lat/lng fournis
 *
 * JWT optionnel : si présent et valide, l'identité est utilisée pour le log.
 */
class MedecinApiController
{
    private const RAYON_MIN    = 5;
    private const RAYON_MAX    = 100;
    private const RAYON_DEFAUT = 10;

    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->mongoLog = new MongoLogService();
    }

    // ── GET /api/medecins ────────────────────────────────────────────────────
    public function liste(array $params = []): void
    {
        // ── Paramètres de recherche ──────────────────────────────────────────
        $lat         = isset($_GET['lat'])         ? (float) $_GET['lat']         : null;
        $lng         = isset($_GET['lng'])         ? (float) $_GET['lng']         : null;
        $rayon       = (int) ($_GET['rayon'] ?? self::RAYON_DEFAUT);
        $specialiteId = isset($_GET['specialiteId']) ? (int) $_GET['specialiteId'] : null;
        $ville       = isset($_GET['ville']) && trim($_GET['ville']) !== '' ? trim($_GET['ville']) : null;

        if ($rayon < self::RAYON_MIN || $rayon > self::RAYON_MAX) {
            $this->erreur('Rayon invalide. Doit être compris entre ' . self::RAYON_MIN . ' et ' . self::RAYON_MAX . ' km', 400);
        }

        // ── JWT optionnel (pour log) ──────────────────────────────────────────
        $userId = null;
        $token  = JwtService::extraireToken();
        if ($token !== null) {
            try {
                $payload = (new JwtService())->verifier($token);
                $userId  = (int) $payload->sub;
            } catch (\RuntimeException) {
                // Token invalide ignoré — la recherche reste accessible
            }
        }

        // ── Construction de la requête SQL ────────────────────────────────────
        $colonnesBase = "
                        u.id              AS utilisateur_id,
                        m.id              AS id,
                        u.nom,
                        u.prenom,
                        u.email,
                        u.telephone,
                        m.specialisation,
                        s.libelle         AS specialisationLibelle,
                        m.numero_rpps     AS numeroRpps,
                        m.adresse_cabinet AS adresseCabinet,
                        m.latitude,
                        m.longitude,
                        m.duree_rdv       AS dureeRdv,
                        m.valide_le       AS valideLe";

        $joinBase = "FROM medecins m
                    JOIN utilisateurs u ON u.id = m.utilisateur_id
                    LEFT JOIN specialite s ON s.id = m.specialisation
                    WHERE u.statut = 'actif'";

        if ($lat !== null && $lng !== null) {
            $sql = "SELECT $colonnesBase,
                        (6371 * ACOS(
                            COS(RADIANS(:lat1)) * COS(RADIANS(m.latitude))
                            * COS(RADIANS(m.longitude) - RADIANS(:lng))
                            + SIN(RADIANS(:lat2)) * SIN(RADIANS(m.latitude))
                        )) AS distance
                    $joinBase
                      AND m.latitude  IS NOT NULL
                      AND m.longitude IS NOT NULL";

            $binds = [':lat1' => $lat, ':lat2' => $lat, ':lng' => $lng];

            if ($specialiteId !== null) {
                $sql .= ' AND m.specialisation = :specialiteId';
                $binds[':specialiteId'] = $specialiteId;
            }
            if ($ville !== null) {
                $sql .= ' AND u.ville LIKE :ville';
                $binds[':ville'] = '%' . $ville . '%';
            }

            $sql .= ' HAVING distance <= :rayon ORDER BY distance ASC';
            $binds[':rayon'] = $rayon;

        } else {
            // Pas de géolocalisation — liste simple sans distance
            $sql = "SELECT $colonnesBase,
                        NULL AS distance
                    $joinBase";

            $binds = [];

            if ($specialiteId !== null) {
                $sql .= ' AND m.specialisation = :specialiteId';
                $binds[':specialiteId'] = $specialiteId;
            }
            if ($ville !== null) {
                $sql .= ' AND u.ville LIKE :ville';
                $binds[':ville'] = '%' . $ville . '%';
            }

            $sql .= ' ORDER BY u.nom ASC';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);
        $rows = $stmt->fetchAll();

        // ── Formatage de la réponse ───────────────────────────────────────────
        $medecins = array_map(fn(array $r) => [
            'id'                    => (int)   $r['id'],
            'utilisateurId'         => (int)   $r['utilisateur_id'],
            'nom'                   => $r['nom'],
            'prenom'                => $r['prenom'],
            'email'                 => $r['email'],
            'telephone'             => $r['telephone'],
            'specialisation'        => (int)   $r['specialisation'],
            'specialisationLibelle' => $r['specialisationLibelle'],
            'numeroRpps'            => $r['numeroRpps'],
            'adresseCabinet'        => $r['adresseCabinet'],
            'latitude'              => $r['latitude']  !== null ? (float) $r['latitude']  : null,
            'longitude'             => $r['longitude'] !== null ? (float) $r['longitude'] : null,
            'dureeRdv'              => (int) $r['dureeRdv'],
            'valideLe'              => $r['valideLe'],
            'distance'              => $r['distance'] !== null ? round((float) $r['distance'], 2) : null,
        ], $rows);

        // ── Log MongoDB ───────────────────────────────────────────────────────
        $this->mongoLog->logRecherche(
            userId     : $userId,
            terme      : $specialiteId !== null ? (string) $specialiteId : null,
            rayon      : $rayon,
            lat        : $lat,
            lng        : $lng,
            nbResultats: count($medecins)
        );

        $this->ok($medecins, total: count($medecins));
    }

    // ── GET /api/medecins/{id} ───────────────────────────────────────────────
    public function detail(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        $selectBase = "
            SELECT u.id              AS utilisateur_id,
                   m.id              AS id,
                   u.nom,
                   u.prenom,
                   u.email,
                   u.telephone,
                   m.specialisation,
                   s.libelle         AS specialisationLibelle,
                   m.numero_rpps     AS numeroRpps,
                   m.adresse_cabinet AS adresseCabinet,
                   m.latitude,
                   m.longitude,
                   m.duree_rdv       AS dureeRdv,
                   m.valide_le       AS valideLe";

        $fromBase = "
            FROM medecins m
            JOIN utilisateurs u ON u.id = m.utilisateur_id
            LEFT JOIN specialite s ON s.id = m.specialisation
            WHERE m.id = :id AND u.statut = 'actif'
            LIMIT 1";

        $stmt = $this->pdo->prepare($selectBase . ", COALESCE(m.photo_path, u.photo_path) AS photoPath" . $fromBase);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->erreur('Médecin introuvable', 404);
        }

        // Horaires hebdomadaires
        $stmtH = $this->pdo->prepare("
            SELECT id, jour_semaine AS jourSemaine, heure_debut AS heureDebut, heure_fin AS heureFin
            FROM horaires_semaine
            WHERE medecin_id = :id
            ORDER BY jour_semaine ASC
        ");
        $stmtH->execute([':id' => $id]);
        $horaires = array_map(fn(array $h) => [
            'id'          => (int) $h['id'],
            'medecinId'   => $id,
            'jourSemaine' => (int) $h['jourSemaine'],
            'heureDebut'  => $h['heureDebut'],
            'heureFin'    => $h['heureFin'],
        ], $stmtH->fetchAll());

        $medecin = [
            'id'                    => (int)  $row['id'],
            'utilisateurId'         => (int)  $row['utilisateur_id'],
            'nom'                   => $row['nom'],
            'prenom'                => $row['prenom'],
            'email'                 => $row['email'],
            'telephone'             => $row['telephone'],
            'specialisation'        => (int)  $row['specialisation'],
            'specialisationLibelle' => $row['specialisationLibelle'],
            'numeroRpps'            => $row['numeroRpps'],
            'adresseCabinet'        => $row['adresseCabinet'],
            'latitude'              => $row['latitude']  !== null ? (float) $row['latitude']  : null,
            'longitude'             => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'dureeRdv'              => (int) $row['dureeRdv'],
            'valideLe'              => $row['valideLe'],
            'photoPath'             => $row['photoPath'],
            'horaires'              => $horaires,
        ];

        $this->ok($medecin);
    }

    // ── PUT /api/medecins/{id} ───────────────────────────────────────────────
    // Note : la spécialité n'est pas modifiable par le médecin (fixée à la validation
    // de son compte). Les coordonnées géographiques sont dérivées de l'adresse côté
    // frontend (API Adresse/BAN) et transmises ici telles quelles, pas saisies à la main.
    public function mettreAJour(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        [$callerId, $callerRole] = $this->authentifier();
        $medRow = $this->verifierProprietaire($id, $callerId, $callerRole);

        // Corps JSON
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $champs  = [];
        $binds   = [':id' => $id];
        $mapping = [
            'adresseCabinet' => 'adresse_cabinet',
            'dureeRdv'       => 'duree_rdv',
            'latitude'       => 'latitude',
            'longitude'      => 'longitude',
        ];

        foreach ($mapping as $jsKey => $sqlCol) {
            if (!array_key_exists($jsKey, $body)) continue;
            $val = $body[$jsKey];
            // Validation basique
            if ($jsKey === 'dureeRdv' && $val !== null && (int) $val < 5) continue;
            $champs[] = "$sqlCol = :$jsKey";
            $binds[":$jsKey"] = $val;
        }

        // Mise à jour du téléphone dans utilisateurs
        if (array_key_exists('telephone', $body)) {
            $stmtTel = $this->pdo->prepare('UPDATE utilisateurs SET telephone = :tel WHERE id = :uid');
            $stmtTel->execute([':tel' => $body['telephone'], ':uid' => $medRow['utilisateur_id']]);
        }

        if (!empty($champs)) {
            $sql  = 'UPDATE medecins SET ' . implode(', ', $champs) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($binds);
        }

        // Renvoyer la fiche à jour
        $this->detail($params);
    }

    // ── PUT /api/medecins/{id}/horaires ──────────────────────────────────────
    public function mettreAJourHoraires(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        [$callerId, $callerRole] = $this->authentifier();
        $this->verifierProprietaire($id, $callerId, $callerRole);

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $horaires = is_array($body['horaires'] ?? null) ? $body['horaires'] : [];

        foreach ($horaires as $h) {
            $jour = (int) ($h['jourSemaine'] ?? 0);
            if ($jour < 1 || $jour > 7) {
                $this->erreur('Jour de semaine invalide (1 à 7)', 400);
            }
            if (empty($h['heureDebut']) || empty($h['heureFin'])) {
                $this->erreur('Heures de début et de fin requises pour chaque jour', 400);
            }
            if ($h['heureDebut'] >= $h['heureFin']) {
                $this->erreur('L\'heure de début doit précéder l\'heure de fin', 400);
            }
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM horaires_semaine WHERE medecin_id = :id');
            $del->execute([':id' => $id]);

            $ins = $this->pdo->prepare(
                'INSERT INTO horaires_semaine (medecin_id, jour_semaine, heure_debut, heure_fin)
                 VALUES (:id, :jour, :debut, :fin)'
            );
            foreach ($horaires as $h) {
                $ins->execute([
                    ':id'    => $id,
                    ':jour'  => (int) $h['jourSemaine'],
                    ':debut' => $h['heureDebut'],
                    ':fin'   => $h['heureFin'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Exception) {
            $this->pdo->rollBack();
            $this->erreur('Erreur lors de la mise à jour des horaires', 500);
        }

        $this->detail(['id' => (string) $id]);
    }

    // ── GET /api/medecins/{id}/creneaux ───────────────────────────────────────
    // Calcule les créneaux disponibles à partir des horaires hebdomadaires, des
    // indisponibilités, de la durée de consultation et des rendez-vous déjà pris.
    public function creneaux(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        $jours = isset($_GET['jours']) ? (int) $_GET['jours'] : 14;
        if ($jours < 1 || $jours > 60) {
            $jours = 14;
        }

        $stmtM = $this->pdo->prepare(
            "SELECT m.duree_rdv FROM medecins m
             JOIN utilisateurs u ON u.id = m.utilisateur_id
             WHERE m.id = :id AND u.statut = 'actif' LIMIT 1"
        );
        $stmtM->execute([':id' => $id]);
        $med = $stmtM->fetch();
        if (!$med) {
            $this->erreur('Médecin introuvable', 404);
        }
        $duree = max(5, (int) $med['duree_rdv']);

        $debut = new \DateTimeImmutable('today');
        $fin   = $debut->modify("+{$jours} days");

        $stmtH = $this->pdo->prepare('SELECT jour_semaine, heure_debut, heure_fin FROM horaires_semaine WHERE medecin_id = :id');
        $stmtH->execute([':id' => $id]);
        $horairesParJour = [];
        foreach ($stmtH->fetchAll() as $h) {
            $horairesParJour[(int) $h['jour_semaine']][] = $h;
        }

        $stmtI = $this->pdo->prepare(
            'SELECT date_debut, date_fin FROM indisponibilite
             WHERE medecin_id = :id AND date_fin >= :debut AND date_debut <= :fin'
        );
        $stmtI->execute([':id' => $id, ':debut' => $debut->format('Y-m-d H:i:s'), ':fin' => $fin->format('Y-m-d H:i:s')]);
        $indisponibilites = array_map(fn($r) => [
            new \DateTimeImmutable($r['date_debut']),
            new \DateTimeImmutable($r['date_fin']),
        ], $stmtI->fetchAll());

        $stmtR = $this->pdo->prepare(
            "SELECT date_heure FROM rendez_vous
             WHERE medecin_id = :id AND statut IN ('en_attente', 'confirme')
               AND date_heure >= :debut AND date_heure < :fin"
        );
        $stmtR->execute([':id' => $id, ':debut' => $debut->format('Y-m-d H:i:s'), ':fin' => $fin->format('Y-m-d H:i:s')]);
        $occupes = array_map(fn($r) => new \DateTimeImmutable($r['date_heure']), $stmtR->fetchAll());

        $maintenant = new \DateTimeImmutable();
        $resultat   = [];

        for ($offset = 0; $offset < $jours; $offset++) {
            $jourCourant = $debut->modify("+{$offset} days");
            $isoJour     = (int) $jourCourant->format('N'); // 1=lundi ... 7=dimanche
            if (empty($horairesParJour[$isoJour])) {
                continue;
            }

            $creneauxJour = [];
            foreach ($horairesParJour[$isoJour] as $h) {
                [$hd, $md] = explode(':', $h['heure_debut']);
                [$hf, $mf] = explode(':', $h['heure_fin']);
                $slotDebut = $jourCourant->setTime((int) $hd, (int) $md);
                $plageFin  = $jourCourant->setTime((int) $hf, (int) $mf);

                while (($slotFin = $slotDebut->modify("+{$duree} minutes")) <= $plageFin) {
                    $libre = $slotDebut > $maintenant;

                    if ($libre) {
                        foreach ($indisponibilites as [$iDebut, $iFin]) {
                            if ($slotDebut < $iFin && $slotFin > $iDebut) {
                                $libre = false;
                                break;
                            }
                        }
                    }
                    if ($libre) {
                        foreach ($occupes as $o) {
                            $oFin = $o->modify("+{$duree} minutes");
                            if ($slotDebut < $oFin && $slotFin > $o) {
                                $libre = false;
                                break;
                            }
                        }
                    }
                    if ($libre) {
                        $creneauxJour[] = $slotDebut->format('H:i');
                    }

                    $slotDebut = $slotFin;
                }
            }

            if (!empty($creneauxJour)) {
                $resultat[] = ['date' => $jourCourant->format('Y-m-d'), 'creneaux' => $creneauxJour];
            }
        }

        $this->ok($resultat);
    }

    // ── GET /api/medecins/{id}/indisponibilites ───────────────────────────────
    public function listerIndisponibilites(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, medecin_id AS medecinId, date_debut AS dateDebut, date_fin AS dateFin,
                    motif, created_at AS createdAt
             FROM indisponibilite WHERE medecin_id = :id ORDER BY date_debut DESC'
        );
        $stmt->execute([':id' => $id]);

        $data = array_map(fn(array $r) => [
            'id'        => (int) $r['id'],
            'medecinId' => (int) $r['medecinId'],
            'dateDebut' => $r['dateDebut'],
            'dateFin'   => $r['dateFin'],
            'motif'     => $r['motif'],
            'createdAt' => $r['createdAt'],
        ], $stmt->fetchAll());

        $this->ok($data, total: count($data));
    }

    // ── POST /api/medecins/{id}/indisponibilites ──────────────────────────────
    public function creerIndisponibilite(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        [$callerId, $callerRole] = $this->authentifier();
        $this->verifierProprietaire($id, $callerId, $callerRole);

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $dateDebut = $body['dateDebut'] ?? '';
        $dateFin   = $body['dateFin']   ?? '';
        $motif     = trim((string) ($body['motif'] ?? '')) ?: null;

        if (!$dateDebut || !$dateFin || strtotime($dateDebut) === false || strtotime($dateFin) === false) {
            $this->erreur('Dates de début et de fin requises et valides', 400);
        }
        if (strtotime($dateDebut) >= strtotime($dateFin)) {
            $this->erreur('La date de début doit précéder la date de fin', 400);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO indisponibilite (medecin_id, date_debut, date_fin, motif)
             VALUES (:id, :debut, :fin, :motif)'
        );
        $stmt->execute([':id' => $id, ':debut' => $dateDebut, ':fin' => $dateFin, ':motif' => $motif]);

        $this->listerIndisponibilites(['id' => (string) $id]);
    }

    // ── DELETE /api/medecins/{id}/indisponibilites/{indispoId} ───────────────
    public function supprimerIndisponibilite(array $params = []): void
    {
        $id        = (int) ($params['id'] ?? 0);
        $indispoId = (int) ($params['indispoId'] ?? 0);
        if ($id <= 0 || $indispoId <= 0) {
            $this->erreur('Identifiant invalide', 400);
        }

        [$callerId, $callerRole] = $this->authentifier();
        $this->verifierProprietaire($id, $callerId, $callerRole);

        $stmt = $this->pdo->prepare('DELETE FROM indisponibilite WHERE id = :indispoId AND medecin_id = :id');
        $stmt->execute([':indispoId' => $indispoId, ':id' => $id]);

        $this->listerIndisponibilites(['id' => (string) $id]);
    }

    // ── GET /api/medecins/me ─────────────────────────────────────────────────
    public function moi(array $params = []): void
    {
        $token = JwtService::extraireToken();
        if ($token === null) {
            $this->erreur('Authentification requise', 401);
        }
        try {
            $payload = (new JwtService())->verifier($token);
        } catch (\RuntimeException) {
            $this->erreur('Token invalide', 401);
        }

        if (($payload->role ?? '') !== 'medecin') {
            $this->erreur('Non autorisé', 403);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM medecins WHERE utilisateur_id = :uid LIMIT 1');
        $stmt->execute([':uid' => (int) $payload->sub]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->erreur('Profil médecin introuvable', 404);
        }

        $this->detail(['id' => (string) $row['id']]);
    }

    // ── GET /api/specialites ─────────────────────────────────────────────────
    public function specialites(array $params = []): void
    {
        $stmt = $this->pdo->query('SELECT id, libelle FROM specialite ORDER BY libelle ASC');
        $rows = $stmt->fetchAll();

        $data = array_map(fn(array $r) => [
            'id'      => (int) $r['id'],
            'libelle' => $r['libelle'],
        ], $rows);

        $this->ok($data, total: count($data));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vérifie le JWT et retourne [callerId, callerRole].
     */
    private function authentifier(): array
    {
        $token = JwtService::extraireToken();
        if ($token === null) {
            $this->erreur('Authentification requise', 401);
        }
        try {
            $payload = (new JwtService())->verifier($token);
        } catch (\RuntimeException) {
            $this->erreur('Token invalide', 401);
        }

        return [(int) $payload->sub, (string) ($payload->role ?? '')];
    }

    /**
     * Vérifie que l'appelant est bien le médecin lui-même ou un admin.
     * Retourne la ligne `medecins` (utilisateur_id) si autorisé.
     */
    private function verifierProprietaire(int $medecinId, int $callerId, string $callerRole): array
    {
        $stmt = $this->pdo->prepare('SELECT utilisateur_id FROM medecins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $medecinId]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->erreur('Médecin introuvable', 404);
        }
        if ($callerRole !== 'admin' && (int) $row['utilisateur_id'] !== $callerId) {
            $this->erreur('Non autorisé', 403);
        }
        return $row;
    }

    private function ok(mixed $data, int $code = 200, ?string $message = null, ?int $total = null): never
    {
        http_response_code($code);
        $body = ['data' => $data];
        if ($message !== null) $body['message'] = $message;
        if ($total   !== null) $body['total']   = $total;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(string $msg, int $code = 400): never
    {
        http_response_code($code);
        echo json_encode(['error' => $msg, 'code' => $code], JSON_UNESCAPED_UNICODE);
        exit;
    }
}