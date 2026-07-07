<?php
namespace controllers;

abstract class BaseController
{
    /**
     * Rend une vue en l'englobant dans le layout principal.
     * Les clés de $data sont extraites comme variables locales dans la vue.
     *
     * @param string $view  Chemin relatif à src/views/ (ex: 'patient/inscription')
     * @param array  $data  Variables transmises à la vue
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = ROOT . '/src/views/' . $view . '.php';
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require ROOT . '/src/views/layouts/main.php';
    }

    /**
     * Redirige vers une URL et stoppe l'exécution.
     */
    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Stocke un message flash en session.
     * Consommé une seule fois par getFlash().
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Récupère et efface le message flash courant.
     * Retourne null si aucun message en attente.
     *
     * @return array{type: string, message: string}|null
     */
    public static function getFlash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Génère un token CSRF et le stocke en session.
     * Retourne le token pour injection dans les vues.
     */
    protected function genererTokenCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie le token CSRF soumis via POST.
     * Arrête l'exécution et renvoie 403 si invalide.
     */
    protected function verifierTokenCsrf(): void
    {
        $tokenSoumis  = $_POST['csrf_token'] ?? '';
        $tokenAttendu = $_SESSION['csrf_token'] ?? '';

        if (empty($tokenAttendu) || !hash_equals($tokenAttendu, $tokenSoumis)) {
            http_response_code(403);
            $this->flash('error', 'Requête invalide. Veuillez réessayer.');
            $this->redirect('/connexion');
        }

        // Rotation du token après vérification réussie
        unset($_SESSION['csrf_token']);
    }

    /**
     * Vérifie que l'utilisateur est connecté avec l'un des rôles autorisés.
     * Re-vérifie le statut du compte en base à chaque requête protégée.
     * Redirige vers /connexion si la vérification échoue.
     */
    protected function requireRole(string ...$roles): void
    {
        if (!isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], $roles, true)) {
            $this->flash('error', 'Accès non autorisé.');
            http_response_code(403);
            $this->redirect('/connexion');
        }

        // Re-vérification du statut en base (prévient l'accès post-suspension)
        $stmt = \Database::getConnection()->prepare(
            'SELECT statut FROM utilisateurs WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $_SESSION['user']['id']]);
        $statut = $stmt->fetchColumn();

        if ($statut !== 'actif') {
            session_unset();
            session_destroy();
            $this->flash('error', 'Votre compte a été suspendu. Contactez l\'administrateur.');
            http_response_code(403);
            $this->redirect('/connexion');
        }
    }
}
