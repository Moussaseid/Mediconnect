<?php
namespace controllers;

use models\PatientModel;

class PatientController extends BaseController
{
    private PatientModel $patientModel;

    public function __construct(\PDO $pdo)
    {
        $this->patientModel = new PatientModel($pdo);
    }

    // ------------------------------------------------------------------ #3
    public function inscription(array $params = []): void
    {
        $errors = [];
        $old    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $nom      = trim($_POST['nom']      ?? '');
            $prenom   = trim($_POST['prenom']   ?? '');
            $email    = trim($_POST['email']    ?? '');
            $mdp      = $_POST['mot_de_passe']           ?? '';
            $confirm  = $_POST['confirmation_mot_de_passe'] ?? '';
            $tel      = trim($_POST['telephone'] ?? '');
            $adresse  = trim($_POST['adresse']  ?? '');
            $ville    = trim($_POST['ville']    ?? '');

            // Conserver les saisies pour re-remplissage (sauf mots de passe)
            $old = compact('nom', 'prenom', 'email', 'tel', 'adresse', 'ville');

            // --- Validation serveur ---
            if (strlen($nom) < 2) {
                $errors['nom'] = 'Le nom doit comporter au moins 2 caractères.';
            }
            if (strlen($prenom) < 2) {
                $errors['prenom'] = 'Le prénom doit comporter au moins 2 caractères.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Adresse e-mail invalide.';
            }
            if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $mdp)) {
                $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre.';
            }
            if ($mdp !== $confirm) {
                $errors['confirmation_mot_de_passe'] = 'Les mots de passe ne correspondent pas.';
            }
            if ($tel !== '' && !preg_match('/^[\d\s\+\-\.]{7,20}$/', $tel)) {
                $errors['telephone'] = 'Format de téléphone invalide.';
            }
            if ($adresse === '') {
                $errors['adresse'] = "L'adresse est requise.";
            }
            if ($ville === '') {
                $errors['ville'] = 'La ville est requise.';
            }

            // Unicité de l'email (uniquement si format valide)
            if (!isset($errors['email']) && $this->patientModel->findByEmail($email) !== null) {
                $errors['email'] = 'Cette adresse est déjà associée à un compte.';
            }

            if (empty($errors)) {
                $this->patientModel->creer([
                    'nom'         => $nom,
                    'prenom'      => $prenom,
                    'email'       => $email,
                    'mot_de_passe'=> $mdp,
                    'telephone'   => $tel,
                    'adresse'     => $adresse,
                    'ville'       => $ville,
                ]);
                $this->flash('success', 'Compte créé, connectez-vous.');
                $this->redirect('/connexion');
            }
        }

        $this->render('patient/inscription', [
            'pageTitle'  => 'Inscription — MediConnect',
            'errors'     => $errors,
            'old'        => $old,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    public function dashboard(array $params = []): void
    {
        $this->requireRole('patient');
        $this->render('patient/dashboard', [
            'pageTitle' => 'Mon espace — MediConnect',
        ]);
    }

}
