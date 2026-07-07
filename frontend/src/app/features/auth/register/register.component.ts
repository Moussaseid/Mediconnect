// src/app/features/auth/register/register.component.ts
import { Component, signal }       from '@angular/core';
import { CommonModule }            from '@angular/common';
import { RouterLink }              from '@angular/router';
import { HttpErrorResponse }       from '@angular/common/http';
import { AuthService }             from '../../../core/services/auth.service';
import { RegisterStep1Component, Step1Data } from './register-step1.component';
import { RegisterStep2Component, Step2Data } from './register-step2.component';
import { RegisterStep3Component, Step3Data } from './register-step3.component';

/**
 * RegisterComponent — Inscription en 3 étapes
 *
 * Étape 1 : Identité   (nom, prénom)           → RegisterStep1Component
 * Étape 2 : Sécurité   (email, mot de passe)   → RegisterStep2Component
 * Étape 3 : Coordonnées (téléphone, ville)     → RegisterStep3Component
 *
 * Ce composant parent collecte les données étape par étape
 * et soumet l'ensemble à l'API lors de la validation de l'étape 3.
 */
@Component({
  selector   : 'app-register',
  standalone : true,
  imports    : [
    CommonModule,
    RouterLink,
    RegisterStep1Component,
    RegisterStep2Component,
    RegisterStep3Component,
  ],
  templateUrl: './register.component.html',
})
export class RegisterComponent {

  etape   = signal<1 | 2 | 3>(1);
  loading = signal(false);
  erreur  = signal<string | null>(null);

  // Données accumulées étape par étape
  step1: Step1Data = { nom: '', prenom: '' };
  step2: Step2Data = { email: '', password: '' };
  step3: Step3Data = { telephone: '', ville: '' };

  constructor(private auth: AuthService) {}

  // ── Handlers des @Output des sous-composants ──────────────────────────────

  etape1Valide(data: Step1Data): void {
    this.step1 = data;
    this.etape.set(2);
  }

  etape2Valide(data: Step2Data): void {
    this.step2 = data;
    this.etape.set(3);
  }

  etape3Valide(data: Step3Data): void {
    this.step3 = data;
    this.soumettre();
  }

  retourEtape1(): void { this.etape.set(1); }
  retourEtape2(): void { this.etape.set(2); }

  // ── Soumission finale ─────────────────────────────────────────────────────
  private soumettre(): void {
    this.loading.set(true);
    this.erreur.set(null);

    const payload = {
      nom      : this.step1.nom,
      prenom   : this.step1.prenom,
      email    : this.step2.email,
      password : this.step2.password,
      telephone: this.step3.telephone || undefined,
      ville    : this.step3.ville     || undefined,
    };

    this.auth.register(payload).subscribe({
      next : () => {
        this.loading.set(false);
        this.auth.redirigerSelonRole();
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.erreur.set(err.error?.error ?? 'Erreur lors de l\'inscription');
        // Retourner à l'étape 2 si l'email est déjà pris
        if (err.status === 409) this.etape.set(2);
      },
    });
  }
}
