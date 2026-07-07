import { Routes } from '@angular/router';
import { CentreAnalyseLayoutComponent } from './centre-analyse-layout.component';

export const centreAnalyseRoutes: Routes = [
  {
    path: '',
    component: CentreAnalyseLayoutComponent,
    children: [
      { path: '',         redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./dashboard-centre-analyse/dashboard-centre-analyse.component')
            .then(m => m.DashboardCentreAnalyseComponent),
      },
      {
        path: 'analyses',
        loadComponent: () =>
          import('./analyses/analyses.component')
            .then(m => m.AnalysesComponent),
      },
    ],
  },
];
