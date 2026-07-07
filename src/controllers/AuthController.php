<?php
namespace controllers;

use models\AdminModel;
use services\AuthService;

class AuthController extends BaseController
{
    private AuthService $authService;
    private AdminModel  $adminModel;

    public function __construct(\PDO $pdo)
    {
        $this->authService = new AuthService($pdo);
        $this->adminModel  = new AdminModel($pdo);
    }

    // ------------------------------------------------------------------ #7 connexion unifiée
    /**
     * GET  /connexion → formulaire commun (tous rôles)
     * POST /connexion → AuthService::login() → redirect selon rôle
     */
    public function connexion(array $params = []): void
    {
        // Déjà connecté → rediriger directement
        if (isset($_SESSION['user'])) {
            $this->redirect($this->authService->urlParRole($_SESSION['user']['role']));
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $email = trim($_POST['email'] ?? '');
            $mdp   = $_POST['mot_de_passe'] ?? '';

            $user = $this->authService->login($email, $mdp);

            if ($user === null) {
                $error = 'Identifiants incorrects.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                $this->redirect($this->authService->urlParRole($user['role']));
            }
        }

        $this->render('auth/connexion', [
            'pageTitle'  => 'Connexion — MediConnect',
            'error'      => $error,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #7 déconnexion
    public function deconnexion(array $params = []): void
    {
        session_unset();
        session_destroy();
        $this->redirect('/connexion');
    }

    // ------------------------------------------------------------------ #20 mot de passe oublié
    /**
     * GET  /mot-de-passe-oubli → formulaire e-mail
     * POST /mot-de-passe-oubli → génère le token et affiche l'URL de reset
     */
    public function motDePasseOubli(array $params = []): void
    {
        $error      = null;
        $resetUrl   = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $email = trim($_POST['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse e-mail invalide.';
            } else {
                $token = bin2hex(random_bytes(32)); // 64 hex chars
                $ok    = $this->adminModel->creerTokenReset($email, $token);

                if ($ok) {
                    // En production : envoyer par email. Ici, on affiche l'URL directement.
                    $resetUrl = '/reinitialiser-mdp?token=' . urlencode($token);
                } else {
                    // Message neutre pour ne pas divulguer l'existence du compte
                    $resetUrl = '__not_found__';
                }
            }
        }

        $this->render('auth/mot_de_passe_oubli', [
            'pageTitle'  => 'Mot de passe oublié — MediConnect',
            'error'      => $error,
            'resetUrl'   => $resetUrl,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #20 réinitialisation
    /**
     * GET  /reinitialiser-mdp?token=xxx → formulaire nouveau mot de passe
     * POST /reinitialiser-mdp           → applique le nouveau mot de passe
     */
    public function reinitialiserMotDePasse(array $params = []): void
    {
        $token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
        $errors = [];
        $succes = false;

        // Vérifier token avant affichage du formulaire
        if ($token === '' || $this->adminModel->trouverTokenReset($token) === null) {
            $this->render('auth/reinitialiser_mdp', [
                'pageTitle' => 'Lien invalide — MediConnect',
                'tokenInvalide' => true,
                'token'     => '',
                'errors'    => [],
                'succes'    => false,
                'csrf_token'=> $this->genererTokenCsrf(),
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $mdp        = $_POST['mot_de_passe'] ?? '';
            $mdpConfirm = $_POST['mot_de_passe_confirm'] ?? '';

            if (strlen($mdp) < 8)       $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            if ($mdp !== $mdpConfirm)   $errors['mot_de_passe_confirm'] = 'Les mots de passe ne correspondent pas.';

            if (empty($errors)) {
                $ok = $this->adminModel->reinitialiserMotDePasse($token, $mdp);
                if ($ok) {
                    $succes = true;
                } else {
                    $errors['global'] = 'Le lien a expiré. Veuillez refaire une demande.';
                }
            }
        }

        $this->render('auth/reinitialiser_mdp', [
            'pageTitle'     => 'Réinitialiser le mot de passe — MediConnect',
            'tokenInvalide' => false,
            'token'         => $token,
            'errors'        => $errors,
            'succes'        => $succes,
            'csrf_token'    => $this->genererTokenCsrf(),
        ]);
    }
}
