// src/app/features/auth/forgot-password/forgot-password.component.ts
import { Component, signal }       from '@angular/core';
import { CommonModule }            from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { RouterLink }              from '@angular/router';
import { HttpErrorResponse }       from '@angular/common/http';
import { PasswordService }         from '../../../core/services/password.service';

/**
 * ForgotPasswordComponent — Demande de réinitialisation de mot de passe.
 * Route : /forgot-password (guest only)
 */
@Component({
  selector   : 'app-forgot-password',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule, RouterLink],
  template   : `
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light py-4">
      <div class="card shadow-sm" style="width:100%;max-width:440px">
        <div class="card-body p-4">

          <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center
                        justify-content-center mb-3" style="width:56px;height:56px">
              <i class="bi bi-lock-fill text-primary fs-4"></i>
            </div>
            <h1 class="h5 fw-bold">Mot de passe oublié ?</h1>
            <p class="text-muted small">
              Entrez votre email. Vous recevrez un lien de réinitialisation.
            </p>
          </div>

          @if (succes()) {
            <div class="alert alert-success">
              <i class="bi bi-check-circle me-1"></i>
              {{ succes() }}
              @if (tokenDev()) {
                <hr>
                <small class="text-muted">
                  <strong>Mode dev — token :</strong>
                  <code class="d-block mt-1 text-break">{{ tokenDev() }}</code>
                  <a [routerLink]="['/reset-password']" [queryParams]="{token: tokenDev()}"
                     class="btn btn-sm btn-outline-primary mt-2">
                    Utiliser ce token →
                  </a>
                </small>
              }
            </div>
          } @else {
            @if (erreur()) {
              <div class="alert alert-danger py-2">{{ erreur() }}</div>
            }

            <form [formGroup]="form" (ngSubmit)="soumettre()" novalidate>
              <label class="form-label fw-semibold">Adresse email</label>
              <input type="email" class="form-control"
                [class.is-invalid]="email.invalid && email.touched"
                formControlName="email" autocomplete="email" />
              @if (email.invalid && email.touched) {
                <div class="invalid-feedback">Email invalide.</div>
              }

              <button type="submit" class="btn btn-primary w-100 mt-3" [disabled]="loading()">
                @if (loading()) {
                  <span class="spinner-border spinner-border-sm me-2"></span>Envoi...
                } @else {
                  Envoyer le lien
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
export class ForgotPasswordComponent {
  form    : FormGroup;
  loading  = signal(false);
  erreur   = signal<string | null>(null);
  succes   = signal<string | null>(null);
  tokenDev = signal<string | null>(null);

  get email() { return this.form.get('email')!; }

  constructor(private fb: FormBuilder, private pwd: PasswordService) {
    this.form = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
    });
  }

  soumettre(): void {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.loading.set(true);
    this.erreur.set(null);

    this.pwd.forgot(this.form.value['email']).subscribe({
      next: res => {
        this.loading.set(false);
        this.succes.set(res.data.message);
        if (res.data.token) this.tokenDev.set(res.data.token);
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.erreur.set(err.error?.error ?? 'Erreur lors de la demande');
      },
    });
  }
}
