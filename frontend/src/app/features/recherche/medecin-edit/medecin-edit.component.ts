import { ChangeDetectionStrategy, Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule }  from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Location } from '@angular/common';
import { Subject, switchMap, catchError, of, takeUntil, forkJoin } from 'rxjs';

import { MedecinService, IHoraireSaisie } from '../../../core/services/medecin.service';
import { AuthService }    from '../../../core/services/auth.service';
import { AdresseAutocompleteComponent } from '../adresse-autocomplete/adresse-autocomplete.component';
import { IAdresseSuggestion } from '../../../core/services/adresse.service';
import { IMedecin, IMedecinUpdateRequest, IIndisponibilite } from '../../../core/models/interfaces';

const JOURS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

interface IJourFormulaire {
  jourSemaine: number;
  nom: string;
  actif: boolean;
  heureDebut: string;
  heureFin: string;
}

@Component({
  selector   : 'app-medecin-edit',
  standalone : true,
  imports         : [CommonModule, FormsModule, RouterLink, AdresseAutocompleteComponent],
  templateUrl     : './medecin-edit.component.html',
  changeDetection : ChangeDetectionStrategy.OnPush,
})
export class MedecinEditComponent implements OnInit, OnDestroy {

  private readonly route      = inject(ActivatedRoute);
  private readonly router     = inject(Router);
  private readonly location   = inject(Location);
  private readonly medecinSvc = inject(MedecinService);
  readonly auth                = inject(AuthService);
  private readonly destroy$   = new Subject<void>();

  medecin      = signal<IMedecin | null>(null);
  chargement   = signal(true);
  sauvegarde   = signal(false);
  erreur       = signal<string | null>(null);
  succes       = signal<string | null>(null);

  // ── Horaires ─────────────────────────────────────────────────────────────
  jours              = signal<IJourFormulaire[]>([]);
  horairesSauvegarde = signal(false);
  horairesErreur     = signal<string | null>(null);
  horairesSucces     = signal<string | null>(null);

  // ── Indisponibilités ─────────────────────────────────────────────────────
  indisponibilites   = signal<IIndisponibilite[]>([]);
  nouvelleIndispo    = { dateDebut: '', dateFin: '', motif: '' };
  indispoSauvegarde  = signal(false);
  indispoErreur      = signal<string | null>(null);

  form: IMedecinUpdateRequest = {
    adresseCabinet : undefined,
    dureeRdv       : undefined,
    telephone      : undefined,
    latitude       : undefined,
    longitude      : undefined,
  };

  ngOnInit(): void {
    this.route.paramMap.pipe(
      switchMap(p => {
        const id = Number(p.get('id'));
        if (!id) { this.erreur.set('Identifiant invalide.'); this.chargement.set(false); return of(null); }
        return forkJoin({
          medecin        : this.medecinSvc.getById(id).pipe(catchError(() => of(null))),
          indisponibilites: this.medecinSvc.listerIndisponibilites(id).pipe(catchError(() => of(null))),
        });
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.chargement.set(false);
      if (!res) return;

      if (res.medecin) {
        const m = res.medecin.data;
        // Vérifier que l'utilisateur connecté est bien ce médecin ou un admin
        const user = this.auth.user();
        if (user?.role !== 'admin' && user?.id !== m.utilisateurId) {
          this.router.navigate(['/recherche/medecin', m.id]);
          return;
        }
        this.medecin.set(m);
        this.form = {
          adresseCabinet : m.adresseCabinet,
          dureeRdv       : m.dureeRdv,
          telephone      : m.telephone,
          latitude       : m.latitude,
          longitude      : m.longitude,
        };
        this.jours.set(this.construireJours(m));
      }
      if (res.indisponibilites) {
        this.indisponibilites.set(res.indisponibilites.data);
      }
    });
  }

  retour(): void { this.location.back(); }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  // ── Adresse (autocomplétion → coordonnées dérivées automatiquement) ───────
  onAdresseSelectionnee(s: IAdresseSuggestion): void {
    this.form.adresseCabinet = s.label;
    this.form.latitude       = s.lat;
    this.form.longitude      = s.lng;
  }

  sauvegarder(): void {
    const m = this.medecin();
    if (!m) return;
    this.sauvegarde.set(true);
    this.erreur.set(null);
    this.succes.set(null);

    this.medecinSvc.mettreAJour(m.id, this.form).pipe(
      catchError(err => {
        this.erreur.set(err.error?.error ?? 'Erreur lors de la sauvegarde.');
        this.sauvegarde.set(false);
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.sauvegarde.set(false);
      if (res) {
        this.medecin.set(res.data);
        this.succes.set('Profil mis à jour avec succès.');
      }
    });
  }

  // ── Horaires ─────────────────────────────────────────────────────────────
  private construireJours(m: IMedecin): IJourFormulaire[] {
    return [1, 2, 3, 4, 5, 6, 7].map(jour => {
      const existant = m.horaires?.find(h => h.jourSemaine === jour);
      return {
        jourSemaine: jour,
        nom        : JOURS[jour],
        actif      : !!existant,
        heureDebut : existant?.heureDebut.substring(0, 5) ?? '09:00',
        heureFin   : existant?.heureFin.substring(0, 5)   ?? '17:00',
      };
    });
  }

  sauvegarderHoraires(): void {
    const m = this.medecin();
    if (!m) return;

    const payload: IHoraireSaisie[] = this.jours()
      .filter(j => j.actif)
      .map(j => ({ jourSemaine: j.jourSemaine, heureDebut: j.heureDebut, heureFin: j.heureFin }));

    for (const j of payload) {
      if (j.heureDebut >= j.heureFin) {
        this.horairesErreur.set(`Le jour ${JOURS[j.jourSemaine]} : l'heure de début doit précéder l'heure de fin.`);
        return;
      }
    }

    this.horairesSauvegarde.set(true);
    this.horairesErreur.set(null);
    this.horairesSucces.set(null);

    this.medecinSvc.mettreAJourHoraires(m.id, payload).pipe(
      catchError(err => {
        this.horairesErreur.set(err.error?.error ?? 'Erreur lors de la sauvegarde des horaires.');
        this.horairesSauvegarde.set(false);
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.horairesSauvegarde.set(false);
      if (res) {
        this.medecin.set(res.data);
        this.horairesSucces.set('Horaires mis à jour avec succès.');
      }
    });
  }

  // ── Indisponibilités ─────────────────────────────────────────────────────
  ajouterIndisponibilite(): void {
    const m = this.medecin();
    if (!m) return;

    const { dateDebut, dateFin, motif } = this.nouvelleIndispo;
    if (!dateDebut || !dateFin) {
      this.indispoErreur.set('Les dates de début et de fin sont requises.');
      return;
    }
    if (dateDebut >= dateFin) {
      this.indispoErreur.set('La date de début doit précéder la date de fin.');
      return;
    }

    this.indispoSauvegarde.set(true);
    this.indispoErreur.set(null);

    this.medecinSvc.creerIndisponibilite(m.id, { dateDebut, dateFin, motif: motif || undefined }).pipe(
      catchError(err => {
        this.indispoErreur.set(err.error?.error ?? 'Erreur lors de l\'ajout.');
        this.indispoSauvegarde.set(false);
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.indispoSauvegarde.set(false);
      if (res) {
        this.indisponibilites.set(res.data);
        this.nouvelleIndispo = { dateDebut: '', dateFin: '', motif: '' };
      }
    });
  }

  supprimerIndisponibilite(indispoId: number): void {
    const m = this.medecin();
    if (!m) return;

    this.medecinSvc.supprimerIndisponibilite(m.id, indispoId).pipe(
      catchError(() => {
        this.indispoErreur.set('Erreur lors de la suppression.');
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res) this.indisponibilites.set(res.data);
    });
  }
}