// src/app/features/auth/reset-password/reset-password.component.ts
import { Component, signal, OnInit }   from '@angular/core';
import { CommonModule }                from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators,
         AbstractControl, ValidationErrors } from '@angular/forms';
import { RouterLink, ActivatedRoute, Router } from '@angular/router';
import { HttpErrorResponse }           from '@angular/common/http';
import { PasswordService }             from '../../../core/services/password.service';

function passwordsIdentiques(group: AbstractControl): ValidationErrors | null {
  return group.get('password')?.value === group.get('confirm')?.value
    ? null
    : { mismatch: true };
}

/**
 * ResetPasswordComponent — Saisie du nouveau mot de passe via token URL.
 * Route : /reset-password?token=<hex64>
 */
@Component({
  selector   : 'app-reset-password',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule, RouterLink],
  template   : `
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
      <div class="card shadow-sm" style="width:100%;max-width:440px">
        <div class="card-body p-4">

          <div class="text-center mb-4">
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center
                        justify-content-center mb-3" style="width:56px;height:56px">
              <i class="bi bi-shield-lock-fill text-success fs-4"></i>
            </div>
            <h1 class="h5 fw-bold">Nouveau mot de passe</h1>
          </div>

          @if (!token) {
            <div class="alert alert-danger">
              Lien invalide. <a routerLink="/forgot-password">Faire une nouvelle demande</a>.
            </div>
          } @else if (succes()) {
            <div class="alert alert-success">
              <i class="bi bi-check-circle me-1"></i>
              Mot de passe réinitialisé !
              <a routerLink="/login" class="d-block mt-2">Se connecter →</a>
            </div>
          } @else {
            @if (erreur()) {
              <div class="alert alert-danger py-2">{{ erreur() }}</div>
            }

            <form [formGroup]="form" (ngSubmit)="soumettre()" novalidate>
              <div class="mb-3">
                <label class="form-label fw-semibold">Nouveau mot de passe</label>
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
                  [class.is-invalid]="confirm.touched && form.errors?.['mismatch']"
                  formControlName="confirm" autocomplete="new-password" />
                @if (confirm.touched && form.errors?.['mismatch']) {
                  <div class="text-danger small mt-1">Les mots de passe ne correspondent pas.</div>
                }
              </div>

              <button type="submit" class="btn btn-success w-100" [disabled]="loading()">
                @if (loading()) {
                  <span class="spinner-border spinner-border-sm me-2"></span>Mise à jour...
                } @else {
                  Enregistrer le mot de passe
                }
              </button>
            </form>
          }

          <hr class="my-3">
          <p class="text-center small mb-0">
            <a routerLink="/login">Retour à la connexion</a>
          </p>

        </div>
      </div>
    </div>
  `,
})
export class ResetPasswordComponent implements OnInit {
  form   : FormGroup;
  token  : string | null = null;
  loading = signal(false);
  erreur  = signal<string | null>(null);
  succes  = signal(false);

  get password() { return this.form.get('password')!; }
  get confirm()  { return this.form.get('confirm')!; }

  constructor(
    private fb    : FormBuilder,
    private pwd   : PasswordService,
    private route : ActivatedRoute,
  ) {
    this.form = this.fb.group({
      password: ['', [Validators.required, Validators.minLength(8)]],
      confirm : ['',  Validators.required],
    }, { validators: passwordsIdentiques });
  }

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token');
  }

  soumettre(): void {
    if (this.form.invalid || !this.token) { this.form.markAllAsTouched(); return; }
    this.loading.set(true);
    this.erreur.set(null);

    this.pwd.reset(this.token, this.form.value['password']).subscribe({
      next: () => {
        this.loading.set(false);
        this.succes.set(true);
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.erreur.set(err.error?.error ?? 'Token invalide ou expiré');
      },
    });
  }
}
