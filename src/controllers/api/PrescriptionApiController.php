<?php
namespace controllers\api;

use models\PrescriptionModel;
use services\JwtService;

/**
 * PrescriptionApiController — Ordonnances du patient
 *
 * GET /api/prescriptions      → liste des ordonnances du patient connecté (avec lignes)
 * GET /api/prescriptions/:id  → détail d'une ordonnance
 */
class PrescriptionApiController
{
    private PrescriptionModel $prescriptionModel;
    private JwtService        $jwtService;

    public function __construct(private \PDO $pdo)
    {
        $this->prescriptionModel = new PrescriptionModel($pdo);
        $this->jwtService        = new JwtService();
    }

    // ── GET /api/prescriptions ────────────────────────────────────────────────
    public function liste(array $params = []): void
    {
        $payload       = $this->exigerAuth('patient');
        $prescriptions = $this->prescriptionModel->listerParPatient((int) $payload->sub);
        $this->ok(array_map(fn($p) => $this->format($p), $prescriptions));
    }

    // ── GET /api/prescriptions/:id ────────────────────────────────────────────
    public function detail(array $params = []): void
    {
        $payload      = $this->exigerAuth('patient');
        $id           = (int) ($params['id'] ?? 0);
        $prescription = $this->prescriptionModel->findById($id);

        if ($prescription === null) $this->erreur('Ordonnance introuvable', 404);

        // Sécurité : un patient ne peut voir que ses propres ordonnances
        if ((int) $prescription['patient_id'] !== (int) $payload->sub) {
            $this->erreur('Accès non autorisé', 403);
        }

        $this->ok($this->format($prescription));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function format(array $p): array
    {
        return [
            'id'               => (int)  $p['id'],
            'patientId'        => (int)  $p['patient_id'],
            'medecinId'        => (int)  $p['medecin_id'],
            'rdvId'            => isset($p['rdv_id']) ? (int) $p['rdv_id'] : null,
            'datePrescription' =>        $p['date_prescription'],
            'validiteJours'    => (int)  $p['validite_jours'],
            'createdAt'        =>        $p['created_at'],
            'medecin'          => [
                'id'             => (int) $p['medecin_id'],
                'nom'            =>       $p['medecin_nom']            ?? null,
                'prenom'         =>       $p['medecin_prenom']         ?? null,
                'specialisation' =>       $p['medecin_specialisation'] ?? null,
            ],
            'lignes' => array_map(fn($l) => [
                'id'             => (int)   $l['id'],
                'prescriptionId' => (int)   $l['prescription_id'],
                'medicamentId'   => (int)   $l['medicament_id'],
                'posologie'      =>          $l['posologie'],
                'dureeJours'     => isset($l['duree_jours']) ? (int) $l['duree_jours'] : null,
                'quantite'       => (int)   $l['quantite'],
                'medicament'     => [
                    'id'    => (int) $l['medicament_id'],
                    'nom'   =>       $l['medicament_nom']   ?? null,
                    'forme' =>       $l['medicament_forme']  ?? null,
                    'dosage'=>       $l['medicament_dosage'] ?? null,
                ],
            ], $p['lignes'] ?? []),
        ];
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
