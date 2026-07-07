import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule }   from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { FormsModule }    from '@angular/forms';
import { Subject, debounceTime, distinctUntilChanged, switchMap, takeUntil, of } from 'rxjs';
import { catchError } from 'rxjs/operators';

import { PharmacieService, IRecherchePharmacieParamsEtendu } from '../../../core/services/pharmacie.service';
import { AdresseAutocompleteComponent } from '../adresse-autocomplete/adresse-autocomplete.component';
import { AdresseService, IAdresseSuggestion } from '../../../core/services/adresse.service';
import { CarteComponent }               from '../carte/carte.component';
import { IPharmacie } from '../../../core/models/interfaces';

@Component({
  selector   : 'app-pharmacies',
  standalone : true,
  imports         : [CommonModule, RouterLink, RouterLinkActive, FormsModule, CarteComponent, AdresseAutocompleteComponent],
  templateUrl     : './pharmacies.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class PharmaciesComponent implements OnInit, OnDestroy {

  private readonly svc        = inject(PharmacieService);
  private readonly adresseSvc = inject(AdresseService);
  private readonly destroy$ = new Subject<void>();
  private readonly filtres$ = new Subject<IRecherchePharmacieParamsEtendu>();

  pharmacies          = signal<IPharmacie[]>([]);
  total               = signal(0);
  chargement          = signal(false);
  erreur              = signal<string | null>(null);
  positionUtilisateur = signal<[number, number] | null>(null);
  adresseAffichee     = signal<string | undefined>(undefined);
  vueCarte            = signal(false);
  medicamentNom       = signal('');
  ville               = signal('');

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
        return this.svc.rechercher(f).pipe(
          catchError(() => { this.erreur.set('Impossible de charger les pharmacies.'); return of(null); })
        );
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargement.set(false);
      if (res) { this.pharmacies.set(res.data); this.total.set(res.total ?? res.data.length); }
    });

    this.filtres$.next({});
  }

  onMedicamentChange(nom: string): void {
    this.medicamentNom.set(nom);
    this.lancer();
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
      medicamentNom: this.medicamentNom() || undefined,
      ville: this.ville() || undefined,
    });
  }

  get pharmaciesCommeIMedecin() {
    return this.pharmacies().map(p => ({
      id: p.id, nom: p.nom ?? '', prenom: '', email: p.email ?? '',
      specialisation: 0, specialisationLibelle: 'Pharmacie',
      numeroRpps: '', adresseCabinet: p.adresse ?? undefined,
      latitude: p.latitude ?? undefined, longitude: p.longitude ?? undefined,
      dureeRdv: 0, distance: p.distance ?? undefined,
      utilisateurId: 0,
    }));
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}