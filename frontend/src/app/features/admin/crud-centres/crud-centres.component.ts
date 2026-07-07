// features/admin/crud-centres/crud-centres.component.ts
import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule }      from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { CentreService, CentreType, CentreUnion } from '../../../core/services/centre.service';

@Component({
  selector  : 'app-crud-centres',
  standalone: true,
  imports   : [CommonModule, ReactiveFormsModule],
  template  : `
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Centres de santé & d'analyse</h4>
        <p class="text-muted small mb-0">{{ centres().length }} centre(s) de type {{ typeActif() === 'sante' ? 'santé' : 'analyse' }}</p>
      </div>
      @if (!showForm()) {
        <button class="btn btn-primary btn-sm" (click)="ouvrir(null)">
          <i class="bi bi-plus-lg me-1"></i>Nouveau centre
        </button>
      }
    </div>

    <!-- Onglets type -->
    <ul class="nav nav-tabs mb-4">
      <li class="nav-item">
        <button class="nav-link" [class.active]="typeActif() === 'sante'" (click)="setType('sante')">
          <i class="bi bi-heart-pulse me-1"></i>Santé
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" [class.active]="typeActif() === 'analyse'" (click)="setType('analyse')">
          <i class="bi bi-eyedropper me-1"></i>Analyse
        </button>
      </li>
    </ul>

    @if (erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-triangle"></i>{{ erreur() }}
      </div>
    }

    <!-- Formulaire -->
    @if (showForm()) {
      <div class="card border-0 shadow-sm p-4 mb-4">
        <h5 class="fw-semibold mb-3">
          {{ editing() ? 'Modifier le centre' : 'Nouveau centre' }}
          <span class="badge bg-secondary ms-2 fw-normal" style="font-size:.7rem;">
            {{ typeActif() === 'sante' ? 'Santé' : 'Analyse' }}
          </span>
        </h5>
        <form [formGroup]="form" (ngSubmit)="sauvegarder()">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Nom <span class="text-danger">*</span></label>
              <input class="form-control" formControlName="nom" placeholder="Centre médical…">
              @if (form.get('nom')?.invalid && form.get('nom')?.touched) {
                <div class="text-danger small mt-1">Le nom est requis</div>
              }
            </div>

            <div class="col-md-6">
              <label class="form-label">Adresse</label>
              <input class="form-control" formControlName="adresse" placeholder="8 avenue de la Santé">
            </div>

            <div class="col-md-6">
              <label class="form-label">Téléphone</label>
              <input class="form-control" formControlName="telephone" placeholder="01 23 45 67 89">
            </div>

            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" formControlName="email"
                     placeholder="contact@centre.fr"
                     [class.form-control]="true"
                     [class.is-invalid]="form.get('email')?.invalid && form.get('email')?.touched">
              @if (form.get('email')?.errors?.['email'] && form.get('email')?.touched) {
                <div class="invalid-feedback">Format d'email invalide (ex : contact&#64;centre.fr)</div>
              }
            </div>

            @if (typeActif() === 'sante') {
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" formControlName="description" rows="2"
                          placeholder="Présentation du centre…"></textarea>
              </div>

              <div class="col-md-6">
                <label class="form-label">Spécialités</label>
                <input class="form-control" formControlName="specialites"
                       placeholder="Cardiologie, Dermatologie…">
                <div class="form-text">Séparées par des virgules</div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Services</label>
                <input class="form-control" formControlName="services"
                       placeholder="Urgences, Maternité…">
                <div class="form-text">Séparés par des virgules</div>
              </div>
            }

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="actifCentreCheck"
                       formControlName="actif">
                <label class="form-check-label" for="actifCentreCheck">Centre actif</label>
              </div>
            </div>

          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary" [disabled]="form.invalid || saving()">
              @if (saving()) { <span class="spinner-border spinner-border-sm me-1"></span> }
              <i class="bi bi-check-lg me-1"></i>Sauvegarder
            </button>
            <button type="button" class="btn btn-outline-secondary" (click)="annuler()">Annuler</button>
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
              @if (centres().length === 0) {
                <tr>
                  <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-building fs-2 d-block mb-2"></i>
                    Aucun centre {{ typeActif() === 'sante' ? 'de santé' : "d'analyse" }} enregistré
                  </td>
                </tr>
              }
              @for (c of centres(); track c.id) {
                <tr>
                  <td class="text-muted small">{{ c.id }}</td>
                  <td class="fw-semibold">{{ c.nom }}</td>
                  <td class="small text-muted">{{ c.adresse ?? '—' }}</td>
                  <td class="small">{{ c.telephone ?? '—' }}</td>
                  <td class="small">{{ c.email ?? '—' }}</td>
                  <td>
                    <span class="badge rounded-pill"
                          [class.bg-success]="c.actif"
                          [class.bg-secondary]="!c.actif">
                      {{ c.actif ? 'Actif' : 'Inactif' }}
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" title="Modifier"
                              (click)="ouvrir(c)">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger" title="Supprimer"
                              (click)="supprimer(c)">
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
export class CrudCentresComponent implements OnInit {

  svc  = inject(CentreService);
  fb   = inject(FormBuilder);

  typeActif  = signal<CentreType>('sante');
  centres    = signal<CentreUnion[]>([]);
  showForm   = signal<boolean>(false);
  editing    = signal<CentreUnion | null>(null);
  chargement = signal<boolean>(false);
  saving     = signal<boolean>(false);
  erreur     = signal<string | null>(null);

  form = this.fb.group({
    nom        : ['', Validators.required],
    adresse    : [''],
    telephone  : [''],
    email      : ['', Validators.email],
    description: [''],
    specialites: [''],
    services   : [''],
    actif      : [true],
  });

  ngOnInit(): void { this.charger(); }

  setType(type: CentreType): void {
    this.typeActif.set(type);
    this.annuler();
    this.charger();
  }

  charger(): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.svc.lister(this.typeActif()).subscribe({
      next    : res => this.centres.set(res.data),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement'),
      complete: () => this.chargement.set(false),
    });
  }

  ouvrir(c: CentreUnion | null): void {
    this.editing.set(c);
    this.erreur.set(null);
    const cs = c as any;
    this.form.reset({
      nom        : cs?.nom         ?? '',
      adresse    : cs?.adresse     ?? '',
      telephone  : cs?.telephone   ?? '',
      email      : cs?.email       ?? '',
      description: cs?.description ?? '',
      specialites: cs?.specialites ?? '',
      services   : cs?.services    ?? '',
      actif      : cs?.actif       ?? true,
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
    const raw  = this.form.value;
    const type = this.typeActif();

    // Filtrer les champs santé-only si type=analyse
    const data: Record<string, unknown> = {
      nom      : raw['nom'],
      adresse  : raw['adresse'],
      telephone: raw['telephone'],
      email    : raw['email'],
      actif    : raw['actif'],
    };
    if (type === 'sante') {
      data['description'] = raw['description'];
      data['specialites'] = raw['specialites'];
      data['services']    = raw['services'];
    }

    const c   = this.editing();
    const req = c
      ? this.svc.modifier(type, c.id, data)
      : this.svc.creer(type, data);

    req.subscribe({
      next: () => {
        this.annuler();
        this.charger();
      },
      error   : err => { this.erreur.set(err.error?.error ?? 'Erreur lors de la sauvegarde'); this.saving.set(false); },
      complete: () => this.saving.set(false),
    });
  }

  supprimer(c: CentreUnion): void {
    if (!confirm(`Supprimer le centre « ${c.nom} » ? Cette action est irréversible.`)) return;
    this.erreur.set(null);
    this.svc.supprimer(this.typeActif(), c.id).subscribe({
      next    : () => this.charger(),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur lors de la suppression'),
    });
  }
}
