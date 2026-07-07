<?php
namespace controllers;

use models\AdminModel;
use models\MedecinModel;
use services\AuthService;

class AdminController extends BaseController
{
    private const PAR_PAGE       = 10;
    private const PAR_PAGE_ROLES = 15;

    private AuthService  $authService;
    private MedecinModel $medecinModel;
    private AdminModel   $adminModel;

    public function __construct(\PDO $pdo)
    {
        $this->authService  = new AuthService($pdo);
        $this->medecinModel = new MedecinModel($pdo);
        $this->adminModel   = new AdminModel($pdo);
    }

    // ------------------------------------------------------------------ #6 connexion
    public function connexion(array $params = []): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $email = trim($_POST['email'] ?? '');
            $mdp   = $_POST['mot_de_passe'] ?? '';

            $user = $this->authService->login($email, $mdp);

            if ($user === null || $user['role'] !== 'admin') {
                $error = 'Identifiants incorrects.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                $this->redirect('/admin/dashboard');
            }
        }

        $this->render('admin/connexion', [
            'pageTitle'  => 'Connexion administrateur — MediConnect',
            'error'      => $error,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #6 dashboard
    public function dashboard(array $params = []): void
    {
        $this->requireRole('admin');

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PAR_PAGE;

        $demandes = $this->medecinModel->listerEnAttente(self::PAR_PAGE, $offset);
        $total    = $this->medecinModel->compterEnAttente();
        $pages    = (int) ceil($total / self::PAR_PAGE);

        $this->render('admin/dashboard', [
            'pageTitle'  => 'Tableau de bord admin — MediConnect',
            'demandes'   => $demandes,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #6 valider
    public function valider(array $params = []): void
    {
        $this->requireRole('admin');
        $this->verifierTokenCsrf();

        $id      = (int) ($params['id'] ?? 0);
        $demande = $this->medecinModel->findById($id);

        if ($demande === null || $demande['statut'] !== 'en_attente') {
            $this->flash('error', 'Demande introuvable ou déjà traitée.');
            $this->redirect('/admin/dashboard');
        }

        $mdpTemporaire = $this->genererMotDePasseTemporaire();
        $adminId       = (int) $_SESSION['user']['id'];
        $this->medecinModel->approuver($demande, $mdpTemporaire, $adminId);

        $this->render('admin/validation_ok', [
            'pageTitle'     => 'Compte médecin créé — MediConnect',
            'demande'       => $demande,
            'mdpTemporaire' => $mdpTemporaire,
        ]);
    }

    // ------------------------------------------------------------------ #6 rejeter
    public function rejeter(array $params = []): void
    {
        $this->requireRole('admin');
        $this->verifierTokenCsrf();

        $id      = (int) ($params['id'] ?? 0);
        $demande = $this->medecinModel->findById($id);

        if ($demande === null || $demande['statut'] !== 'en_attente') {
            $this->flash('error', 'Demande introuvable ou déjà traitée.');
            $this->redirect('/admin/dashboard');
        }

        $this->medecinModel->mettreAJourStatut($id, 'rejete');
        $this->flash('success', 'Demande de ' . $demande['prenom'] . ' ' . $demande['nom'] . ' rejetée.');
        $this->redirect('/admin/dashboard');
    }

    // ------------------------------------------------------------------ #21 liste patients
    public function patients(array $params = []): void
    {
        $this->requireRole('admin');

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $offset   = ($page - 1) * self::PAR_PAGE;
        $patients = $this->adminModel->listerPatients(self::PAR_PAGE, $offset);
        $total    = $this->adminModel->compterPatients();
        $pages    = (int) ceil($total / self::PAR_PAGE);

        $this->render('admin/patients', [
            'pageTitle'  => 'Patients inscrits — MediConnect',
            'patients'   => $patients,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #30 liste médecins
    public function medecins(array $params = []): void
    {
        $this->requireRole('admin');

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $offset   = ($page - 1) * self::PAR_PAGE;
        $medecins = $this->adminModel->listerMedecins(self::PAR_PAGE, $offset);
        $total    = $this->adminModel->compterMedecins();
        $pages    = (int) ceil($total / self::PAR_PAGE);

        $this->render('admin/medecins', [
            'pageTitle'  => 'Médecins — MediConnect',
            'medecins'   => $medecins,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #30 modifier médecin
    public function modifierMedecin(array $params = []): void
    {
        $this->requireRole('admin');

        $userId  = (int) ($params['id'] ?? 0);
        $medecin = $this->adminModel->findMedecinParId($userId);

        if ($medecin === null) {
            $this->flash('error', 'Médecin introuvable.');
            $this->redirect('/admin/medecins');
        }

        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $data = [
                'nom'             => trim($_POST['nom'] ?? ''),
                'prenom'          => trim($_POST['prenom'] ?? ''),
                'email'           => trim($_POST['email'] ?? ''),
                'telephone'       => trim($_POST['telephone'] ?? ''),
                'adresse'         => trim($_POST['adresse'] ?? ''),
                'ville'           => trim($_POST['ville'] ?? ''),
                'specialisation'  => trim($_POST['specialisation'] ?? ''),
                'adresse_cabinet' => trim($_POST['adresse_cabinet'] ?? ''),
                'duree_rdv'       => (int) ($_POST['duree_rdv'] ?? 30),
            ];

            if ($data['nom'] === '')            $errors['nom']            = 'Champ requis.';
            if ($data['prenom'] === '')         $errors['prenom']         = 'Champ requis.';
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail invalide.';
            if ($data['specialisation'] === '') $errors['specialisation'] = 'Champ requis.';
            if (!in_array($data['duree_rdv'], [15, 30, 45, 60], true)) $errors['duree_rdv'] = 'Valeur invalide.';

            if (empty($errors)) {
                $this->adminModel->modifierMedecin($userId, $data);
                $this->flash('success', 'Profil de ' . $data['prenom'] . ' ' . $data['nom'] . ' mis à jour.');
                $this->redirect('/admin/medecins');
            }

            // Pré-remplir avec les données saisies (pas celles de la BDD)
            $medecin = array_merge($medecin, $data);
        }

        $this->render('admin/medecin_modifier', [
            'pageTitle'  => 'Modifier un médecin — MediConnect',
            'medecin'    => $medecin,
            'errors'     => $errors,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ #30 suspendre / réactiver
    public function suspendre(array $params = []): void
    {
        $this->requireRole('admin');
        $this->verifierTokenCsrf();

        $userId  = (int) ($params['id'] ?? 0);
        $statut  = $_POST['statut'] ?? '';

        if (!in_array($statut, ['actif', 'suspendu'], true)) {
            $this->flash('error', 'Statut invalide.');
            $this->redirect('/admin/medecins');
        }

        $this->adminModel->changerStatutUtilisateur($userId, $statut);
        $label = $statut === 'suspendu' ? 'suspendu' : 'réactivé';
        $this->flash('success', "Compte $label avec succès.");

        // Rediriger vers la page d'origine (patients ou médecins)
        $retour = $_POST['redirect_to'] ?? '/admin/medecins';
        $retour = in_array($retour, ['/admin/patients', '/admin/medecins'], true)
                  ? $retour
                  : '/admin/medecins';
        $this->redirect($retour);
    }

    // ------------------------------------------------------------------ #30 supprimer médecin
    public function supprimerMedecin(array $params = []): void
    {
        $this->requireRole('admin');
        $this->verifierTokenCsrf();

        $userId = (int) ($params['id'] ?? 0);
        $this->adminModel->supprimerMedecin($userId);
        $this->flash('success', 'Médecin supprimé.');
        $this->redirect('/admin/medecins');
    }

    // ------------------------------------------------------------------ #29 attribution rôles institutionnels
    public function attribuerRole(array $params = []): void
    {
        $this->requireRole('admin');

        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $userId = (int) ($_POST['user_id'] ?? 0);
            $role   = trim($_POST['role'] ?? '');
            $rolesAutorises = ['patient', 'pharmacie', 'centre_sante', 'centre_analyse', 'medecin'];

            if ($userId <= 0)                             $errors['user_id'] = 'Utilisateur invalide.';
            if (!in_array($role, $rolesAutorises, true))  $errors['role']    = 'Rôle invalide.';

            if (empty($errors)) {
                $this->adminModel->attribuerRole($userId, $role);
                $this->flash('success', 'Rôle mis à jour avec succès.');
                $this->redirect('/admin/roles');
            }
        }

        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $total      = $this->adminModel->compterUtilisateursGestion();
        $totalPages = max(1, (int) ceil($total / self::PAR_PAGE_ROLES));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * self::PAR_PAGE_ROLES;

        $users = $this->adminModel->listerUtilisateursGestion(self::PAR_PAGE_ROLES, $offset);

        $this->render('admin/roles', [
            'pageTitle'  => 'Attribution des rôles — MediConnect',
            'users'      => $users,
            'errors'     => $errors,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    // ------------------------------------------------------------------ utilitaire
    private function genererMotDePasseTemporaire(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
        $mdp   = '';
        $max   = strlen($chars) - 1;
        for ($i = 0; $i < 16; $i++) {
            $mdp .= $chars[random_int(0, $max)];
        }
        return $mdp;
    }
}
