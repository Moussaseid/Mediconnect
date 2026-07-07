// src/app/features/auth/register/register-step1.component.ts
import { Component, Input, Output, EventEmitter, OnInit } from '@angular/core';
import { CommonModule }          from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';

export interface Step1Data { nom: string; prenom: string; }

/**
 * Étape 1/3 — Identité (nom + prénom)
 * @Input()  valeurs  — données pré-remplies si l'utilisateur revient en arrière
 * @Output() suivant  — émis avec Step1Data quand le formulaire est valide
 */
@Component({
  selector   : 'app-register-step1',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule],
  template   : `
    <form [formGroup]="form" (ngSubmit)="soumettre()" novalidate>
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label fw-semibold">Nom</label>
          <input type="text" class="form-control"
            [class.is-invalid]="nom.invalid && nom.touched"
            formControlName="nom" autocomplete="family-name" />
          @if (nom.invalid && nom.touched) {
            <div class="invalid-feedback">2 caractères minimum.</div>
          }
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold">Prénom</label>
          <input type="text" class="form-control"
            [class.is-invalid]="prenom.invalid && prenom.touched"
            formControlName="prenom" autocomplete="given-name" />
          @if (prenom.invalid && prenom.touched) {
            <div class="invalid-feedback">2 caractères minimum.</div>
          }
        </div>
      </div>

      <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-primary px-4">
          Suivant <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </form>
  `,
})
export class RegisterStep1Component implements OnInit {
  @Input()  valeurs: Step1Data = { nom: '', prenom: '' };
  @Output() suivant = new EventEmitter<Step1Data>();

  form!: FormGroup;

  constructor(private fb: FormBuilder) {}

  ngOnInit(): void {
    this.form = this.fb.group({
      nom   : [this.valeurs.nom,    [Validators.required, Validators.minLength(2)]],
      prenom: [this.valeurs.prenom, [Validators.required, Validators.minLength(2)]],
    });
  }

  get nom()    { return this.form.get('nom')!; }
  get prenom() { return this.form.get('prenom')!; }

  soumettre(): void {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.suivant.emit(this.form.value as Step1Data);
  }
}
