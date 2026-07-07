import { Routes } from '@angular/router';
import { authGuard } from '../../core/guards/auth.guard';
import { roleGuard } from '../../core/guards/role.guard';
import { CalendrierDisponibilitesComponent } from './calendrier-disponibilites/calendrier-disponibilites.component';
import { PriseRdvComponent } from './prise-rdv/prise-rdv.component';
import { MesRdvPatientComponent } from './mes-rdv-patient/mes-rdv-patient.component';
import { MesRdvMedecinComponent } from './mes-rdv-medecin/mes-rdv-medecin.component';
import { CreneauxMedecinComponent } from './creneaux-medecin/creneaux-medecin.component';

export const RDV_ROUTES: Routes = [
  {
    path: 'calendrier/:medecinId',
    component: CalendrierDisponibilitesComponent,
    canActivate: [authGuard, roleGuard],
    data: { roles: ['patient'] },
  },
  {
    path: 'prendre/:medecinId',
    component: PriseRdvComponent,
    canActivate: [authGuard, roleGuard],
    data: { roles: ['patient'] },
  },
  {
    path: 'mes-rdv',
    component: MesRdvPatientComponent,
    canActivate: [authGuard, roleGuard],
    data: { roles: ['patient'] },
  },
  {
    path: 'medecin/mes-rdv',
    component: MesRdvMedecinComponent,
    canActivate: [authGuard, roleGuard],
    data: { roles: ['medecin'] },
  },
  {
    path: 'medecin/creneaux',
    component: CreneauxMedecinComponent,
    canActivate: [authGuard, roleGuard],
    data: { roles: ['medecin'] },
  },
  { path: '', redirectTo: 'mes-rdv', pathMatch: 'full' },
];
