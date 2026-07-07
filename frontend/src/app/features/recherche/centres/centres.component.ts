import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule }  from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { Subject, switchMap, debounceTime, distinctUntilChanged, takeUntil, of, combineLatest } from 'rxjs';
import { catchError }    from 'rxjs/operators';

import { CentreService, IRechercheCentreParams } from '../../../core/services/centre.service';
import { AdresseAutocompleteComponent } from '../adresse-autocomplete/adresse-autocomplete.component';
import { AdresseService, IAdresseSuggestion } from '../../../core/services/adresse.service';
import { CarteComponent }               from '../carte/carte.component';
import { ICentreSante, ICentreAnalyse } from '../../../core/models/interfaces';

@Component({
  selector   : 'app-centres',
  standalone : true,
  imports         : [CommonModule, RouterLink, RouterLinkActive, CarteComponent, AdresseAutocompleteComponent],
  templateUrl     : './centres.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class CentresComponent implements OnInit, OnDestroy {

  private readonly svc        = inject(CentreService);
  private readonly adresseSvc = inject(AdresseService);
  private readonly destroy$ = new Subject<void>();
  private readonly filtres$ = new Subject<IRechercheCentreParams>();

  centresSante    = signal<ICentreSante[]>([]);
  centresAnalyse  = signal<ICentreAnalyse[]>([]);
  chargement      = signal(false);
  erreur          = signal<string | null>(null);
  positionUtilisateur = signal<[number, number] | null>(null);
  adresseAffichee = signal<string | undefined>(undefined);
  onglet          = signal<'sante' | 'analyse'>('sante');
  vueCarte        = signal(false);
  ville           = signal('');

  readonly rayonMin = 5;
  readonly rayonMax = 100;
  rayon = signal(10);

  ngOnInit(): void {
    this.filtres$.pipe(
      debounceTime(350),
      distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
      switchMap(f => {
        this.chargement.set(true);
        this.erreur.set(null);
        return combineLatest([
          this.svc.getCentresSante(f).pipe(catchError(() => of(null))),
          this.svc.getCentresAnalyse(f).pipe(catchError(() => of(null))),
        ]);
      }),
      takeUntil(this.destroy$)
    ).subscribe(([sante, analyse]) => {
      this.chargement.set(false);
      if (!sante && !analyse) { this.erreur.set('Impossible de charger les centres.'); return; }
      if (sante)   this.centresSante.set(sante.data);
      if (analyse) this.centresAnalyse.set(analyse.data);
    });

    this.filtres$.next({});
  }

  onVilleChange(v: string): void {
    this.ville.set(v);
    this.lancer();
  }

  onRayonChange(r: number): void {
    this.rayon.set(r);
    this.lancer();
  }

  onAdresseSelectionnee(s: IAdresseSuggestion): void {
    this.positionUtilisateur.set([s.lat, s.lng]);
    this.lancer();
  }

  onAdresseEffacee(): void {
    this.positionUtilisateur.set(null);
    this.lancer();
  }

  geoLocaliser(): void {
    if (!navigator.geolocation) { this.erreur.set('Géolocalisation non disponible.'); return; }
    navigator.geolocation.getCurrentPosition(
      pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        this.positionUtilisateur.set([lat, lng]);
        this.lancer();

        this.adresseSvc.inverser(lat, lng).pipe(takeUntil(this.destroy$)).subscribe(label => {
          if (label) this.adresseAffichee.set(label);
        });
      },
      () => this.erreur.set('Impossible d\'obtenir votre position.')
    );
  }

  private lancer(): void {
    const pos = this.positionUtilisateur();
    this.filtres$.next({
      lat: pos?.[0], lng: pos?.[1],
      rayon: this.rayon(),
      ville: this.ville() || undefined,
    });
  }

  get centresCommeIMedecin() {
    const liste: Array<ICentreSante | ICentreAnalyse> =
      this.onglet() === 'sante' ? this.centresSante() : this.centresAnalyse();
    return liste.map(c => ({
      id: c.id, nom: c.nom ?? '', prenom: '', email: c.email ?? '',
      specialisation: 0, specialisationLibelle: this.onglet() === 'sante' ? 'Centre de santé' : 'Laboratoire',
      numeroRpps: '', adresseCabinet: c.adresse ?? undefined,
      latitude: c.latitude ?? undefined, longitude: c.longitude ?? undefined,
      dureeRdv: 0, distance: c.distance ?? undefined,
      utilisateurId: 0,
    }));
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}