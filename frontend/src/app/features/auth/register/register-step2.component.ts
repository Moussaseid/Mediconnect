// src/app/features/auth/register/register-step2.component.ts
import { Component, Input, Output, EventEmitter, OnInit } from '@angular/core';
import { CommonModule }          from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators,
         AbstractControl, ValidationErrors } from '@angular/forms';

export interface Step2Data { email: string; password: string; }

function passwordsIdentiques(group: AbstractControl): ValidationErrors | null {
  const pw  = group.get('password')?.value;
  const pw2 = group.get('passwordConfirm')?.value;
  return pw === pw2 ? null : { passwordsMismatch: true };
}

/**
 * Étape 2/3 — Sécurité (email + mot de passe)
 * @Input()  valeurs   — pré-remplissage si retour arrière
 * @Output() suivant   — émis avec Step2Data quand le formulaire est valide
 * @Output() precedent — émis quand l'utilisateur clique "Retour"
 */
@Component({
  selector   : 'app-register-step2',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule],
  template   : `
    <form [formGroup]="form" (ngSubmit)="soumettre()" novalidate>

      <div class="mb-3">
        <label class="form-label fw-semibold">Adresse email</label>
        <input type="email" class="form-control"
          [class.is-invalid]="email.invalid && email.touched"
          formControlName="email" autocomplete="email" />
        @if (email.invalid && email.touched) {
          <div class="invalid-feedback">
            @if (email.errors?.['required']) { Email requis. }
            @if (email.errors?.['email'])    { Format invalide. }
          </div>
        }
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Mot de passe</label>
        <input type="password" class="form-control"
          [class.is-invalid]="password.invalid && password.touched"
          formControlName="password" autocomplete="new-password" />
        @if (password.invalid && password.touched) {
          <div class="invalid-feedback">8 caractères minimum.</div>
        }
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Confirmer le mot de passe</label>
        <input type="password" class="form-control"
          [class.is-invalid]="passwordConfirm.touched && form.errors?.['passwordsMismatch']"
          formControlName="passwordConfirm" autocomplete="new-password" />
        @if (passwordConfirm.touched && form.errors?.['passwordsMismatch']) {
          <div class="text-danger small mt-1">Les mots de passe ne correspondent pas.</div>
        }
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-outline-secondary" (click)="precedent.emit()">
          <i class="bi bi-arrow-left me-1"></i> Retour
        </button>
        <button type="submit" class="btn btn-primary px-4">
          Suivant <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </form>
  `,
})
export class RegisterStep2Component implements OnInit {
  @Input()  valeurs: Step2Data = { email: '', password: '' };
  @Output() suivant   = new EventEmitter<Step2Data>();
  @Output() precedent = new EventEmitter<void>();

  form!: FormGroup;

  constructor(private fb: FormBuilder) {}

  ngOnInit(): void {
    this.form = this.fb.group({
      email          : [this.valeurs.email,    [Validators.required, Validators.email]],
      password       : [this.valeurs.password, [Validators.required, Validators.minLength(8)]],
      passwordConfirm: ['',                     Validators.required],
    }, { validators: passwordsIdentiques });
  }

  get email()           { return this.form.get('email')!; }
  get password()        { return this.form.get('password')!; }
  get passwordConfirm() { return this.form.get('passwordConfirm')!; }

  soumettre(): void {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    const { passwordConfirm: _, ...data } = this.form.value;
    this.suivant.emit(data as Step2Data);
  }
}
