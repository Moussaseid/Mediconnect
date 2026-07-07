import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule, DatePipe, Location } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subject, catchError, of, switchMap, takeUntil } from 'rxjs';

import { MedecinService, ICreneauJour } from '../../../core/services/medecin.service';
import { RdvService }     from '../../../core/services/rdv.service';
import { AuthService }    from '../../../core/services/auth.service';
import { IMedecin } from '../../../core/models/interfaces';

@Component({
  selector   : 'app-medecin-detail',
  standalone : true,
  imports         : [CommonModule, RouterLink, DatePipe],
  templateUrl     : './medecin-detail.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class MedecinDetailComponent implements OnInit, OnDestroy {

  private readonly route      = inject(ActivatedRoute);
  private readonly router     = inject(Router);
  private readonly location   = inject(Location);
  private readonly medecinSvc = inject(MedecinService);
  private readonly rdvSvc     = inject(RdvService);
  readonly auth                = inject(AuthService);
  private readonly destroy$   = new Subject<void>();

  medecin    = signal<IMedecin | null>(null);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  creneaux            = signal<ICreneauJour[]>([]);
  chargementCreneaux  = signal(false);
  reservationEnCours  = signal<string | null>(null);
  messageReservation  = signal<{ type: 'success' | 'error'; texte: string } | null>(null);
  creneauAConfirmer   = signal<{ date: string; heure: string } | null>(null);

  ngOnInit(): void {
    this.route.paramMap.pipe(
      switchMap(p => {
        const id = Number(p.get('id'));
        if (!id) {
          this.erreur.set('Identifiant invalide.');
          this.chargement.set(false);
          return of(null);
        }
        return this.medecinSvc.getById(id).pipe(
          catchError(() => {
            this.erreur.set('Médecin introuvable ou erreur réseau.');
            return of(null);
          })
        );
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargement.set(false);
      if (res) {
        this.medecin.set(res.data);
        this.chargerCreneaux(res.data.id);
      }
    });
  }

  retour(): void { this.location.back(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private chargerCreneaux(medecinId: number): void {
    this.chargementCreneaux.set(true);
    this.medecinSvc.getCreneaux(medecinId).pipe(
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargementCreneaux.set(false);
      if (res) this.creneaux.set(res.data);
    });
  }

  cleCreneau(date: string, heure: string): string {
    return `${date}_${heure}`;
  }

  onCliqueCreneau(date: string, heure: string): void {
    if (!this.auth.estConnecte() || !this.auth.estTokenValide()) {
      this.router.navigate(['/login']);
      return;
    }
    const role = this.auth.role();
    if (role !== 'patient' && role !== 'admin') return;

    this.messageReservation.set(null);
    this.creneauAConfirmer.set({ date, heure });
  }

  annulerSelection(): void {
    this.creneauAConfirmer.set(null);
  }

  confirmerRdv(): void {
    const creneau = this.creneauAConfirmer();
    const m = this.medecin();
    if (!creneau || !m) return;

    const cle = this.cleCreneau(creneau.date, creneau.heure);
    this.creneauAConfirmer.set(null);
    this.reservationEnCours.set(cle);
    this.messageReservation.set(null);

    this.rdvSvc.creer({ medecinId: m.id, dateHeure: `${creneau.date} ${creneau.heure}:00` }).pipe(
      catchError(err => {
        this.messageReservation.set({
          type : 'error',
          texte: err.error?.error ?? 'Erreur lors de la prise de rendez-vous.',
        });
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.reservationEnCours.set(null);
      if (res) {
        this.messageReservation.set({ type: 'success', texte: 'Rendez-vous confirmé !' });
        this.chargerCreneaux(m.id);
      }
    });
  }
}