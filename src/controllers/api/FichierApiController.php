<?php
namespace controllers\api;

use services\JwtService;
use services\MongoLogService;

/**
 * FichierApiController — Upload de fichiers avec validation MIME
 *
 * POST /api/fichiers/upload
 *
 * Multipart/form-data :
 *   fichier  : le fichier binaire
 *   contexte : 'photo_profil_medecin' | 'justificatif_demande' | 'ordonnance'
 *
 * Réponse :
 *   { cheminServeur, nom, type, taille }
 *
 * Validation : finfo (type réel du fichier, pas $_FILES['type'] forgeable).
 * Métadonnées : stockées dans MongoDB → fichiers_metadata
 * Fallback    : si MongoDB absent, les métadonnées sont loggées en fichier.
 */
class FichierApiController
{
    private const CONTEXTES_AUTORISES = [
        'photo_profil_medecin' => [
            'mimes'        => ['image/jpeg', 'image/png', 'image/webp'],
            'extensions'   => ['jpg', 'jpeg', 'png', 'webp'],
            'tailleMaxOctet' => 5 * 1024 * 1024, // 5 Mo
        ],
        'justificatif_demande' => [
            'mimes'          => ['image/jpeg', 'image/png', 'application/pdf'],
            'extensions'     => ['jpg', 'jpeg', 'png', 'pdf'],
            'tailleMaxOctet' => 10 * 1024 * 1024, // 10 Mo
        ],
        'ordonnance' => [
            'mimes'          => ['image/jpeg', 'image/png', 'application/pdf'],
            'extensions'     => ['jpg', 'jpeg', 'png', 'pdf'],
            'tailleMaxOctet' => 10 * 1024 * 1024, // 10 Mo
        ],
    ];

    private JwtService      $jwtService;
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->jwtService = new JwtService();
        $this->mongoLog   = new MongoLogService();
    }

    // ── POST /api/fichiers/upload ─────────────────────────────────────────────
    public function upload(array $params = []): void
    {
        $payload = $this->exigerAuth();
        $userId  = (int) $payload->sub;

        // Vérifier présence du fichier
        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            $this->erreur($this->messageErreurUpload($_FILES['fichier']['error'] ?? -1), 400);
        }

        $contexte = trim($_POST['contexte'] ?? '');
        if (!array_key_exists($contexte, self::CONTEXTES_AUTORISES)) {
            $this->erreur('contexte invalide — valeurs : ' . implode(', ', array_keys(self::CONTEXTES_AUTORISES)), 422);
        }

        $config  = self::CONTEXTES_AUTORISES[$contexte];
        $tmpPath = $_FILES['fichier']['tmp_name'];
        $nomOriginal = basename($_FILES['fichier']['name']);
        $taille  = (int) $_FILES['fichier']['size'];

        // Taille
        if ($taille > $config['tailleMaxOctet']) {
            $max = number_format($config['tailleMaxOctet'] / 1024 / 1024, 0) . ' Mo';
            $this->erreur("Fichier trop volumineux — maximum : $max", 413);
        }

        // MIME réel via finfo (résistant aux fichiers renommés)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReel = $finfo->file($tmpPath);

        if (!in_array($mimeReel, $config['mimes'], true)) {
            $this->erreur(
                'Type de fichier non autorisé pour ce contexte — types acceptés : ' . implode(', ', $config['mimes']),
                415
            );
        }

        // Extension à partir du MIME réel (jamais depuis le nom d'origine)
        $ext = $this->mimeVersExtension($mimeReel);

        // Nom unique : évite les collisions et les caractères dangereux dans le chemin
        $nomFichier   = sprintf('%s_%s.%s', $contexte, bin2hex(random_bytes(8)), $ext);
        $dossierDest  = ROOT . '/public/uploads/' . $contexte;

        if (!is_dir($dossierDest) && !@mkdir($dossierDest, 0755, true)) {
            $this->erreur('Impossible de créer le dossier de destination', 500);
        }

        $cheminAbsolu = $dossierDest . '/' . $nomFichier;
        $cheminPublic = '/uploads/' . $contexte . '/' . $nomFichier;

        if (!move_uploaded_file($tmpPath, $cheminAbsolu)) {
            $this->erreur('Échec du déplacement du fichier uploadé', 500);
        }

        // Métadonnées MongoDB → fichiers_metadata
        $this->mongoLog->sauvegarderFichierMetadata([
            'nom'           => $nomOriginal,
            'type'          => $mimeReel,
            'taille'        => $taille,
            'userId'        => $userId,
            'contexte'      => $contexte,
            'cheminServeur' => $cheminPublic,
        ]);

        $this->ok([
            'cheminServeur' => $cheminPublic,
            'nom'           => $nomOriginal,
            'type'          => $mimeReel,
            'taille'        => $taille,
        ], 201, 'Fichier uploadé avec succès');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function exigerAuth(): object
    {
        $token = JwtService::extraireToken();
        if ($token === null) $this->erreur('Token manquant', 401);

        try {
            $payload = $this->jwtService->verifier($token);
        } catch (\RuntimeException $e) {
            $this->erreur($e->getMessage(), 401);
        }

        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $payload->sub]);
        if ($stmt->fetchColumn() !== 'actif') $this->erreur('Compte inactif ou suspendu', 403);

        return $payload;
    }

    private function mimeVersExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/webp'       => 'webp',
            'application/pdf'  => 'pdf',
            default            => 'bin',
        };
    }

    private function messageErreurUpload(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux',
            UPLOAD_ERR_PARTIAL  => 'Upload incomplet — réessayez',
            UPLOAD_ERR_NO_FILE  => 'Aucun fichier reçu',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque',
            default             => 'Erreur d\'upload inconnue',
        };
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