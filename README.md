# MediConnect — WE4A / SI40 / WE4B

[![Tests](https://github.com/Yu9763/PROJET-WE4A-SI40/actions/workflows/tests.yml/badge.svg)](https://github.com/Yu9763/PROJET-WE4A-SI40/actions/workflows/tests.yml)
[![Build](https://github.com/Yu9763/PROJET-WE4A-SI40/actions/workflows/build.yml/badge.svg)](https://github.com/Yu9763/PROJET-WE4A-SI40/actions/workflows/build.yml)

Plateforme médicale en ligne — PHP 8 · MySQL · Angular 17 · JWT · MongoDB

---

## Prérequis

| Outil | Version |
|---|---|
| PHP | ≥ 8.1 (XAMPP recommandé) |
| MySQL | ≥ 8.0 (via XAMPP) |
| Composer | ≥ 2.0 |
| Node.js | ≥ 20 |
| Angular CLI | ≥ 17 (`npm install -g @angular/cli`) |

---

## Installation complète (1ère fois)

### 1. Cloner et configurer l'environnement

```bash
git clone https://github.com/Yu9763/PROJET-WE4A-SI40.git
cd PROJET-WE4A-SI40
git checkout develop

# Copier le fichier d'environnement
cp .env.example .env
```

Ouvrir `.env` et renseigner :
```
DB_HOST=127.0.0.1
DB_NAME=mediconnect
DB_USER=root
DB_PASS=

# Générer une clé JWT sécurisée :
# php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=REMPLACER_PAR_CLE_GENEREE

CORS_ALLOWED_ORIGINS=http://localhost:4200
```

### 2. Base de données (XAMPP MySQL démarré)

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p mediconnect < database/migration_fondations.sql
mysql -u root -p mediconnect < database/migration_gestion_rdv.sql
mysql -u root -p mediconnect < database/migration_add_columns.sql
mysql -u root -p mediconnect < database/migration_pharmacien.sql
mysql -u root -p mediconnect < database/migration_professionnels.sql
mysql -u root -p mediconnect < database/migration_refactoring_demandes.sql
mysql -u root -p mediconnect < database/migration_sprint3.sql
mysql -u root -p mediconnect < database/migration_photo_path.sql
mysql -u root -p mediconnect < database/seed.sql
```

### 3. Dépendances PHP

```bash
composer install
```

### 4. Dépendances Angular

```bash
cd frontend
npm install
cd ..
```

---

## Lancement (chaque session de travail)

**Terminal 1 — API PHP (port 8080)**
```bash
php -S localhost:8080 -t public public/server_router.php
```

**Terminal 2 — Angular (port 4200)**
```bash
cd frontend
npm start
```

Ouvrir **http://localhost:4200**

---

## Comptes de test

> Mot de passe universel : **`Test1234!`**

| Rôle | Email | Mot de passe |
|---|---|---|
| Admin | `admin@mediconnect.fr` | `Test1234!` |
| Médecin | `j.dupont@mediconnect.fr` | `Test1234!` |
| Médecin | `m.martin@mediconnect.fr` | `Test1234!` |
| Patient | *(s'inscrire via `/register`)* | — |

Ces comptes sont créés automatiquement par `database/seed.sql`.

---

## Architecture

```
public/               Point d'entrée Apache / PHP built-in server
  api/auth/           Endpoints REST JWT (login, register, me)
  server_router.php   Routeur pour PHP built-in server (dev)
src/
  controllers/        MVC — controllers web + api/
  models/             Accès PDO
  services/           AuthService · JwtService · MongoLogService
  views/              Templates PHP (app Partie 1)
frontend/             Application Angular 17
  src/app/
    core/             Services · Guards · Interceptors · Interfaces
    features/         Modules par fonctionnalité (auth, rdv, admin...)
    shared/           Composants réutilisables
config/               Base de données, JWT
database/             Schéma SQL + migrations + seeds
docs/                 MCD, MLD, schéma MongoDB
routes.php            Routeur PHP (web + API)
```

---

## Tests PHP

```bash
composer install
vendor/bin/phpunit --colors
```

---

## Conventions de contribution

Voir **CONVENTIONS_PARTIE2.md** à la racine du projet.

Branches : `feature/angular-*`, `feature/api-*`, `feature/mongo-*`
Commits : `feat(scope): description en français`
