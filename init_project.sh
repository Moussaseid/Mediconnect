#!/bin/bash

# ============================================================
#  MediConnect – init_project.sh
#  Structure conforme aux conventions d'équipe WE4A — SI40
# ============================================================

echo "🏥 Initialisation de MediConnect (structure WE4A)..."

# ── Point d'entrée public ────────────────────────────────────
mkdir -p public/assets/css
mkdir -p public/assets/js

# ── Source MVC ───────────────────────────────────────────────
mkdir -p src/controllers
mkdir -p src/models
mkdir -p src/views/auth
mkdir -p src/views/patient
mkdir -p src/views/medecin
mkdir -p src/views/admin
mkdir -p src/views/rendez_vous
mkdir -p src/views/layouts
mkdir -p src/views/partials
mkdir -p src/views/errors

# ── Endpoints AJAX dédiés (convention section 1.7) ──────────
mkdir -p api

# ── Configuration ────────────────────────────────────────────
mkdir -p config

# ── Base de données ──────────────────────────────────────────
mkdir -p database

# ============================================================
#  Fichiers de base
# ============================================================

cat > public/index.php << 'EOF'
<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/config.php';
EOF

cat > config/config.php << 'EOF'
<?php
session_start();
require_once ROOT . '/config/database.php';
spl_autoload_register(function (string $class): void {
    $file = ROOT . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});
require_once ROOT . '/routes.php';
EOF

cat > config/database.php << 'EOF'
<?php
$host     = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$dbname   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'mediconnect';
$user     = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'root';
$password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de connexion à la base de données.']));
}
EOF

cat > .env << 'EOF'
DB_HOST=localhost
DB_NAME=mediconnect
DB_USER=root
DB_PASS=
EOF

cat > .env.example << 'EOF'
DB_HOST=localhost
DB_NAME=mediconnect
DB_USER=your_db_user
DB_PASS=your_db_password
EOF

cat > routes.php << 'EOF'
<?php
// Définir vos routes ici
// $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// match($uri) {
//     '/connexion' => (new \controllers\AuthController())->connexion(),
//     default      => (new \controllers\ErrorController())->notFound(),
// };
EOF

cat > src/controllers/AuthController.php << 'EOF'
<?php
namespace controllers;

class AuthController
{
    public function connexion(): void
    {
        // GET → afficher formulaire / POST → password_verify() + session
        require_once ROOT . '/src/views/auth/login_form.php';
    }

    public function deconnexion(): void
    {
        session_destroy();
        header('Location: /connexion');
        exit;
    }
}
EOF

cat > src/models/UserModel.php << 'EOF'
<?php
namespace models;

class UserModel
{
    public function __construct(private \PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM utilisateurs WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, telephone, adresse, ville)
             VALUES (:nom, :prenom, :email, :hash, :telephone, :adresse, :ville)'
        );
        return $stmt->execute([
            ':nom'       => htmlspecialchars($data['nom']),
            ':prenom'    => htmlspecialchars($data['prenom']),
            ':email'     => $data['email'],
            ':hash'      => password_hash($data['mot_de_passe'], PASSWORD_BCRYPT),
            ':telephone' => htmlspecialchars($data['telephone']),
            ':adresse'   => htmlspecialchars($data['adresse']),
            ':ville'     => htmlspecialchars($data['ville']),
        ]);
    }
}
EOF

cat > src/views/auth/login_form.php << 'EOF'
<?php /* login_form.php */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — MediConnect</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <form method="POST" action="/connexion">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
        <label for="motDePasse">Mot de passe</label>
        <input type="password" id="motDePasse" name="mot_de_passe" required>
        <button type="submit">Se connecter</button>
    </form>
    <script src="/assets/js/validation.js"></script>
</body>
</html>
EOF

cat > api/medecins.php << 'EOF'
<?php
// Endpoint AJAX — retourne TOUJOURS du JSON (section 1.7)
header('Content-Type: application/json');
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';

$rayon          = intval($_GET['rayon'] ?? 10);
$specialisation = htmlspecialchars($_GET['specialisation'] ?? '');

if ($rayon <= 0 || $rayon > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre rayon invalide.']);
    exit;
}
// TODO : MedecinModel->rechercherParRayon($rayon, $lat, $lon)
echo json_encode(['medecins' => []]);
exit;
EOF

cat > database/schema.sql << 'EOF'
-- MediConnect — Schéma de base de données
CREATE DATABASE IF NOT EXISTS mediconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mediconnect;

CREATE TABLE utilisateurs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100)  NOT NULL,
    prenom            VARCHAR(100)  NOT NULL,
    email             VARCHAR(255)  NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255)  NOT NULL,
    telephone         VARCHAR(20),
    adresse           VARCHAR(255),
    ville             VARCHAR(100),
    role              ENUM('patient','medecin','admin') NOT NULL DEFAULT 'patient',
    statut            ENUM('actif','en_attente','rejete') NOT NULL DEFAULT 'actif',
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medecins (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT          NOT NULL,
    specialisation  VARCHAR(100) NOT NULL,
    numero_rpps     VARCHAR(11)  NOT NULL UNIQUE,
    adresse_cabinet VARCHAR(255),
    latitude        DECIMAL(10,8),
    longitude       DECIMAL(11,8),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE pharmacies (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   VARCHAR(255),
    latitude  DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

CREATE TABLE centres_sante (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   VARCHAR(255),
    latitude  DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

CREATE TABLE centres_analyse (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(150) NOT NULL,
    adresse   VARCHAR(255),
    latitude  DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

CREATE TABLE creneaux_disponibilite (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    medecin_id   INT     NOT NULL,
    jour_semaine TINYINT NOT NULL COMMENT '1=Lundi … 7=Dimanche',
    heure_debut  TIME    NOT NULL,
    heure_fin    TIME    NOT NULL,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE
);

CREATE TABLE rendez_vous (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medecin_id INT NOT NULL,
    date_heure DATETIME NOT NULL,
    statut     ENUM('en_attente','confirme','refuse','annule') NOT NULL DEFAULT 'en_attente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY rdv_unique (medecin_id, date_heure),
    FOREIGN KEY (patient_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (medecin_id) REFERENCES medecins(id)
);

CREATE TABLE medicaments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    description TEXT
);

CREATE TABLE inventaire (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pharmacie_id  INT NOT NULL,
    medicament_id INT NOT NULL,
    quantite      INT NOT NULL DEFAULT 0,
    FOREIGN KEY (pharmacie_id)  REFERENCES pharmacies(id),
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
);
EOF

cat > database/seed.sql << 'EOF'
-- MediConnect — Données de test Sprint 1
USE mediconnect;

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role) VALUES
('Admin', 'MediConnect', 'admin@mediconnect.fr', '$2y$10$placeholder_hash_admin', 'admin');

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut) VALUES
('Dupont',  'Jean',  'j.dupont@mediconnect.fr',  '$2y$10$hash1', 'medecin', 'actif'),
('Martin',  'Marie', 'm.martin@mediconnect.fr',  '$2y$10$hash2', 'medecin', 'actif'),
('Bernard', 'Paul',  'p.bernard@mediconnect.fr', '$2y$10$hash3', 'medecin', 'actif');

INSERT INTO medecins (utilisateur_id, specialisation, numero_rpps, adresse_cabinet, latitude, longitude) VALUES
(2, 'Generaliste', '10003456789', '12 rue de la Paix, Paris',     48.86950, 2.33130),
(3, 'Cardiologue', '10003456790', '5 avenue Montaigne, Paris',    48.86612, 2.30514),
(4, 'Pediatre',    '10003456791', '8 boulevard Haussmann, Paris', 48.87395, 2.33100);

INSERT INTO pharmacies (nom, adresse, latitude, longitude) VALUES
('Pharmacie Centrale',  '1 place de la Republique, Paris', 48.86735, 2.36302),
('Pharmacie du Marche', '15 rue du Commerce, Paris',       48.84879, 2.29474);

INSERT INTO creneaux_disponibilite (medecin_id, jour_semaine, heure_debut, heure_fin) VALUES
(1, 1, '09:00', '12:00'), (1, 1, '14:00', '18:00'),
(1, 3, '09:00', '12:00'),
(2, 2, '10:00', '13:00'), (2, 4, '14:00', '17:00'),
(3, 5, '09:00', '12:00'), (3, 5, '13:00', '16:00');
EOF

cat > database/queries.sql << 'EOF'
-- MediConnect — Requêtes SQL documentées

-- Recherche de médecins par rayon (formule Haversine)
-- :lat   → latitude patient   (DECIMAL)
-- :lon   → longitude patient  (DECIMAL)
-- :rayon → rayon en km        (INT)
SELECT
    u.nom, u.prenom, m.specialisation, m.adresse_cabinet,
    m.latitude, m.longitude,
    (6371 * ACOS(
        COS(RADIANS(:lat)) * COS(RADIANS(m.latitude))
        * COS(RADIANS(m.longitude) - RADIANS(:lon))
        + SIN(RADIANS(:lat)) * SIN(RADIANS(m.latitude))
    )) AS distance_km
FROM medecins m
JOIN utilisateurs u ON u.id = m.utilisateur_id
WHERE u.statut = 'actif'
HAVING distance_km <= :rayon
ORDER BY distance_km ASC;
EOF

cat > .htaccess << 'EOF'
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
EOF

cat > public/.htaccess << 'EOF'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
EOF

cat > .gitignore << 'EOF'
.env
/vendor/
config/database.php
*.log
/storage/
.DS_Store
Thumbs.db
EOF

cat > README.md << 'EOF'
# MediConnect — WE4A / SI40

## Installation
1. Copier `.env.example` → `.env` et renseigner les credentials
2. `mysql -u root -p < database/schema.sql`
3. `mysql -u root -p mediconnect < database/seed.sql`
4. Configurer votre virtual host Apache vers `/public`

## Structure
```
public/           Point d'entrée + assets (css/js)
src/controllers/  Logique de contrôle
src/models/       Accès PDO (requêtes préparées)
src/views/        Templates HTML/PHP par domaine
api/              Endpoints AJAX (JSON uniquement)
config/           Bootstrap, connexion BDD
database/         schema.sql · seed.sql · queries.sql
routes.php        Définition des routes
.env.example      Modèle de config (sans credentials)
```
EOF

# ============================================================
echo ""
echo "✅ Structure MediConnect initialisée (conforme conventions WE4A) !"
echo ""
echo "📁 Fichiers générés :"
find . -not -path './.git/*' -type f | sort | sed 's|^\./||'
echo ""
echo "▶  Prochaines étapes :"
echo "   1. cp .env.example .env  → renseigner vos credentials"
echo "   2. mysql -u root -p < database/schema.sql"
echo "   3. mysql -u root -p mediconnect < database/seed.sql"
echo "   4. Configurer virtual host Apache vers /public"