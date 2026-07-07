import {
  ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal,
} from '@angular/core';
import { CommonModule }          from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { Subject, switchMap, debounceTime, distinctUntilChanged, takeUntil, of } from 'rxjs';
import { catchError }            from 'rxjs/operators';

import { MedecinService, IRechercheMedecinParamsEtendu } from '../../../core/services/medecin.service';
import { AdresseService }        from '../../../core/services/adresse.service';
import { FiltreComponent }       from '../filtre/filtre.component';
import { MedecinCardComponent }  from '../medecin-card/medecin-card.component';
import { CarteComponent }        from '../carte/carte.component';
import { IMedecin, ISpecialite } from '../../../core/models/interfaces';

@Component({
  selector   : 'app-search-medecin',
  standalone : true,
  imports           : [CommonModule, RouterLink, RouterLinkActive, FiltreComponent, MedecinCardComponent, CarteComponent],
  templateUrl       : './search-medecin.component.html',
  changeDetection   : ChangeDetectionStrategy.OnPush,
})
export class SearchMedecinComponent implements OnInit, OnDestroy {

  private readonly medecinSvc = inject(MedecinService);
  private readonly adresseSvc = inject(AdresseService);
  private readonly destroy$   = new Subject<void>();
  private readonly filtres$   = new Subject<IRechercheMedecinParamsEtendu>();

  medecins             = signal<IMedecin[]>([]);
  total                = signal(0);
  chargement           = signal(false);
  erreur               = signal<string | null>(null);
  filtres              = signal<IRechercheMedecinParamsEtendu>({ rayon: 10 });
  specialites          = signal<ISpecialite[]>([]);
  positionUtilisateur  = signal<[number, number] | null>(null);
  adresseAffichee      = signal<string | undefined>(undefined);
  vueCarte             = signal(false);

  ngOnInit(): void {
    // Chargement du référentiel des spécialités
    this.medecinSvc.getSpecialites().pipe(
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res) this.specialites.set(res.data);
    });

    // Pipeline de recherche réactive
    this.filtres$
      .pipe(
        debounceTime(300),
        distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
        switchMap(f => {
          this.chargement.set(true);
          this.erreur.set(null);
          return this.medecinSvc.rechercher(f).pipe(
            catchError(() => {
              this.erreur.set('Impossible de charger les médecins.');
              return of(null);
            })
          );
        }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.chargement.set(false);
        if (res) {
          this.medecins.set(res.data);
          this.total.set(res.total ?? res.data.length);
        }
      });

    // Lancement initial
    this.filtres$.next(this.filtres());
  }

  onFiltresChange(f: IRechercheMedecinParamsEtendu): void {
    this.filtres.set(f);
    this.positionUtilisateur.set(f.lat != null && f.lng != null ? [f.lat, f.lng] : null);
    this.filtres$.next(f);
  }

  geoLocaliser(): void {
    if (!navigator.geolocation) {
      this.erreur.set('La géolocalisation n\'est pas disponible sur ce navigateur.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        this.positionUtilisateur.set([lat, lng]);
        const f: IRechercheMedecinParamsEtendu = { ...this.filtres(), lat, lng };
        this.filtres.set(f);
        this.filtres$.next(f);

        this.adresseSvc.inverser(lat, lng).pipe(takeUntil(this.destroy$)).subscribe(label => {
          if (label) this.adresseAffichee.set(label);
        });
      },
      () => this.erreur.set('Impossible d\'obtenir votre position.')
    );
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}