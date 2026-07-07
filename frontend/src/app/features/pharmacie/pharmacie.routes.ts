// features/pharmacie/pharmacie.routes.ts
import { Routes } from '@angular/router';
import { PharmacieLayoutComponent }      from './pharmacie-layout.component';
import { InventaireComponent }           from './inventaire/inventaire.component';
import { CommandePharmacieComponent }    from './commande-pharmacie/commande-pharmacie.component';

export const pharmacieRoutes: Routes = [
  {
    path     : '',
    component: PharmacieLayoutComponent,
    children : [
      { path: '',          redirectTo: 'stock', pathMatch: 'full' },
      { path: 'stock',     component: InventaireComponent        },
      { path: 'commandes', component: CommandePharmacieComponent },
    ],
  },
];