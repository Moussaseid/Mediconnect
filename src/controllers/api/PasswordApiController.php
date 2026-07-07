<?php
namespace controllers\api;

use models\AdminModel;
use services\MongoLogService;

/**
 * PasswordApiController — Réinitialisation de mot de passe (patient uniquement)
 *
 * POST /api/auth/forgot → génère un token de reset (1h), le retourne dans data.token
 *                         (en prod : enverrait un email)
 * POST /api/auth/reset  → applique le nouveau mot de passe via le token
 */
class PasswordApiController
{
    private AdminModel      $adminModel;
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->adminModel = new AdminModel($pdo);
        $this->mongoLog   = new MongoLogService();
    }

    // ── POST /api/auth/forgot ────────────────────────────────────────────────
    public function forgot(array $params = []): void
    {
        $body  = $this->lireCorps();
        $email = trim($body['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->erreur('Adresse email invalide', 400);
        }

        $token = bin2hex(random_bytes(32)); // 64 chars hex
        $ok    = $this->adminModel->creerTokenReset($email, $token);

        $this->mongoLog->log('reset_demande', null, $email, null, $ok ? 'succes' : 'echec');

        // Réponse identique que l'email existe ou non (sécurité — énumération)
        // En dev : on retourne le token pour pouvoir tester sans serveur SMTP
        $data = ['message' => 'Si cet email est associé à un compte, un lien de réinitialisation a été envoyé.'];
        if ($ok && ($_ENV['APP_ENV'] ?? 'dev') !== 'production') {
            $data['token'] = $token; // Uniquement en développement
        }

        $this->ok($data, 200);
    }

    // ── POST /api/auth/reset ─────────────────────────────────────────────────
    public function reset(array $params = []): void
    {
        $body     = $this->lireCorps();
        $token    = trim($body['token']    ?? '');
        $password = $body['password'] ?? '';

        if (strlen($token) < 10) $this->erreur('Token manquant ou invalide', 400);
        if (strlen($password) < 8) $this->erreur('Le mot de passe doit contenir au moins 8 caractères', 400);

        $ok = $this->adminModel->reinitialiserMotDePasse($token, $password);

        if (!$ok) {
            $this->mongoLog->log('reset_echec', null, null, null, 'echec');
            $this->erreur('Token invalide ou expiré', 400);
        }

        $this->mongoLog->log('reset_succes', null, null, null, 'succes');
        $this->ok(null, 200, 'Mot de passe réinitialisé avec succès');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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
        if ($message) $body['message'] = $message;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(string $msg, int $code = 400, ?string $details = null): never
    {
        http_response_code($code);
        $body = ['error' => $msg, 'code' => $code];
        if ($details) $body['details'] = $details;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
