import { ChangeDetectionStrategy, Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { RouterLink }             from '@angular/router';
import { catchError, of }         from 'rxjs';
import { FormsModule }            from '@angular/forms';

import { RdvService }  from '../../../core/services/rdv.service';
import { AuthService } from '../../../core/services/auth.service';
import { IRdv }        from '../../../core/models/interfaces';

@Component({
  selector   : 'app-dashboard-patient',
  standalone : true,
  imports         : [CommonModule, RouterLink, DatePipe, FormsModule],
  templateUrl     : './dashboard-patient.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class DashboardPatientComponent implements OnInit {

  private readonly rdvSvc = inject(RdvService);
  readonly auth            = inject(AuthService);

  rdvs       = signal<IRdv[]>([]);
  chargement = signal(true);
  erreur     = signal<string | null>(null);
  onglet     = signal<'aVenir' | 'passes'>('aVenir');

  // ID du RDV dont la modale d'annulation est ouverte
  annulationId    = signal<number | null>(null);
  motifAnnulation = signal('');
  enCoursAnnulation = signal(false);
  erreurAnnulation  = signal<string | null>(null);

  readonly maintenant = new Date();

  readonly aVenir = computed(() =>
    this.rdvs()
      .filter(r => r.statut !== 'annule' && new Date(r.dateHeure) > this.maintenant)
      .sort((a, b) => +new Date(a.dateHeure) - +new Date(b.dateHeure))
  );

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
        if (!res) { this.erreur.set('Impossible de charger vos rendez-vous.'); return; }
        this.rdvs.set(res.data);
      });
  }

  ouvrirAnnulation(id: number): void {
    this.annulationId.set(id);
    this.motifAnnulation.set('');
    this.erreurAnnulation.set(null);
  }

  fermerAnnulation(): void {
    this.annulationId.set(null);
  }

  confirmerAnnulation(): void {
    const id = this.annulationId();
    if (id === null) return;
    this.enCoursAnnulation.set(true);
    this.erreurAnnulation.set(null);

    this.rdvSvc.annuler(id, this.motifAnnulation() || undefined)
      .pipe(catchError(err => {
        const msg = err?.error?.error ?? 'Une erreur est survenue.';
        this.erreurAnnulation.set(msg);
        this.enCoursAnnulation.set(false);
        return of(null);
      }))
      .subscribe(res => {
        if (!res) return;
        // Met à jour localement le RDV annulé
        this.rdvs.update(list =>
          list.map(r => r.id === id ? { ...r, ...res.data } : r)
        );
        this.enCoursAnnulation.set(false);
        this.annulationId.set(null);
      });
  }
}