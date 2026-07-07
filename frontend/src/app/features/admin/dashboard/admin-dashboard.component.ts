// src/app/features/admin/dashboard/admin-dashboard.component.ts
import { Component, inject } from '@angular/core';
import { CommonModule }      from '@angular/common';
import { RouterLink }        from '@angular/router';
import { AuthService }       from '../../../core/services/auth.service';

@Component({
  selector   : 'app-admin-dashboard',
  standalone : true,
  imports    : [CommonModule, RouterLink],
  template   : `
    <div class="min-vh-100 bg-light py-5">
      <div class="container" style="max-width:900px">

        <div class="d-flex align-items-center justify-content-between mb-5">
          <div>
            <h1 class="fw-bold mb-0">Tableau de bord admin</h1>
            <p class="text-muted mb-0">Connecté en tant que {{ auth.user()?.email }}</p>
          </div>
          <div class="d-flex gap-2">
            <a routerLink="/profil" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-person-gear me-1"></i>Mon profil
            </a>
            <button class="btn btn-outline-danger btn-sm" (click)="auth.logout()">
              <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
            </button>
          </div>
        </div>

        <div class="row g-4">

          <div class="col-md-4">
            <a routerLink="/admin/patients" class="card shadow-sm text-decoration-none h-100">
              <div class="card-body text-center py-4">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex
                            align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px">
                  <i class="bi bi-people-fill text-primary fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Patients</h5>
                <p class="text-muted small mb-0">Liste, recherche, filtres, pagination</p>
              </div>
            </a>
          </div>

          <div class="col-md-4">
            <a routerLink="/admin/medecins" class="card shadow-sm text-decoration-none h-100">
              <div class="card-body text-center py-4">
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex
                            align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px">
                  <i class="bi bi-heart-pulse-fill text-success fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Médecins</h5>
                <p class="text-muted small mb-0">CRUD complet — modifier, suspendre, supprimer</p>
              </div>
            </a>
          </div>

          <div class="col-md-4">
            <a routerLink="/admin/logs" class="card shadow-sm text-decoration-none h-100">
              <div class="card-body text-center py-4">
                <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex
                            align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px">
                  <i class="bi bi-journal-code text-warning fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Logs connexion</h5>
                <p class="text-muted small mb-0">Activité MongoDB (auth_logs)</p>
              </div>
            </a>
          </div>

          <div class="col-md-4 mt-3">
            <a routerLink="/admin/auth-dashboard" class="card shadow-sm text-decoration-none h-100 border-danger border-opacity-25">
              <div class="card-body text-center py-4">
                <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex
                            align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px">
                  <i class="bi bi-shield-lock-fill text-danger fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Dashboard Sécurité</h5>
                <p class="text-muted small mb-0">Graphique connexions · Alertes IP · Export PDF</p>
              </div>
            </a>
          </div>

        </div>
      </div>
    </div>
  `,
})
export class AdminDashboardComponent {
  auth = inject(AuthService);
}
