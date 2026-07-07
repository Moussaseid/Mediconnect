# MediConnect — Guide Architecture Partie 2

## Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────┐
│  NAVIGATEUR  →  Angular (port 4200)                         │
│                      ↓ proxy /api/*                         │
│  PHP built-in server (port 8080)                            │
│    → server_router.php → routes.php → Controller → MySQL    │
│    → MongoLogService  → var/logs/auth.log (ou MongoDB)      │
└─────────────────────────────────────────────────────────────┘
```

---

## 1. Côté PHP — où toucher quoi

### Ajouter un endpoint REST

1. Ouvrir `routes.php` et ajouter la route :
   ```php
   ['GET', '#^/api/medecins$#', 'controllers\api\MedecinApiController', 'liste'],
   ```
2. Créer le contrôleur dans `src/controllers/api/MedecinApiController.php`
3. Tester : `curl http://localhost:8080/api/medecins`

### Flux d'une requête API

```
Angular POST /api/auth/login
  → proxy → localhost:8080/api/auth/login
  → server_router.php  (force SCRIPT_NAME=/index.php)
  → config/config.php  (vendor/autoload + database + routes)
  → routes.php         (match POST #^/api/auth/login$#)
  → AuthApiController::login()
  → AuthService::login() → MySQL
  → JwtService::generer() → token JWT
  → MongoLogService::log() → var/logs/auth.log
  → jsonReponse([token, user])
```

### Fichiers PHP à connaître

| Fichier | Rôle |
|---|---|
| `routes.php` | Toutes les routes web ET API |
| `config/config.php` | Bootstrap : vendor, session, BDD, routes |
| `config/jwt.php` | Secret JWT, expiration, algorithme |
| `src/services/JwtService.php` | Génère et vérifie les tokens |
| `src/services/AuthService.php` | Login — réutilisé par l'API |
| `src/services/MongoLogService.php` | Logs auth (MongoDB ou fichier) |
| `src/controllers/api/AuthApiController.php` | login · register · me |
| `public/server_router.php` | Routeur PHP built-in server (dev uniquement) |

### ⚠️ Fichiers à IGNORER (ancienne Partie 1)

- `public/api/auth/login.php` — ancienne approche, remplacée par le controller
- `public/api/bootstrap.php` — ancienne approche, non utilisée via XAMPP
- `src/views/` — templates PHP Partie 1, non utilisés par Angular

---

## 2. Côté Angular — où toucher quoi

### Ajouter un composant

```bash
# Dans frontend/
ng generate component features/recherche/search-medecin
```

Structure attendue :
```
frontend/src/app/
  features/
    recherche/
      search-medecin/
        search-medecin.component.ts    ← logique
        search-medecin.component.html  ← template Bootstrap
```

### Ajouter un service Angular

```bash
ng generate service core/services/medecin
```

Le service appelle le PHP via `HttpClient` :
```typescript
getMedecins(params: IRechercheMedecinParams) {
  return this.http.get<IApiResponse<IMedecin[]>>(`${environment.apiUrl}/medecins`, { params });
}
```

### Fichiers Angular à connaître

| Fichier | Rôle |
|---|---|
| `src/app/core/models/interfaces.ts` | Tous les types TypeScript (IMedecin, IRdv...) |
| `src/app/core/services/auth.service.ts` | Connexion, token, user Signal |
| `src/app/core/interceptors/jwt.interceptor.ts` | Injecte Bearer automatiquement |
| `src/app/core/guards/auth.guard.ts` | Bloque si non connecté |
| `src/app/core/guards/role.guard.ts` | Bloque si mauvais rôle |
| `src/app/app.routes.ts` | Toutes les routes Angular |
| `src/environments/environment.ts` | URL API (`/api` → proxy → port 8080) |
| `proxy.conf.json` | Redirige /api → localhost:8080 |

### Ajouter une route Angular

Dans `app.routes.ts`, ajouter dans le bon espace :
```typescript
{
  path: 'medecin',
  canActivate: [authGuard, roleGuard],
  data: { roles: ['medecin'] },
  children: [
    { path: 'dashboard',  component: MedecinDashboardComponent },
    { path: 'horaires',   component: HorairesComponent },
  ],
},
```

---

## 3. Répartition des tâches Sprint 1

| Personne | Branche | Ce qu'elle crée |
|---|---|---|
| **P1** (Auth) | `feature/api-auth-jwt` ✅ DONE | JwtService, endpoints login/register/me |
| **P2** (Search) | `feature/angular-search` | SearchMedecinComponent + `GET /api/medecins` |
| **P3** (RDV) | `feature/angular-booking` | CalendrierComponent + `GET/POST /api/rdv` |
| **P4** (Admin) | `feature/angular-admin` | AdminDashboardComponent + `GET /api/admin/stats` |

---

## 4. Workflow Git

```bash
# Partir toujours de develop à jour
git checkout develop && git pull origin develop

# Créer sa branche
git checkout -b feature/angular-search

# Commiter selon les conventions
git commit -m "feat(search): ajouter SearchMedecinComponent avec filtre specialite"

# Pousser et ouvrir une PR vers develop
git push origin feature/angular-search
```

Voir `CONVENTIONS_PARTIE2.md` pour le format complet des commits.

---

## 5. FAQ rapide

**Q: Je veux tester mon endpoint PHP sans Angular ?**
```bash
curl http://localhost:8080/api/mon-endpoint
```

**Q: Comment voir l'utilisateur connecté dans Angular ?**
```typescript
import { inject } from '@angular/core';
import { AuthService } from '../../core/services/auth.service';
const auth = inject(AuthService);
console.log(auth.user());   // IUser | null
console.log(auth.role());   // 'admin' | 'patient' | ...
```

**Q: Comment protéger une route Angular par rôle ?**
```typescript
{ path: 'admin/stats', canActivate: [authGuard, roleGuard], data: { roles: ['admin'] }, component: StatsComponent }
```

**Q: Comment appeler l'API avec authentification ?**
Le `JwtInterceptor` injecte automatiquement le header `Authorization: Bearer <token>` sur toute requête vers `/api`. Rien à faire.

**Q: Le serveur PHP est-il redémarré automatiquement ?**
Non. À chaque session de travail, relancer :
```bash
php -S localhost:8080 -t public public/server_router.php
```
