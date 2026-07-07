// features/admin/crud-pharmacies/crud-pharmacies.component.ts
import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule }      from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { HttpClient }        from '@angular/common/http';
import { PharmacieService }  from '../../../core/services/pharmacie.service';
import { IPharmacie, IApiResponse } from '../../../core/models/interfaces';
import { environment }       from '../../../../environments/environment';

@Component({
  selector  : 'app-crud-pharmacies',
  standalone: true,
  imports   : [CommonModule, ReactiveFormsModule],
  template  : `
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Pharmacies</h4>
        <p class="text-muted small mb-0">{{ pharmacies().length }} pharmacie(s) enregistrée(s)</p>
      </div>
      @if (!showForm()) {
        <button class="btn btn-primary btn-sm" (click)="ouvrir(null)">
          <i class="bi bi-plus-lg me-1"></i>Nouvelle pharmacie
        </button>
      }
    </div>

    @if (erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-triangle"></i>{{ erreur() }}
      </div>
    }

    <!-- Formulaire create / edit -->
    @if (showForm()) {
      <div class="card border-0 shadow-sm p-4 mb-4">
        <h5 class="fw-semibold mb-3">
          {{ editing() ? 'Modifier la pharmacie' : 'Nouvelle pharmacie' }}
        </h5>
        <form [formGroup]="form" (ngSubmit)="sauvegarder()">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Nom <span class="text-danger">*</span></label>
              <input class="form-control" formControlName="nom" placeholder="Pharmacie du Centre">
              @if (form.get('nom')?.invalid && form.get('nom')?.touched) {
                <div class="text-danger small mt-1">Le nom est requis</div>
              }
            </div>

            <div class="col-md-6">
              <label class="form-label">Adresse</label>
              <input class="form-control" formControlName="adresse" placeholder="12 rue de la Paix">
            </div>

            <div class="col-md-4">
              <label class="form-label">Code postal</label>
              <input class="form-control" formControlName="codePostal" placeholder="75001">
            </div>

            <div class="col-md-4">
              <label class="form-label">Ville</label>
              <input class="form-control" formControlName="ville" placeholder="Paris">
            </div>

            <div class="col-md-4">
              <label class="form-label">Téléphone</label>
              <input class="form-control" formControlName="telephone" placeholder="01 23 45 67 89">
            </div>

            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" formControlName="email"
                     placeholder="contact@pharmacie.fr"
                     [class.form-control]="true"
                     [class.is-invalid]="form.get('email')?.invalid && form.get('email')?.touched">
              @if (form.get('email')?.errors?.['email'] && form.get('email')?.touched) {
                <div class="invalid-feedback">Format d'email invalide (ex : contact&#64;pharmacie.fr)</div>
              }
            </div>

            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="actifCheck"
                       formControlName="actif">
                <label class="form-check-label" for="actifCheck">Pharmacie active</label>
              </div>
            </div>

          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary" [disabled]="form.invalid || saving()">
              @if (saving()) { <span class="spinner-border spinner-border-sm me-1"></span> }
              <i class="bi bi-check-lg me-1"></i>Sauvegarder
            </button>
            <button type="button" class="btn btn-outline-secondary" (click)="annuler()">
              Annuler
            </button>
          </div>
        </form>
      </div>
    }

    <!-- Liste -->
    @if (chargement()) {
      <div class="placeholder-glow">
        @for (i of [1,2,3]; track i) {
          <div class="placeholder w-100 mb-2" style="height:52px;border-radius:8px;"></div>
        }
      </div>
    } @else {
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Adresse</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @if (pharmacies().length === 0) {
                <tr>
                  <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-hospital fs-2 d-block mb-2"></i>
                    Aucune pharmacie enregistrée
                  </td>
                </tr>
              }
              @for (p of pharmacies(); track p.id) {
                <tr>
                  <td class="text-muted small">{{ p.id }}</td>
                  <td class="fw-semibold">{{ p.nom }}</td>
                  <td class="small text-muted">
                    {{ p.adresse ?? '' }}{{ p.ville ? ', ' + p.ville : '' }}
                  </td>
                  <td class="small">{{ p.telephone ?? '—' }}</td>
                  <td class="small">{{ p.email ?? '—' }}</td>
                  <td>
                    <span class="badge rounded-pill"
                          [class.bg-success]="p.actif"
                          [class.bg-secondary]="!p.actif">
                      {{ p.actif ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" title="Modifier"
                              (click)="ouvrir(p)">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger" title="Supprimer"
                              (click)="supprimer(p)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>
    }
  `,
})
export class CrudPharmaciesComponent implements OnInit {

  private http = inject(HttpClient);
  private api  = environment.apiUrl;
  svc          = inject(PharmacieService);
  fb           = inject(FormBuilder);

  pharmacies = signal<IPharmacie[]>([]);
  showForm   = signal<boolean>(false);
  editing    = signal<IPharmacie | null>(null);
  chargement = signal<boolean>(false);
  saving     = signal<boolean>(false);
  erreur     = signal<string | null>(null);

  form = this.fb.group({
    nom       : ['', Validators.required],
    adresse   : [''],
    codePostal: [''],
    ville     : [''],
    telephone : [''],
    email     : ['', Validators.email],
    actif     : [true],
  });

  ngOnInit(): void { this.charger(); }

  charger(): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http.get<IApiResponse<IPharmacie[]>>(`${this.api}/pharmacies`).subscribe({
      next    : res => this.pharmacies.set(res.data),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement'),
      complete: () => this.chargement.set(false),
    });
  }

  ouvrir(p: IPharmacie | null): void {
    this.editing.set(p);
    this.erreur.set(null);
    this.form.reset({
      nom       : p?.nom        ?? '',
      adresse   : p?.adresse    ?? '',
      codePostal: p?.codePostal ?? '',
      ville     : p?.ville      ?? '',
      telephone : p?.telephone  ?? '',
      email     : p?.email      ?? '',
      actif     : p?.actif      ?? true,
    });
    this.showForm.set(true);
  }

  annuler(): void {
    this.showForm.set(false);
    this.editing.set(null);
    this.erreur.set(null);
  }

  sauvegarder(): void {
    if (this.form.invalid) return;
    this.saving.set(true);
    this.erreur.set(null);
    const data = this.form.value as Partial<IPharmacie>;
    const p    = this.editing();

    const req = p
      ? this.svc.modifierPharmacie(p.id, data)
      : this.svc.creerPharmacie(data);

    req.subscribe({
      next: () => {
        this.annuler();
        this.charger();
      },
      error   : err => { this.erreur.set(err.error?.error ?? 'Erreur lors de la sauvegarde'); this.saving.set(false); },
      complete: () => this.saving.set(false),
    });
  }

  supprimer(p: IPharmacie): void {
    if (!confirm(`Supprimer la pharmacie « ${p.nom} » ? Cette action est irréversible.`)) return;
    this.erreur.set(null);
    this.svc.supprimerPharmacie(p.id).subscribe({
      next    : () => this.charger(),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur lors de la suppression'),
    });
  }
}