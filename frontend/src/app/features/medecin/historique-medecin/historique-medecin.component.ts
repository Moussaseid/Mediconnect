import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { RouterLink }             from '@angular/router';
import { catchError, of }         from 'rxjs';

import { RdvService } from '../../../core/services/rdv.service';
import { IRdv }       from '../../../core/models/interfaces';

@Component({
  selector        : 'app-historique-medecin',
  standalone      : true,
  imports         : [CommonModule, RouterLink, DatePipe],
  templateUrl     : './historique-medecin.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class HistoriqueMedecinComponent implements OnInit {

  private readonly rdvSvc = inject(RdvService);

  rdvs       = signal<IRdv[]>([]);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  private readonly maintenant = new Date();

  readonly passes = computed(() =>
    this.rdvs()
      .filter(r => r.statut === 'annule' || new Date(r.dateHeure) <= this.maintenant)
      .sort((a, b) => +new Date(b.dateHeure) - +new Date(a.dateHeure))
  );

  readonly effectues = computed(() => this.passes().filter(r => r.statut !== 'annule').length);
  readonly annules   = computed(() => this.passes().filter(r => r.statut === 'annule').length);

  ngOnInit(): void {
    this.rdvSvc.mesRendezVous()
      .pipe(catchError(() => of(null)))
      .subscribe(res => {
        this.chargement.set(false);
        if (!res) { this.erreur.set('Impossible de charger l\'historique.'); return; }
        this.rdvs.set(res.data);
      });
  }
}
