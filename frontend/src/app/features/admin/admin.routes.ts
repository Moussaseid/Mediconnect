// features/admin/admin.routes.ts
import { Routes } from '@angular/router';
import { AdminLayoutComponent }           from './admin-layout.component';
import { DashboardAdminComponent }        from './dashboard-admin/dashboard-admin.component';
import { GestionDemandesComponent }       from './gestion-demandes/gestion-demandes.component';
import { GestionUtilisateursComponent }   from './gestion-utilisateurs/gestion-utilisateurs.component';
import { CrudPharmaciesComponent }        from './crud-pharmacies/crud-pharmacies.component';
import { CrudCentresComponent }           from './crud-centres/crud-centres.component';
import { PatientsComponent }             from './patients/patients.component';
import { MedecinsComponent }             from './medecins/medecins.component';
import { LogsComponent }                 from './logs/logs.component';
import { AuthDashboardComponent }        from './auth-dashboard/auth-dashboard.component';

export const adminRoutes: Routes = [
  {
    path     : '',
    component: AdminLayoutComponent,
    children : [
      { path: '',              redirectTo: 'dashboard', pathMatch: 'full'    },
      { path: 'dashboard',    component: DashboardAdminComponent             },
      { path: 'demandes',     component: GestionDemandesComponent            },
      { path: 'utilisateurs', component: GestionUtilisateursComponent        },
      { path: 'pharmacies',   component: CrudPharmaciesComponent             },
      { path: 'centres',      component: CrudCentresComponent                },
      { path: 'patients',     component: PatientsComponent                   },
      { path: 'medecins',     component: MedecinsComponent                   },
      { path: 'logs',         component: LogsComponent                       },
      { path: 'auth-dashboard', component: AuthDashboardComponent            },
    ],
  },
];