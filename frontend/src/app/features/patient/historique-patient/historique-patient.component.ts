import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { RouterLink }             from '@angular/router';
import { catchError, of }         from 'rxjs';

import { RdvService }  from '../../../core/services/rdv.service';
import { IRdv }        from '../../../core/models/interfaces';

@Component({
  selector        : 'app-historique-patient',
  standalone      : true,
  imports         : [CommonModule, RouterLink, DatePipe],
  templateUrl     : './historique-patient.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class HistoriquePatientComponent implements OnInit {

  private readonly rdvSvc = inject(RdvService);

  rdvs       = signal<IRdv[]>([]);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  readonly maintenant = new Date();

  readonly passes = computed(() =>
    this.rdvs()
      .filter(r => r.statut === 'annule' || new Date(r.dateHeure) <= this.maintenant)
      .sort((a, b) => +new Date(b.dateHeure) - +new Date(a.dateHeure))
  );

  ngOnInit(): void {
    this.rdvSvc.mesRendezVousPatient()
      .pipe(catchError(() => of(null)))
      .subscribe(res => {
        this.chargement.set(false);
        if (!res) { this.erreur.set('Impossible de charger l\'historique.'); return; }
        this.rdvs.set(res.data);
      });
  }
}
