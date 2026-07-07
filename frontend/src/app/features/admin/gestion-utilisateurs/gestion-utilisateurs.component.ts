// features/admin/gestion-utilisateurs/gestion-utilisateurs.component.ts
import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule }  from '@angular/common';
import { FormsModule }   from '@angular/forms';
import { AdminService }  from '../../../core/services/admin.service';
import { IUser, UserStatut } from '../../../core/models/interfaces';

@Component({
  selector  : 'app-gestion-utilisateurs',
  standalone: true,
  imports   : [CommonModule, FormsModule],
  template  : `
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Utilisateurs</h4>
        <p class="text-muted small mb-0">{{ admin.utilisateursTotal() }} utilisateur(s) au total</p>
      </div>
    </div>

    <!-- filtre rôle -->
    <div class="card border-0 shadow-sm p-3 mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-sm-4">
          <label class="form-label small text-muted mb-1">Rôle</label>
          <select class="form-select form-select-sm" [(ngModel)]="filtreRole" (change)="filtrer()">
            @for (r of roles; track r.val) {
              <option [value]="r.val">{{ r.label }}</option>
            }
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-secondary btn-sm" (click)="filtrer()">
            <i class="bi bi-search me-1"></i>Filtrer
          </button>
        </div>
      </div>
    </div>

    @if (admin.erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle"></i>{{ admin.erreur() }}
      </div>
    }

    @if (admin.chargement()) {
      <div class="placeholder-glow">
        @for (i of [1,2,3,4,5]; track i) {
          <div class="placeholder w-100 mb-2" style="height:52px;border-radius:8px;"></div>
        }
      </div>
    } @else {
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:40px">#</th>
                <th>Identité</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Statut</th>
                <th>Membre depuis</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @if (admin.utilisateurs().length === 0) {
                <tr>
                  <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-people fs-2 d-block mb-2"></i>
                    Aucun utilisateur trouvé
                  </td>
                </tr>
              }
              @for (u of admin.utilisateurs(); track u.id) {
                <tr>
                  <td class="text-muted small">{{ u.id }}</td>
                  <td>
                    <div class="fw-semibold">{{ u.prenom }} {{ u.nom }}</div>
                    @if (u.ville) {
                      <div class="text-muted small">{{ u.ville }}</div>
                    }
                  </td>
                  <td class="small">{{ u.email }}</td>
                  <td>
                    <span class="badge rounded-pill" [ngClass]="badgeRole(u.role)">
                      {{ labelRole(u.role) }}
                    </span>
                  </td>
                  <td>
                    <span class="badge rounded-pill" [ngClass]="badgeStatut(u.statut)">
                      {{ labelStatut(u.statut) }}
                    </span>
                  </td>
                  <td class="small text-muted">{{ u.createdAt | date:'dd/MM/yyyy' }}</td>
                  <td>
                    <div class="d-flex gap-1">
                      @if (u.statut !== 'actif') {
                        <button class="btn btn-sm btn-outline-success"
                                title="Activer"
                                [disabled]="enCours() === u.id"
                                (click)="changerStatut(u, 'actif')">
                          @if (enCours() === u.id) {
                            <span class="spinner-border spinner-border-sm"></span>
                          } @else {
                            <i class="bi bi-check-circle"></i>
                          }
                        </button>
                      }
                      @if (u.statut !== 'suspendu') {
                        <button class="btn btn-sm btn-outline-warning"
                                title="Suspendre"
                                [disabled]="enCours() === u.id"
                                (click)="changerStatut(u, 'suspendu')">
                          <i class="bi bi-pause-circle"></i>
                        </button>
                      }
                      @if (u.statut !== 'inactif') {
                        <button class="btn btn-sm btn-outline-danger"
                                title="Désactiver"
                                [disabled]="enCours() === u.id"
                                (click)="desactiver(u)">
                          <i class="bi bi-slash-circle"></i>
                        </button>
                      }
                    </div>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>

      @if (admin.utilisateursPages() > 1) {
        <div class="d-flex justify-content-center align-items-center gap-2 mt-3">
          <button class="btn btn-outline-secondary btn-sm"
                  [disabled]="page() === 1"
                  (click)="changerPage(page() - 1)">
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="small text-muted">Page {{ page() }} / {{ admin.utilisateursPages() }}</span>
          <button class="btn btn-outline-secondary btn-sm"
                  [disabled]="page() === admin.utilisateursPages()"
                  (click)="changerPage(page() + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      }
    }
  `,
})
export class GestionUtilisateursComponent implements OnInit {

  admin      = inject(AdminService);
  filtreRole = '';
  page       = signal<number>(1);
  enCours    = signal<number | null>(null);

  roles = [
    { val: '',               label: 'Tous les rôles' },
    { val: 'patient',        label: 'Patients' },
    { val: 'medecin',        label: 'Médecins' },
    { val: 'pharmacie',      label: 'Pharmacies' },
    { val: 'centre_sante',   label: 'Centres santé' },
    { val: 'centre_analyse', label: 'Centres analyse' },
    { val: 'admin',          label: 'Admins' },
  ];

  ngOnInit(): void { this.admin.chargerUtilisateurs(1); }

  filtrer(): void {
    this.page.set(1);
    this.admin.chargerUtilisateurs(1, this.filtreRole || undefined);
  }

  changerPage(p: number): void {
    this.page.set(p);
    this.admin.chargerUtilisateurs(p, this.filtreRole || undefined);
  }

  changerStatut(u: IUser, statut: UserStatut): void {
    this.enCours.set(u.id);
    this.admin.modifierUtilisateur(u.id, { statut }).subscribe({
      next    : () => this.admin.chargerUtilisateurs(this.page(), this.filtreRole || undefined),
      error   : err => alert(err.error?.error ?? 'Erreur'),
      complete: () => this.enCours.set(null),
    });
  }

  desactiver(u: IUser): void {
    if (!confirm(`Désactiver le compte de ${u.prenom} ${u.nom} ?`)) return;
    this.changerStatut(u, 'inactif');
  }

  badgeStatut(s: string): string {
    return s === 'actif'    ? 'bg-success'
         : s === 'suspendu' ? 'bg-warning text-dark'
         : 'bg-secondary';
  }

  labelStatut(s: string): string {
    return s === 'actif'    ? 'Actif'
         : s === 'suspendu' ? 'Suspendu'
         : 'Inactif';
  }

  badgeRole(r: string): string {
    return r === 'admin'          ? 'bg-danger'
         : r === 'medecin'        ? 'bg-primary'
         : r === 'pharmacie'      ? 'bg-success'
         : r === 'centre_sante'   ? 'bg-info text-dark'
         : r === 'centre_analyse' ? 'bg-secondary'
         : 'bg-light text-dark border';
  }

  labelRole(r: string): string {
    return r === 'admin'          ? 'Admin'
         : r === 'medecin'        ? 'Médecin'
         : r === 'pharmacie'      ? 'Pharmacie'
         : r === 'centre_sante'   ? 'Centre santé'
         : r === 'centre_analyse' ? 'Centre analyse'
         : 'Patient';
  }
}
