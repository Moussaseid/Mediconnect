// features/admin/gestion-demandes/gestion-demandes.component.ts
import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule }   from '@angular/common';
import { AdminService }   from '../../../core/services/admin.service';
import { IDemandeProfessionnel } from '../../../core/models/interfaces';

@Component({
  selector  : 'app-gestion-demandes',
  standalone: true,
  imports   : [CommonModule],
  template  : `
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Demandes professionnelles</h4>
        <p class="text-muted small mb-0">{{ admin.demandesTotal() }} demande(s) au total</p>
      </div>
      <button class="btn btn-outline-primary btn-sm" (click)="recharger()">
        <i class="bi bi-arrow-clockwise me-1"></i>Actualiser
      </button>
    </div>

    <!-- filtres statut -->
    <div class="mb-3 d-flex gap-2 flex-wrap">
      @for (s of statuts; track s.val) {
        <button class="btn btn-sm"
                [class.btn-primary]="filtreStatut() === s.val"
                [class.btn-outline-secondary]="filtreStatut() !== s.val"
                (click)="setStatut(s.val)">
          {{ s.label }}
        </button>
      }
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
                <th>Type</th>
                <th>Identité</th>
                <th>Email</th>
                <th>N° Pro</th>
                <th>Entité</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @if (demandesAffichees().length === 0) {
                <tr>
                  <td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Aucune demande{{ filtreStatut() !== 'tous' ? ' pour ce filtre' : '' }}
                  </td>
                </tr>
              }
              @for (d of demandesAffichees(); track d.id) {
                <tr>
                  <td class="text-muted small">{{ d.id }}</td>
                  <td>
                    <span class="badge rounded-pill" [ngClass]="badgeType(d.typeProfessionnel)">
                      {{ labelType(d.typeProfessionnel) }}
                    </span>
                  </td>
                  <td>
                    <div class="fw-semibold">{{ d.prenom }} {{ d.nom }}</div>
                    @if (d.telephone) {
                      <div class="text-muted small">{{ d.telephone }}</div>
                    }
                  </td>
                  <td class="small">{{ d.email }}</td>
                  <td class="small">{{ d.numeroPro ?? '—' }}</td>
                  <td class="small">{{ d.entiteNom ?? '—' }}</td>
                  <td>
                    <span class="badge rounded-pill" [ngClass]="badgeStatut(d.statut)">
                      {{ labelStatut(d.statut) }}
                    </span>
                  </td>
                  <td class="small text-muted">{{ d.createdAt | date:'dd/MM/yy' }}</td>
                  <td>
                    @if (d.statut === 'en_attente') {
                      <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-success"
                                [disabled]="enCours() === d.id"
                                (click)="valider(d)">
                          @if (enCours() === d.id) {
                            <span class="spinner-border spinner-border-sm"></span>
                          } @else {
                            <i class="bi bi-check-lg"></i>
                          }
                        </button>
                        <button class="btn btn-sm btn-outline-danger"
                                [disabled]="enCours() === d.id"
                                (click)="rejeter(d)">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </div>
                    } @else {
                      <span class="text-muted small">Traité</span>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>

      @if (admin.demandesPages() > 1) {
        <div class="d-flex justify-content-center align-items-center gap-2 mt-3">
          <button class="btn btn-outline-secondary btn-sm"
                  [disabled]="page() === 1"
                  (click)="changerPage(page() - 1)">
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="small text-muted">Page {{ page() }} / {{ admin.demandesPages() }}</span>
          <button class="btn btn-outline-secondary btn-sm"
                  [disabled]="page() === admin.demandesPages()"
                  (click)="changerPage(page() + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      }
    }
  `,
})
export class GestionDemandesComponent implements OnInit {

  admin    = inject(AdminService);
  filtreStatut = signal<string>('en_attente');
  page         = signal<number>(1);
  enCours      = signal<number | null>(null);

  statuts = [
    { val: 'tous',       label: 'Toutes' },
    { val: 'en_attente', label: 'En attente' },
    { val: 'approuve',   label: 'Approuvées' },
    { val: 'rejete',     label: 'Rejetées' },
  ];

  demandesAffichees = computed(() => {
    const f = this.filtreStatut();
    return f === 'tous'
      ? this.admin.demandes()
      : this.admin.demandes().filter(d => d.statut === f);
  });

  ngOnInit(): void { this.admin.chargerDemandes(1); }

  recharger(): void { this.admin.chargerDemandes(this.page()); }

  setStatut(s: string): void {
    this.filtreStatut.set(s);
    this.page.set(1);
    this.admin.chargerDemandes(1);
  }

  changerPage(p: number): void {
    this.page.set(p);
    this.admin.chargerDemandes(p);
  }

  valider(d: IDemandeProfessionnel): void {
    this.enCours.set(d.id);
    this.admin.traiterDemande(d.id, 'valider').subscribe({
      next    : () => this.admin.chargerDemandes(this.page()),
      error   : err => alert(err.error?.error ?? 'Erreur lors de la validation'),
      complete: () => this.enCours.set(null),
    });
  }

  rejeter(d: IDemandeProfessionnel): void {
    if (!confirm(`Rejeter la demande de ${d.prenom} ${d.nom} ?`)) return;
    this.enCours.set(d.id);
    this.admin.traiterDemande(d.id, 'rejeter').subscribe({
      next    : () => this.admin.chargerDemandes(this.page()),
      error   : err => alert(err.error?.error ?? 'Erreur lors du rejet'),
      complete: () => this.enCours.set(null),
    });
  }

  badgeStatut(s: string): string {
    return s === 'en_attente' ? 'bg-warning text-dark'
         : s === 'approuve'  ? 'bg-success'
         : 'bg-danger';
  }

  labelStatut(s: string): string {
    return s === 'en_attente' ? 'En attente'
         : s === 'approuve'  ? 'Approuvé'
         : 'Rejeté';
  }

  badgeType(t: string): string {
    return t === 'medecin'        ? 'bg-primary'
         : t === 'pharmacien'     ? 'bg-success'
         : t === 'centre_sante'   ? 'bg-info text-dark'
         : 'bg-secondary';
  }

  labelType(t: string): string {
    return t === 'medecin'        ? 'Médecin'
         : t === 'pharmacien'     ? 'Pharmacien'
         : t === 'centre_sante'   ? 'Centre santé'
         : 'Centre analyse';
  }
}