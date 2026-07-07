# MediConnect - Conventions Partie 2
Angular 17 - PHP REST API - MongoDB - Power BI

---

## 1. Strategie de branches

| Branche | Usage | Exemple |
|---|---|---|
| main | Code stable - jamais touche directement | - |
| develop | Branche integration commune | - |
| feature/angular-[nom] | Composant Angular | feature/angular-search |
| feature/api-[nom] | Endpoint PHP REST | feature/api-medecins |
| feature/mongo-[nom] | Collection MongoDB | feature/mongo-auth-logs |
| feature/pbi-[nom] | Dashboard Power BI | feature/pbi-inventaire |
| fix/[nom] | Correction bug | fix/jwt-expiration |
| chore/[nom] | Tache technique | chore/ci-github-actions |

Regle absolue : ne jamais commiter sur main ou develop directement.

---

## 2. Format commits - Conventional Commits

Format : type(scope): description en francais

### Types autorises

| Type | Usage |
|---|---|
| feat | Nouvelle fonctionnalite |
| fix | Correction bug |
| refactor | Refactorisation sans changement comportement |
| test | Ajout ou modification de tests |
| chore | Tache technique |
| docs | Documentation |
| style | Formatage uniquement |
| perf | Amelioration performance |

### Scopes Angular
auth | search | booking | admin | pharmacie | shared | core | routing

### Scopes API et donnees
api | mongo | pbi | db | ci

### Exemples valides
feat(auth): ajouter LoginComponent avec Reactive Form
feat(api): ajouter endpoint GET /api/medecins avec Haversine
feat(mongo): creer collection auth_logs avec index TTL 90j
fix(booking): corriger calcul des creneaux disponibles
feat(search): ajouter filtrage RxJS debounceTime 500ms
test(auth): ajouter tests Jasmine sur AuthService
feat(pbi): creer dashboard inventaire avec alertes stock
chore(ci): configurer pipeline GitHub Actions tests + lint
refactor(core): extraire JwtInterceptor dans core/interceptors
feat(admin): ajouter GestionDemandesComponent avec tri et pagination

### Exemples interdits
fix | update | ca marche | feat: login (scope manquant) | WIP

---

## 3. Nommage Angular

| Element | Convention | Exemple |
|---|---|---|
| Composants | PascalCase + Component | SearchMedecinComponent |
| Services | PascalCase + Service | MedecinService |
| Interfaces | PascalCase + prefixe I | IMedecin, IRdv, IUser |
| Guards | camelCase + Guard | adminGuard, authGuard |
| Interceptors | camelCase + Interceptor | jwtInterceptor |
| Modules | PascalCase + Module | AuthModule, BookingModule |
| Fichiers | kebab-case | search-medecin.component.ts |
| Pipes | camelCase + Pipe | distancePipe |

---

## 4. Structure Angular

src/app/
  core/
    models/interfaces.ts        <- contrats API
    services/
      auth.service.ts           <- login, logout, token
      medecin.service.ts        <- GET /api/medecins
      rdv.service.ts            <- GET/POST /api/rdv
      pharmacie.service.ts      <- GET /api/pharmacies
      admin.service.ts          <- endpoints admin
    guards/
      auth.guard.ts | patient.guard.ts | medecin.guard.ts | admin.guard.ts
    interceptors/
      jwt.interceptor.ts        <- inject Authorization header
  features/
    auth/ | recherche/ | rdv/ | admin/ | pharmacie/
  shared/components/
    medecin-card/ | alert-stock/ | kpi-widget/

---

## 5. Regles API REST PHP

Toutes les reponses retournent du JSON avec Content-Type: application/json.

Format succes  : { data: {}, message: OK, total: 42 }
Format erreur  : { error: Token invalide, code: 401 }

### Codes HTTP

| Code | Situation |
|---|---|
| 200 | Succes GET/PUT |
| 201 | Succes POST (creation) |
| 400 | Donnees invalides |
| 401 | Non authentifie (JWT absent ou expire) |
| 403 | Non autorise (mauvais role) |
| 404 | Ressource introuvable |
| 409 | Conflit (creneau deja pris - UNIQUE violation) |
| 500 | Erreur serveur |

---

## 6. Regles securite

- Ne jamais commiter .env (uniquement .env.example avec valeurs vides)
- JWT_SECRET dans .env uniquement
- Credentials MongoDB dans .env uniquement
- Aucune cle API dans le code
- console.log de donnees sensibles interdit
- var_dump / print_r interdits dans les endpoints API PHP
- Tout endpoint /api/ verifie le JWT avant la logique metier

---

## 7. Pull Requests

- Titre : type(scope): description courte
- Une PR = une feature ou un fix
- Ne pas merger sa propre PR - un autre membre valide
- Review dans les 24h
- CI : tous les tests PHPUnit et lint Angular doivent passer avant merge

---

## 8. Repartition Parties

| Personne | WE4B Angular | SI40 MongoDB | API PHP |
|---|---|---|---|
| P1 | AuthModule, Guards, JwtInterceptor | auth_logs | POST /api/login, /api/register |
| P2 | SearchModule, MapComponent | - | GET /api/medecins, /api/specialites |
| P3 | BookingModule, RdvListComponent | activity_logs | GET/POST /api/rdv, /api/creneaux |
| P4 | AdminModule, DashboardComponent | stats_analytiques + Power BI | GET /api/admin/stats |