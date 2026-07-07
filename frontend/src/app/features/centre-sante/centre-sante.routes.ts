import { Routes } from '@angular/router';
import { CentreSanteLayoutComponent } from './centre-sante-layout.component';

export const centreSanteRoutes: Routes = [
  {
    path: '',
    component: CentreSanteLayoutComponent,
    children: [
      { path: '',         redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./dashboard-centre-sante/dashboard-centre-sante.component')
            .then(m => m.DashboardCentreSanteComponent),
      },
      {
        path: 'infos',
        loadComponent: () =>
          import('./infos-centre/infos-centre.component')
            .then(m => m.InfosCentreComponent),
      },
    ],
  },
];
