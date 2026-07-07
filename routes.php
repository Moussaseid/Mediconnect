<?php
// Routeur MediConnect
// Dispatche l'URI vers le bon contrôleur/méthode.
// Segments paramétrés : /admin/demande/{id}/valider → $params['id']

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ── Garantie JSON pour toutes les routes /api/* ───────────────────────────────
// display_errors=On en dev génère du HTML qui casse le parsing JSON côté Angular.
// On le coupe ici et on installe un handler qui renvoie toujours du JSON propre.
ini_set('display_errors', '0');
set_exception_handler(static function (\Throwable $e) use (&$uri): void {
    if (!headers_sent()) {
        http_response_code(500);
        if (isset($uri) && str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Erreur serveur interne', 'code' => 500]);
        }
    }
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
});

// Supprime le préfixe de sous-dossier si l'app est installée dans un sous-répertoire
// (ex: /mediconnect/connexion → /connexion)
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
if ($base !== '' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

// Redirection racine vers /connexion
if ($uri === '/') {
    header('Location: ' . $base . '/connexion');
    exit;
}

// ── API REST — CORS + JSON ────────────────────────────────────────────────────
// Toutes les routes /api/* : réponses JSON, pas de session HTML
if (str_starts_with($uri, '/api/')) {
    // CORS
    $allowedOrigins = array_filter(array_map('trim', explode(',',
        ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '') ?: (getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:4200')
    )));
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: http://localhost:4200');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');

    if ($method === 'OPTIONS') { http_response_code(204); exit; }

    header('Content-Type: application/json; charset=UTF-8');
}

// --- Correspondance des routes ---
// Chaque entrée : [méthode, pattern regex, contrôleur, méthode, params nommés]
$routes = [
    // ── API REST Auth ─────────────────────────────────────────────────────────
    ['POST',   '#^/api/auth/login$#',    'controllers\api\AuthApiController', 'login'],
    ['POST',   '#^/api/auth/register$#', 'controllers\api\AuthApiController', 'register'],
    ['GET',    '#^/api/auth/me$#',       'controllers\api\AuthApiController', 'me'],
    ['POST',   '#^/api/auth/logout$#',   'controllers\api\AuthApiController', 'logout'],
    ['PATCH',  '#^/api/auth/profil$#',   'controllers\api\AuthApiController', 'mettreAJourProfil'],

    // ── Reset mot de passe ────────────────────────────────────────────────────
    ['POST',   '#^/api/auth/forgot$#',   'controllers\api\PasswordApiController', 'forgot'],
    ['POST',   '#^/api/auth/reset$#',    'controllers\api\PasswordApiController', 'reset'],

    // ── Admin API (JWT admin requis) ──────────────────────────────────────────
    ['GET',    '#^/api/admin/patients$#',                'controllers\api\AdminApiController', 'patients'],
    ['GET',    '#^/api/admin/medecins$#',                'controllers\api\AdminApiController', 'medecins'],
    ['GET',    '#^/api/admin/medecins/(\d+)$#',          'controllers\api\AdminApiController', 'getMedecin',          ['id']],
    ['PUT',    '#^/api/admin/medecins/(\d+)$#',          'controllers\api\AdminApiController', 'modifierMedecin',     ['id']],
    ['PATCH',  '#^/api/admin/medecins/(\d+)/statut$#',   'controllers\api\AdminApiController', 'changerStatutMedecin', ['id']],
    ['DELETE', '#^/api/admin/medecins/(\d+)$#',          'controllers\api\AdminApiController', 'supprimerMedecin',    ['id']],
    ['GET',    '#^/api/admin/logs$#',                    'controllers\api\AdminApiController', 'logs'],
    ['GET',    '#^/api/admin/auth-stats$#',              'controllers\api\AdminApiController', 'authStats'],
    ['GET',    '#^/api/admin/stats$#',                   'controllers\api\AdminApiController', 'stats'],
    ['GET',    '#^/api/admin/demandes$#',                'controllers\api\AdminApiController', 'listeDemandes'],
    ['GET',    '#^/api/admin/demandes/(\d+)$#',              'controllers\api\AdminApiController', 'demande',             ['id']],
    ['PUT',    '#^/api/admin/demandes/(\d+)$#',              'controllers\api\AdminApiController', 'traiteDemande',       ['id']],
    ['POST',   '#^/api/admin/demandes/(\d+)/valider$#',      'controllers\api\AdminApiController', 'validerDemande',      ['id']],
    ['POST',   '#^/api/admin/demandes/(\d+)/rejeter$#',      'controllers\api\AdminApiController', 'rejeterDemande',      ['id']],
    ['GET',    '#^/api/admin/utilisateurs$#',                'controllers\api\AdminApiController', 'listeUtilisateurs'],
    ['PUT',    '#^/api/admin/utilisateurs/(\d+)$#',          'controllers\api\AdminApiController', 'modifierUtilisateur', ['id']],
    ['PATCH',  '#^/api/admin/utilisateurs/(\d+)$#',          'controllers\api\AdminApiController', 'modifierUtilisateur', ['id']],

    // ── API Pharmacies (CRUD) ─────────────────────────────────────────────────
    ['GET',    '#^/api/pharmacies$#',                    'controllers\api\PharmacieApiController', 'liste'],
    ['POST',   '#^/api/pharmacies$#',                    'controllers\api\PharmacieApiController', 'creer'],
    ['GET',    '#^/api/pharmacies/(\d+)$#',              'controllers\api\PharmacieApiController', 'detail',    ['id']],
    ['PUT',    '#^/api/pharmacies/(\d+)$#',              'controllers\api\PharmacieApiController', 'modifier',  ['id']],
    ['DELETE', '#^/api/pharmacies/(\d+)$#',              'controllers\api\PharmacieApiController', 'supprimer', ['id']],

    // ── API Centres santé + analyse (CRUD) ────────────────────────────────────
    ['GET',    '#^/api/centres/(sante|analyse)$#',         'controllers\api\CentreApiController', 'liste',    ['type']],
    ['POST',   '#^/api/centres/(sante|analyse)$#',         'controllers\api\CentreApiController', 'creer',    ['type']],
    ['GET',    '#^/api/centres/(sante|analyse)/(\d+)$#',   'controllers\api\CentreApiController', 'detail',   ['type', 'id']],
    ['PUT',    '#^/api/centres/(sante|analyse)/(\d+)$#',   'controllers\api\CentreApiController', 'modifier', ['type', 'id']],
    ['DELETE', '#^/api/centres/(sante|analyse)/(\d+)$#',   'controllers\api\CentreApiController', 'supprimer',['type', 'id']],

    // ── API Inventaire ────────────────────────────────────────────────────────
    ['GET',  '#^/api/inventaire/(\d+)$#',                'controllers\api\InventaireApiController', 'liste',    ['pharmacieId']],
    ['POST', '#^/api/inventaire$#',                      'controllers\api\InventaireApiController', 'creer'],
    ['PUT',  '#^/api/inventaire/(\d+)$#',                'controllers\api\InventaireApiController', 'modifier', ['id']],

    // ── API Commandes ─────────────────────────────────────────────────────────
    ['GET',  '#^/api/commandes$#',                       'controllers\api\CommandeApiController', 'liste'],
    ['POST', '#^/api/commandes$#',                       'controllers\api\CommandeApiController', 'creer'],
    ['GET',  '#^/api/commandes/(\d+)$#',                 'controllers\api\CommandeApiController', 'detail',   ['id']],
    ['PUT',  '#^/api/commandes/(\d+)$#',                 'controllers\api\CommandeApiController', 'modifier', ['id']],

    // ── API RDV (patient) ────────────────────────────────────────────────────
    ['GET',    '#^/api/rdv$#',                              'controllers\api\RdvApiController', 'liste'],
    ['GET',    '#^/api/rdv/medecins$#',                     'controllers\api\RdvApiController', 'medecins'],
    ['GET',    '#^/api/rdv/creneaux$#',                     'controllers\api\RdvApiController', 'creneaux'],
    ['POST',   '#^/api/rdv$#',                              'controllers\api\RdvApiController', 'creer'],
    ['PUT',    '#^/api/rdv/(\d+)$#',                       'controllers\api\RdvApiController', 'annuler',          ['id']],

    // ── API RDV (médecin) ────────────────────────────────────────────────────
    ['GET',    '#^/api/medecin/mes-rdv$#',                  'controllers\api\RdvApiController', 'mesRdvMedecin'],
    ['GET',    '#^/api/medecin/horaires$#',                 'controllers\api\RdvApiController', 'horaires'],
    ['POST',   '#^/api/medecin/horaires$#',                 'controllers\api\RdvApiController', 'ajouterHoraire'],
    ['DELETE', '#^/api/medecin/horaires/(\d+)$#',          'controllers\api\RdvApiController', 'supprimerHoraire', ['id']],

    // ── API Prescriptions ─────────────────────────────────────────────────────
    ['GET',  '#^/api/prescriptions$#',                   'controllers\api\PrescriptionApiController', 'liste'],
    ['GET',  '#^/api/prescriptions/(\d+)$#',             'controllers\api\PrescriptionApiController', 'detail', ['id']],

    // ── API Fichiers ──────────────────────────────────────────────────────────
    ['POST', '#^/api/fichiers/upload$#',                 'controllers\api\FichierApiController', 'upload'],

    // ── API Centre Analyse (JWT role='centre_analyse') ────────────────────────
    ['GET',    '#^/api/centre-analyse/infos$#',                  'controllers\api\CentreAnalyseApiController', 'infos'],
    ['GET',    '#^/api/centre-analyse/analyses$#',               'controllers\api\CentreAnalyseApiController', 'liste'],
    ['POST',   '#^/api/centre-analyse/analyses$#',               'controllers\api\CentreAnalyseApiController', 'creer'],
    ['PUT',    '#^/api/centre-analyse/analyses/(\d+)$#',         'controllers\api\CentreAnalyseApiController', 'modifier',  ['id']],
    ['DELETE', '#^/api/centre-analyse/analyses/(\d+)$#',         'controllers\api\CentreAnalyseApiController', 'supprimer', ['id']],
    ['PATCH',  '#^/api/centre-analyse/analyses/(\d+)/toggle$#',  'controllers\api\CentreAnalyseApiController', 'toggle',    ['id']],

    // ── API Centre Santé (JWT role='centre_sante') ────────────────────────────
    ['GET',  '#^/api/centre-sante/infos$#',              'controllers\api\CentreSanteApiController', 'infos'],
    ['PUT',  '#^/api/centre-sante/infos$#',              'controllers\api\CentreSanteApiController', 'modifierInfos'],
    ['POST', '#^/api/centre-sante/photo$#',              'controllers\api\CentreSanteApiController', 'uploadPhoto'],

    // ── API Recherche médecins / pharmacies / centres ─────────────────────────
    ['GET',    '#^/api/medecins$#',                                   'controllers\api\MedecinApiController', 'liste'],
    ['GET',    '#^/api/medecins/moi$#',                               'controllers\api\MedecinApiController', 'moi'],
    ['GET',    '#^/api/medecins/(\d+)$#',                             'controllers\api\MedecinApiController', 'detail',              ['id']],
    ['PUT',    '#^/api/medecins/(\d+)$#',                             'controllers\api\MedecinApiController', 'mettreAJour',         ['id']],
    ['PUT',    '#^/api/medecins/(\d+)/horaires$#',                    'controllers\api\MedecinApiController', 'mettreAJourHoraires', ['id']],
    ['GET',    '#^/api/medecins/(\d+)/creneaux$#',                    'controllers\api\MedecinApiController', 'creneaux',            ['id']],
    ['GET',    '#^/api/medecins/(\d+)/indisponibilites$#',            'controllers\api\MedecinApiController', 'listerIndisponibilites', ['id']],
    ['POST',   '#^/api/medecins/(\d+)/indisponibilites$#',            'controllers\api\MedecinApiController', 'creerIndisponibilite',   ['id']],
    ['DELETE', '#^/api/medecins/(\d+)/indisponibilites/(\d+)$#',     'controllers\api\MedecinApiController', 'supprimerIndisponibilite', ['medecinId', 'id']],

    // ── API Utilisateur (profil) ───────────────────────────────────────────────
    ['PUT',    '#^/api/utilisateurs/profil$#',                        'controllers\api\UserApiController', 'mettreAJour'],

    // ── API Analytics / Power BI ──────────────────────────────────────────────
    ['GET',    '#^/api/analytics/recherches$#',                       'controllers\api\AnalyticsApiController', 'recherches'],
    ['GET',    '#^/api/analytics/geo$#',                              'controllers\api\AnalyticsApiController', 'geo'],
    ['GET',    '#^/api/analytics/specialites$#',                      'controllers\api\AnalyticsApiController', 'specialites'],

    // ── API Export Power BI (JWT admin) ───────────────────────────────────────
    ['GET', '#^/api/export/stock-pharmacies$#',          'controllers\api\ExportApiController', 'stockPharmacies'],
    ['GET', '#^/api/export/commandes-stats$#',           'controllers\api\ExportApiController', 'commandesStats'],
    ['GET', '#^/api/export/admin-kpis$#',                'controllers\api\ExportApiController', 'adminKpis'],
    ['GET', '#^/api/export/mongo-logs$#',                'controllers\api\ExportApiController', 'mongoLogs'],

    // Auth unifiée
    ['GET',  '#^/connexion$#',       'controllers\AuthController',    'connexion'],
    ['POST', '#^/connexion$#',       'controllers\AuthController',    'connexion'],
    ['GET',  '#^/deconnexion$#',     'controllers\AuthController',    'deconnexion'],

    // Patient
    ['GET',  '#^/patient/inscription$#', 'controllers\PatientController', 'inscription'],
    ['POST', '#^/patient/inscription$#', 'controllers\PatientController', 'inscription'],
    ['GET',  '#^/patient/dashboard$#',   'controllers\PatientController', 'dashboard'],

    // Médecin — demande de compte
    ['GET',  '#^/medecin/demande$#',  'controllers\MedecinController', 'demande'],
    ['POST', '#^/medecin/demande$#',  'controllers\MedecinController', 'demande'],
    ['GET',  '#^/medecin/dashboard$#','controllers\MedecinController', 'dashboard'],

    // Admin
    ['GET',  '#^/admin/connexion$#',  'controllers\AdminController', 'connexion'],
    ['POST', '#^/admin/connexion$#',  'controllers\AdminController', 'connexion'],
    ['GET',  '#^/admin/dashboard$#',  'controllers\AdminController', 'dashboard'],
    ['POST', '#^/admin/demande/(\d+)/valider$#',   'controllers\AdminController', 'valider',   ['id']],
    ['POST', '#^/admin/demande/(\d+)/rejeter$#',   'controllers\AdminController', 'rejeter',   ['id']],

    // Admin — #21 patients
    ['GET',  '#^/admin/patients$#',   'controllers\AdminController', 'patients'],

    // Admin — #30 CRUD médecins
    ['GET',  '#^/admin/medecins$#',   'controllers\AdminController', 'medecins'],
    ['GET',  '#^/admin/medecin/(\d+)/modifier$#',  'controllers\AdminController', 'modifierMedecin', ['id']],
    ['POST', '#^/admin/medecin/(\d+)/modifier$#',  'controllers\AdminController', 'modifierMedecin', ['id']],
    ['POST', '#^/admin/utilisateur/(\d+)/suspendre$#', 'controllers\AdminController', 'suspendre',   ['id']],
    ['POST', '#^/admin/medecin/(\d+)/supprimer$#', 'controllers\AdminController', 'supprimerMedecin', ['id']],

    // Admin — #29 rôles institutionnels (15 utilisateurs/page)
    ['GET',  '#^/admin/roles$#',      'controllers\AdminController', 'attribuerRole'],
    ['POST', '#^/admin/roles$#',      'controllers\AdminController', 'attribuerRole'],

    // Auth — #20 reset mot de passe
    ['GET',  '#^/mot-de-passe-oubli$#',   'controllers\AuthController', 'motDePasseOubli'],
    ['POST', '#^/mot-de-passe-oubli$#',   'controllers\AuthController', 'motDePasseOubli'],
    ['GET',  '#^/reinitialiser-mdp$#',    'controllers\AuthController', 'reinitialiserMotDePasse'],
    ['POST', '#^/reinitialiser-mdp$#',    'controllers\AuthController', 'reinitialiserMotDePasse'],
];

$matched = false;

foreach ($routes as $route) {
    [$routeMethod, $pattern, $class, $action] = $route;
    $paramNames = $route[4] ?? [];

    if ($routeMethod !== $method) {
        continue;
    }

    if (!preg_match($pattern, $uri, $matches)) {
        continue;
    }

    // Extraction des segments capturés
    $params = [];
    array_shift($matches); // retire le match complet
    foreach ($paramNames as $i => $name) {
        $params[$name] = $matches[$i] ?? null;
    }

    $controller = new $class($pdo);
    $controller->$action($params);
    $matched = true;
    break;
}

if (!$matched) {
    http_response_code(404);
    // Routes /api/* : répondre en JSON (pas en HTML)
    if (str_starts_with($uri, '/api/')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Endpoint introuvable', 'code' => 404], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!DOCTYPE html><html lang="fr"><body><h1>404 — Page introuvable</h1></body></html>';
    }
}


