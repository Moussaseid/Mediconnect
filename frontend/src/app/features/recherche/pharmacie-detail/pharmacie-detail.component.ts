import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule, Location }     from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subject, switchMap, catchError, of, takeUntil } from 'rxjs';

import { PharmacieService }  from '../../../core/services/pharmacie.service';
import { CommandeService }   from '../../../core/services/commande.service';
import { AuthService }       from '../../../core/services/auth.service';
import { IPharmacie, IInventaire, ModeRetrait } from '../../../core/models/interfaces';

@Component({
  selector   : 'app-pharmacie-detail',
  standalone : true,
  imports         : [CommonModule, RouterLink],
  templateUrl     : './pharmacie-detail.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class PharmacieDetailComponent implements OnInit, OnDestroy {

  private readonly route       = inject(ActivatedRoute);
  private readonly router      = inject(Router);
  private readonly location    = inject(Location);
  private readonly svc         = inject(PharmacieService);
  private readonly commandeSvc = inject(CommandeService);
  readonly auth                = inject(AuthService);
  private readonly destroy$    = new Subject<void>();

  pharmacie  = signal<IPharmacie | null>(null);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  // État réservation
  ligneAReserver      = signal<IInventaire | null>(null);
  quantite            = signal(1);
  modeRetrait         = signal<ModeRetrait>('sur_place');
  adresseLivraison    = signal('');
  reservationEnCours  = signal(false);
  messageReservation  = signal<{ type: 'success' | 'error'; texte: string } | null>(null);

  ngOnInit(): void {
    this.route.paramMap.pipe(
      switchMap(p => {
        const id = Number(p.get('id'));
        if (!id) { this.erreur.set('Identifiant invalide.'); this.chargement.set(false); return of(null); }
        return this.svc.getById(id).pipe(
          catchError(() => { this.erreur.set('Pharmacie introuvable ou erreur réseau.'); return of(null); })
        );
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargement.set(false);
      if (res) this.pharmacie.set(res.data);
    });
  }

  retour(): void { this.location.back(); }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  ouvrirReservation(ligne: IInventaire): void {
    if (!this.auth.estConnecte() || !this.auth.estTokenValide()) {
      this.router.navigate(['/login']);
      return;
    }
    const role = this.auth.role();
    if (role !== 'patient' && role !== 'admin') return;

    this.ligneAReserver.set(ligne);
    this.quantite.set(1);
    this.modeRetrait.set('sur_place');
    this.adresseLivraison.set('');
    this.messageReservation.set(null);
  }

  annulerReservation(): void {
    this.ligneAReserver.set(null);
  }

  onQuantiteChange(val: string): void {
    const ligne = this.ligneAReserver();
    const n = Math.max(1, Math.min(parseInt(val, 10) || 1, ligne?.quantite ?? 1));
    this.quantite.set(n);
  }

  confirmerReservation(): void {
    const ligne   = this.ligneAReserver();
    const pharma  = this.pharmacie();
    if (!ligne || !pharma) return;

    if (this.modeRetrait() === 'livraison' && !this.adresseLivraison().trim()) {
      this.messageReservation.set({ type: 'error', texte: 'Veuillez saisir une adresse de livraison.' });
      return;
    }

    this.reservationEnCours.set(true);
    this.messageReservation.set(null);

    this.commandeSvc.creer({
      pharmacieId     : pharma.id,
      modeRetrait     : this.modeRetrait(),
      adresseLivraison: this.modeRetrait() === 'livraison' ? this.adresseLivraison().trim() : undefined,
      lignes          : [{ medicamentId: ligne.medicamentId, quantite: this.quantite() }],
    }).pipe(
      catchError(err => {
        this.messageReservation.set({
          type : 'error',
          texte: err.error?.error ?? 'Erreur lors de la réservation.',
        });
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.reservationEnCours.set(false);
      if (res) {
        this.ligneAReserver.set(null);
        this.messageReservation.set({ type: 'success', texte: 'Réservation confirmée ! La pharmacie prépare votre commande.' });
      }
    });
  }
}