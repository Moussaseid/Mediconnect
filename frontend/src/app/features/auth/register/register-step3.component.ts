// src/app/features/auth/register/register-step3.component.ts
import { Component, Input, Output, EventEmitter, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule }          from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup } from '@angular/forms';

export interface Step3Data { telephone: string; ville: string; }

/**
 * Étape 3/3 — Coordonnées (téléphone + ville, optionnels) + bouton de soumission
 * @Input()  valeurs   — pré-remplissage
 * @Input()  loading   — désactive le bouton pendant l'appel API
 * @Input()  erreur    — message d'erreur retourné par l'API
 * @Output() valide    — émis avec Step3Data quand l'utilisateur clique "Créer mon compte"
 * @Output() precedent — émis sur "Retour"
 */
@Component({
  selector   : 'app-register-step3',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule],
  template   : `
    @if (erreur) {
      <div class="alert alert-danger py-2 mb-3">{{ erreur }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="soumettre()" novalidate>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label fw-semibold">
            Téléphone <span class="text-muted fw-normal">(optionnel)</span>
          </label>
          <input type="tel" class="form-control"
            formControlName="telephone" autocomplete="tel" />
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold">
            Ville <span class="text-muted fw-normal">(optionnel)</span>
          </label>
          <input type="text" class="form-control"
            formControlName="ville" autocomplete="address-level2" />
        </div>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary" (click)="precedent.emit()" [disabled]="loading">
          <i class="bi bi-arrow-left me-1"></i> Retour
        </button>
        <button type="submit" class="btn btn-success px-4" [disabled]="loading">
          @if (loading) {
            <span class="spinner-border spinner-border-sm me-2"></span>Création...
          } @else {
            <i class="bi bi-check-circle me-1"></i>Créer mon compte
          }
        </button>
      </div>
    </form>
  `,
})
export class RegisterStep3Component implements OnInit, OnChanges {
  @Input()  valeurs: Step3Data = { telephone: '', ville: '' };
  @Input()  loading = false;
  @Input()  erreur: string | null = null;
  @Output() valide    = new EventEmitter<Step3Data>();
  @Output() precedent = new EventEmitter<void>();

  form!: FormGroup;

  constructor(private fb: FormBuilder) {}

  ngOnInit(): void {
    this.form = this.fb.group({
      telephone: [this.valeurs.telephone],
      ville    : [this.valeurs.ville],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    // Mettre à jour les valeurs si le parent change valeurs
    if (changes['valeurs'] && this.form) {
      this.form.patchValue(this.valeurs);
    }
  }

  soumettre(): void {
    this.valide.emit(this.form.value as Step3Data);
  }
}
