import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal, computed } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { RouterLink }             from '@angular/router';
import { forkJoin, catchError, of, Subscription, interval } from 'rxjs';

import { MedecinService } from '../../../core/services/medecin.service';
import { RdvService }     from '../../../core/services/rdv.service';
import { AuthService }    from '../../../core/services/auth.service';
import { IMedecin, IRdv }  from '../../../core/models/interfaces';

type Periode = 'jour' | 'semaine';

@Component({
  selector   : 'app-dashboard-medecin',
  standalone : true,
  imports         : [CommonModule, RouterLink, DatePipe],
  templateUrl     : './dashboard-medecin.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class DashboardMedecinComponent implements OnInit, OnDestroy {

  private readonly medecinSvc = inject(MedecinService);
  private readonly rdvSvc     = inject(RdvService);
  readonly auth                = inject(AuthService);

  private refreshSub?: Subscription;

  medecin    = signal<IMedecin | null>(null);
  rdvs       = signal<IRdv[]>([]);
  chargement = signal(true);
  erreur     = signal<string | null>(null);
  periode    = signal<Periode>('jour');
  maintenant = signal(new Date());

  // ── Rendez-vous de la période sélectionnée ──────────────────────────────
  private readonly rdvPeriode = computed(() => {
    const p   = this.periode();
    const ref = this.maintenant();
    return this.rdvs().filter(r => this.dansPeriode(new Date(r.dateHeure), ref, p));
  });

  // ── Partition passés / en cours / à venir ───────────────────────────────
  readonly groupes = computed(() => {
    const ref   = this.maintenant();
    const duree = this.medecin()?.dureeRdv ?? 30;
    const enCours: IRdv[] = [];
    const aVenir:  IRdv[] = [];
    const passes:  IRdv[] = [];

    for (const r of this.rdvPeriode()) {
      const debut = new Date(r.dateHeure);
      const fin   = new Date(debut.getTime() + duree * 60000);
      if (r.statut === 'confirme' && ref >= debut && ref <= fin) {
        enCours.push(r);
      } else if (r.statut === 'confirme' && debut > ref) {
        aVenir.push(r);
      } else {
        passes.push(r);
      }
    }
    aVenir.sort((a, b) => +new Date(a.dateHeure) - +new Date(b.dateHeure));
    passes.sort((a, b) => +new Date(b.dateHeure) - +new Date(a.dateHeure));
    return { enCours, aVenir, passes };
  });

  ngOnInit(): void {
    forkJoin({
      medecin: this.medecinSvc.moi().pipe(catchError(() => of(null))),
      rdvs   : this.rdvSvc.mesRendezVous().pipe(catchError(() => of(null))),
    }).subscribe(res => {
      this.chargement.set(false);
      if (!res.medecin) {
        this.erreur.set('Impossible de charger votre profil médecin.');
        return;
      }
      this.medecin.set(res.medecin.data);
      this.rdvs.set(res.rdvs?.data ?? []);
    });

    // Rafraîchit "maintenant" toutes les minutes pour garder "en cours" à jour
    this.refreshSub = interval(60000).subscribe(() => this.maintenant.set(new Date()));
  }

  ngOnDestroy(): void {
    this.refreshSub?.unsubscribe();
  }

  changerPeriode(p: Periode): void {
    this.periode.set(p);
  }

  private dansPeriode(date: Date, ref: Date, periode: Periode): boolean {
    if (periode === 'jour') {
      return date.toDateString() === ref.toDateString();
    }
    const lundi = new Date(ref);
    const decalage = (ref.getDay() + 6) % 7; // lundi=0 ... dimanche=6
    lundi.setDate(ref.getDate() - decalage);
    lundi.setHours(0, 0, 0, 0);
    const dimanche = new Date(lundi);
    dimanche.setDate(lundi.getDate() + 6);
    dimanche.setHours(23, 59, 59, 999);
    return date >= lundi && date <= dimanche;
  }
}