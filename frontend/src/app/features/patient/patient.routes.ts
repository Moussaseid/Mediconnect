// features/patient/patient.routes.ts
import { Routes } from '@angular/router';
import { PatientLayoutComponent }     from './patient-layout.component';
import { RdvComponent }               from './rdv/rdv.component';
import { PrescriptionsComponent }     from './prescriptions/prescriptions.component';

export const patientRoutes: Routes = [
  {
    path     : '',
    component: PatientLayoutComponent,
    children : [
      { path: '',              redirectTo: 'rdv', pathMatch: 'full' },
      { path: 'rdv',           component: RdvComponent           },
      { path: 'prescriptions', component: PrescriptionsComponent },
      {
        path: 'historique',
        loadComponent: () =>
          import('./historique-patient/historique-patient.component')
            .then(m => m.HistoriquePatientComponent),
      },
    ],
  },
];
