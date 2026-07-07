<?php
namespace controllers\api;

use models\RdvModel;
use services\JwtService;

/**
 * RdvApiController — Gestion des rendez-vous (espace patient)
 *
 * GET  /api/rdv                      → liste des RDV du patient connecté
 * GET  /api/rdv/medecins             → liste des médecins (pour formulaire)
 * GET  /api/rdv/creneaux             → créneaux dispo (?medecinId=X&date=YYYY-MM-DD)
 * POST /api/rdv                      → créer un RDV
 * PUT  /api/rdv/:id                  → annuler { motif }
 */
class RdvApiController
{
    private RdvModel   $rdvModel;
    private JwtService $jwtService;

    public function __construct(private \PDO $pdo)
    {
        $this->rdvModel   = new RdvModel($pdo);
        $this->jwtService = new JwtService();
    }

    // ── GET /api/rdv ──────────────────────────────────────────────────────────
    public function liste(array $params = []): void
    {
        $payload = $this->exigerAuth('patient');
        $rdvs    = $this->rdvModel->listerParPatient((int) $payload->sub);
        $this->ok(array_map(fn($r) => $this->formatRdv($r), $rdvs));
    }

    // ── GET /api/rdv/medecins ─────────────────────────────────────────────────
    public function medecins(array $params = []): void
    {
        $this->exigerAuth('patient');
        $liste = $this->rdvModel->listerMedecins();
        $this->ok(array_map(fn($m) => $this->formatMedecin($m), $liste));
    }

    // ── GET /api/rdv/creneaux ─────────────────────────────────────────────────
    public function creneaux(array $params = []): void
    {
        $this->exigerAuth('patient');

        $medecinId = (int) ($_GET['medecinId'] ?? 0);
        $date      = $_GET['date'] ?? '';

        if ($medecinId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->erreur('medecinId et date (YYYY-MM-DD) sont requis', 422);
        }
        if (strtotime($date) < strtotime('today')) {
            $this->erreur('La date doit être aujourd\'hui ou dans le futur', 422);
        }

        $this->ok($this->rdvModel->creneauxDisponibles($medecinId, $date));
    }

    // ── POST /api/rdv ─────────────────────────────────────────────────────────
    public function creer(array $params = []): void
    {
        $payload = $this->exigerAuth('patient');
        $data    = $this->lireCorps();

        $medecinId = (int) ($data['medecinId'] ?? 0);
        $dateHeure = trim($data['dateHeure'] ?? '');

        if ($medecinId <= 0) $this->erreur('medecinId requis', 422);
        if (empty($dateHeure)) $this->erreur('dateHeure requis (YYYY-MM-DD HH:MM)', 422);
        if (strtotime($dateHeure) === false || strtotime($dateHeure) <= time()) {
            $this->erreur('dateHeure doit être dans le futur', 422);
        }

        $id = $this->rdvModel->creer((int) $payload->sub, $medecinId, $dateHeure);
        if ($id === null) {
            $this->erreur('Ce créneau est déjà pris — veuillez en choisir un autre', 409);
        }

        $rdv = $this->rdvModel->findById($id);
        $this->ok($this->formatRdv($rdv), 201, 'Rendez-vous créé');
    }

    // ── GET /api/medecin/mes-rdv ──────────────────────────────────────────────
    public function mesRdvMedecin(array $params = []): void
    {
        $payload   = $this->exigerAuth('medecin');
        $medecinId = $this->idMedecinPourUtilisateur((int) $payload->sub);
        $rdvs      = $this->rdvModel->listerParMedecin($medecinId);
        $this->ok(array_map(fn($r) => $this->formatRdvMedecin($r), $rdvs));
    }

    // ── GET /api/medecin/horaires ─────────────────────────────────────────────
    public function horaires(array $params = []): void
    {
        $payload   = $this->exigerAuth('medecin');
        $medecinId = $this->idMedecinPourUtilisateur((int) $payload->sub);
        $this->ok($this->rdvModel->listerHoraires($medecinId));
    }

    // ── POST /api/medecin/horaires ────────────────────────────────────────────
    public function ajouterHoraire(array $params = []): void
    {
        $payload   = $this->exigerAuth('medecin');
        $medecinId = $this->idMedecinPourUtilisateur((int) $payload->sub);
        $data      = $this->lireCorps();

        $jour  = (int) ($data['jourSemaine'] ?? 0);
        $debut = trim($data['heureDebut'] ?? '');
        $fin   = trim($data['heureFin']   ?? '');

        if ($jour < 1 || $jour > 7)       $this->erreur('jourSemaine doit être entre 1 et 7', 422);
        if (empty($debut) || empty($fin)) $this->erreur('heureDebut et heureFin sont requis', 422);

        $id = $this->rdvModel->ajouterHoraire($medecinId, $jour, $debut, $fin);
        $this->ok(['id' => $id, 'medecinId' => $medecinId, 'jourSemaine' => $jour, 'heureDebut' => $debut, 'heureFin' => $fin], 201, 'Horaire ajouté');
    }

    // ── DELETE /api/medecin/horaires/:id ─────────────────────────────────────
    public function supprimerHoraire(array $params = []): void
    {
        $payload   = $this->exigerAuth('medecin');
        $medecinId = $this->idMedecinPourUtilisateur((int) $payload->sub);
        $id        = (int) ($params['id'] ?? 0);

        if ($id <= 0) $this->erreur('ID invalide', 422);

        $ok = $this->rdvModel->supprimerHoraire($id, $medecinId);
        if (!$ok) $this->erreur('Horaire introuvable', 404);

        $this->ok(null, 200, 'Horaire supprimé');
    }

    // ── PUT /api/rdv/:id (annuler) ────────────────────────────────────────────
    public function annuler(array $params = []): void
    {
        $payload   = $this->exigerAuth('patient');
        $id        = (int) ($params['id'] ?? 0);
        $data      = $this->lireCorps();
        $motif     = trim($data['motif'] ?? '');

        if ($id <= 0)     $this->erreur('ID invalide', 422);
        if (empty($motif)) $this->erreur('motif requis pour annuler un RDV', 422);

        $ok = $this->rdvModel->annuler($id, (int) $payload->sub, $motif);
        if (!$ok) $this->erreur('Rendez-vous introuvable ou déjà annulé', 404);

        $rdv = $this->rdvModel->findById($id);
        $this->ok($this->formatRdv($rdv), 200, 'Rendez-vous annulé');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function idMedecinPourUtilisateur(int $utilisateurId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM medecins WHERE utilisateur_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $utilisateurId]);
        $id = $stmt->fetchColumn();
        if (!$id) $this->erreur('Profil médecin introuvable', 404);
        return (int) $id;
    }

    private function formatRdvMedecin(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'patientId'       => (int) $r['patient_id'],
            'medecinId'       => (int) $r['medecin_id'],
            'dateHeure'       =>       $r['date_heure'],
            'statut'          =>       $r['statut'],
            'annulePar'       =>       $r['annule_par']       ?? null,
            'motifAnnulation' =>       $r['motif_annulation'] ?? null,
            'createdAt'       =>       $r['created_at'],
            'patient'         => [
                'nom'    => $r['patient_nom']    ?? null,
                'prenom' => $r['patient_prenom'] ?? null,
                'email'  => $r['patient_email']  ?? null,
            ],
        ];
    }

    private function exigerAuth(string ...$roles): object
    {
        $token = JwtService::extraireToken();
        if ($token === null) $this->erreur('Token manquant', 401);

        try {
            $payload = $this->jwtService->verifier($token);
        } catch (\RuntimeException $e) {
            $this->erreur($e->getMessage(), 401);
        }

        if (!empty($roles) && !in_array($payload->role ?? '', $roles, true)) {
            $this->erreur('Accès non autorisé pour ce rôle', 403);
        }

        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $payload->sub]);
        if ($stmt->fetchColumn() !== 'actif') $this->erreur('Compte inactif ou suspendu', 403);

        return $payload;
    }

    private function formatRdv(array $r): array
    {
        return [
            'id'               => (int)  $r['id'],
            'patientId'        => (int)  $r['patient_id'],
            'medecinId'        => (int)  $r['medecin_id'],
            'dateHeure'        =>        $r['date_heure'],
            'statut'           =>        $r['statut'],
            'annulePar'        =>        $r['annule_par']        ?? null,
            'motifAnnulation'  =>        $r['motif_annulation']  ?? null,
            'createdAt'        =>        $r['created_at'],
            'medecin'          => [
                'id'             => (int) $r['medecin_id'],
                'nom'            =>       $r['medecin_nom']             ?? null,
                'prenom'         =>       $r['medecin_prenom']          ?? null,
                'specialisation' =>       $r['medecin_specialisation']  ?? null,
                'adresseCabinet' =>       $r['medecin_adresse']         ?? null,
            ],
        ];
    }

    private function formatMedecin(array $m): array
    {
        return [
            'id'             => (int) $m['id'],
            'nom'            =>       $m['nom'],
            'prenom'         =>       $m['prenom'],
            'specialisation' =>       $m['specialisation'],
            'adresseCabinet' =>       $m['adresse_cabinet'] ?? null,
            'dureeRdv'       => (int) ($m['duree_rdv'] ?? 30),
            'telephone'      =>       $m['telephone'] ?? null,
            'email'          =>       $m['email']     ?? null,
        ];
    }

    private function lireCorps(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) $this->erreur('Corps JSON invalide', 400);
        return $data;
    }

    private function ok(mixed $data, int $code = 200, ?string $message = null): never
    {
        http_response_code($code);
        $body = ['data' => $data];
        if ($message !== null) $body['message'] = $message;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(string $msg, int $code = 400, ?string $details = null): never
    {
        http_response_code($code);
        $body = ['error' => $msg, 'code' => $code];
        if ($details !== null) $body['details'] = $details;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
