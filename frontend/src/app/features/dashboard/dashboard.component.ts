// src/app/features/dashboard/dashboard.component.ts
// Placeholder Sprint 1 — affiché après connexion pour tous les rôles
import { Component, inject } from '@angular/core';
import { CommonModule }      from '@angular/common';
import { RouterLink }        from '@angular/router';
import { AuthService }       from '../../core/services/auth.service';

@Component({
  selector   : 'app-dashboard',
  standalone : true,
  imports    : [CommonModule, RouterLink],
  template   : `
    <div class="min-vh-100 bg-light d-flex align-items-center justify-content-center">
      <div class="card shadow-sm text-center p-5" style="max-width:480px;width:100%">
        <div class="mb-3">
          <span class="badge bg-primary fs-6">{{ auth.role() }}</span>
        </div>
        <h2 class="fw-bold">Bienvenue, {{ auth.user()?.nom }} !</h2>
        <p class="text-muted mt-2">
          Connecté en tant que <strong>{{ auth.user()?.email }}</strong>
        </p>
        <hr>
        <p class="text-muted small">
          Dashboard Sprint 1 en cours de développement.
        </p>
        <div class="d-flex gap-2 justify-content-center mt-2">
          <a routerLink="/profil" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-gear me-1"></i>Mon profil
          </a>
          <button class="btn btn-outline-danger btn-sm" (click)="auth.logout()">
            <i class="bi bi-box-arrow-right me-1"></i>Se déconnecter
          </button>
        </div>
      </div>
    </div>
  `,
})
export class DashboardComponent {
  auth = inject(AuthService);
}
