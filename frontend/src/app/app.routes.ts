// src/app/app.routes.ts
import { Routes }               from '@angular/router';
import { authGuard }            from './core/guards/auth.guard';
import { guestGuard }           from './core/guards/guest.guard';
import { patientGuard }         from './core/guards/patient.guard';
import { medecinGuard }         from './core/guards/medecin.guard';
import { adminGuard }           from './core/guards/admin.guard';
import { roleGuard }            from './core/guards/role.guard';
import { LoginComponent }          from './features/auth/login/login.component';
import { RegisterComponent }       from './features/auth/register/register.component';
import { ForgotPasswordComponent } from './features/auth/forgot-password/forgot-password.component';
import { ResetPasswordComponent }  from './features/auth/reset-password/reset-password.component';
import { DashboardComponent }      from './features/dashboard/dashboard.component';
import { ProfilComponent }         from './features/profil/profil.component';

export const routes: Routes = [

  { path: '', redirectTo: 'login', pathMatch: 'full' },

  // Auth publique (guests uniquement)
  { path: 'login',           canActivate: [guestGuard], component: LoginComponent          },
  { path: 'register',        canActivate: [guestGuard], component: RegisterComponent       },
  { path: 'forgot-password', canActivate: [guestGuard], component: ForgotPasswordComponent },
  { path: 'reset-password',  canActivate: [guestGuard], component: ResetPasswordComponent  },

  // Profil — tout utilisateur connecté
  { path: 'profil', canActivate: [authGuard], component: ProfilComponent },

  // Espace patient — lazy-loaded
  {
    path: 'patient',
    canActivate: [patientGuard],
    loadChildren: () =>
      import('./features/patient/patient.routes').then(m => m.patientRoutes),
  },

  // Espace médecin
  {
    path: 'medecin',
    canActivate: [medecinGuard],
    children: [
      { path: '',          component: DashboardComponent },
      { path: 'dashboard', component: DashboardComponent },
      {
        path: 'historique',
        loadComponent: () =>
          import('./features/medecin/historique-medecin/historique-medecin.component')
            .then(m => m.HistoriqueMedecinComponent),
      },
    ],
  },

  // RDV — patient et médecin (lazy-loaded, guards dans les routes enfants)
  {
    path: 'rdv',
    canActivate: [authGuard],
    loadChildren: () =>
      import('./features/rdv/rdv.routes').then(m => m.RDV_ROUTES),
  },

  // Espace admin — lazy-loaded, adminGuard sur toutes les routes
  {
    path: 'admin',
    canActivate: [adminGuard],
    loadChildren: () =>
      import('./features/admin/admin.routes').then(m => m.adminRoutes),
  },

  // Espace pharmacie — lazy-loaded
  {
    path: 'pharmacie',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['pharmacie'] },
    loadChildren: () =>
      import('./features/pharmacie/pharmacie.routes').then(m => m.pharmacieRoutes),
  },

  // Espace centre d'analyse — lazy-loaded
  {
    path: 'centre-analyse',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['centre_analyse'] },
    loadChildren: () =>
      import('./features/centre-analyse/centre-analyse.routes').then(m => m.centreAnalyseRoutes),
  },

  // Espace centre de santé — lazy-loaded
  {
    path: 'centre-sante',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['centre_sante'] },
    loadChildren: () =>
      import('./features/centre-sante/centre-sante.routes').then(m => m.centreSanteRoutes),
  },

  // Recherche — fiches publiques, liste et édition protégées
  {
    path: 'recherche',
    children: [
      {
        path: '',
        pathMatch: 'full',
        canActivate: [authGuard],
        loadComponent: () =>
          import('./features/recherche/search-medecin/search-medecin.component')
            .then(m => m.SearchMedecinComponent),
      },
      {
        path: 'medecin/:id',
        loadComponent: () =>
          import('./features/recherche/medecin-detail/medecin-detail.component')
            .then(m => m.MedecinDetailComponent),
      },
      {
        path: 'medecin/:id/modifier',
        canActivate: [authGuard],
        loadComponent: () =>
          import('./features/recherche/medecin-edit/medecin-edit.component')
            .then(m => m.MedecinEditComponent),
      },
      {
        path: 'pharmacies',
        canActivate: [authGuard],
        loadComponent: () =>
          import('./features/recherche/pharmacies/pharmacies.component')
            .then(m => m.PharmaciesComponent),
      },
      {
        path: 'pharmacie/:id',
        loadComponent: () =>
          import('./features/recherche/pharmacie-detail/pharmacie-detail.component')
            .then(m => m.PharmacieDetailComponent),
      },
      {
        path: 'centres',
        canActivate: [authGuard],
        loadComponent: () =>
          import('./features/recherche/centres/centres.component')
            .then(m => m.CentresComponent),
      },
      {
        path: 'centre-sante/:id',
        loadComponent: () =>
          import('./features/recherche/centre-sante-detail/centre-sante-detail.component')
            .then(m => m.CentreSanteDetailComponent),
      },
      {
        path: 'centre-analyse/:id',
        loadComponent: () =>
          import('./features/recherche/centre-analyse-detail/centre-analyse-detail.component')
            .then(m => m.CentreAnalyseDetailComponent),
      },
    ],
  },

  { path: '**', redirectTo: 'login' },
];
