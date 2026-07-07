<?php
namespace controllers;

use models\MedecinModel;

class MedecinController extends BaseController
{
    private MedecinModel $medecinModel;

    public function __construct(\PDO $pdo)
    {
        $this->medecinModel = new MedecinModel($pdo);
    }

    // ------------------------------------------------------------------ #5
    public function demande(array $params = []): void
    {
        $errors = [];
        $old    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifierTokenCsrf();

            $nom            = trim($_POST['nom']            ?? '');
            $prenom         = trim($_POST['prenom']         ?? '');
            $specialisation = trim($_POST['specialisation'] ?? '');
            $email          = trim($_POST['email']          ?? '');
            $rpps           = trim($_POST['numero_rpps']    ?? '');
            $adresseCabinet = trim($_POST['adresse_cabinet'] ?? '');

            $old = compact('nom', 'prenom', 'specialisation', 'email', 'rpps', 'adresseCabinet');

            if ($nom === '') {
                $errors['nom'] = 'Le nom est requis.';
            }
            if ($prenom === '') {
                $errors['prenom'] = 'Le prénom est requis.';
            }
            if ($specialisation === '') {
                $errors['specialisation'] = 'La spécialité est requise.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Adresse e-mail invalide.';
            }
            if (!preg_match('/^\d{11}$/', $rpps)) {
                $errors['numero_rpps'] = 'Le numéro RPPS doit comporter exactement 11 chiffres.';
            }
            if ($adresseCabinet === '') {
                $errors['adresse_cabinet'] = "L'adresse du cabinet est requise.";
            }

            if (!isset($errors['email']) && $this->medecinModel->emailDejaUtilise($email)) {
                $errors['email'] = 'Cette adresse e-mail est déjà associée à une demande ou un compte médecin.';
            }

            if (!isset($errors['numero_rpps']) && $this->medecinModel->rppsDejaUtilise($rpps)) {
                $errors['numero_rpps'] = 'Ce numéro RPPS est déjà enregistré.';
            }

            if (empty($errors)) {
                $this->medecinModel->creerDemande([
                    'nom'             => $nom,
                    'prenom'          => $prenom,
                    'specialisation'  => $specialisation,
                    'email'           => $email,
                    'numero_rpps'     => $rpps,
                    'adresse_cabinet' => $adresseCabinet,
                ]);
                $this->render('medecin/demande_confirmee', [
                    'pageTitle' => 'Demande envoyée — MediConnect',
                ]);
                return;
            }
        }

        $this->render('medecin/demande', [
            'pageTitle'  => 'Demande de compte médecin — MediConnect',
            'errors'     => $errors,
            'old'        => $old,
            'csrf_token' => $this->genererTokenCsrf(),
        ]);
    }

    public function dashboard(array $params = []): void
    {
        $this->requireRole('medecin');
        $this->render('medecin/dashboard', [
            'pageTitle' => 'Espace médecin — MediConnect',
        ]);
    }
}
