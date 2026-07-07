// features/patient/prescriptions/prescriptions.component.ts
import { Component, OnInit, inject, computed } from '@angular/core';
import { CommonModule }         from '@angular/common';
import { PrescriptionService }  from '../../../core/services/prescription.service';
import { IPrescription }        from '../../../core/models/interfaces';

@Component({
  selector  : 'app-prescriptions',
  standalone: true,
  imports   : [CommonModule],
  styles: [`
    .ordonnance-card { border-radius: 12px; transition: box-shadow .12s; }
    .ordonnance-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .ligne-row { border-left: 3px solid #0d6efd; }
  `],
  template: `
    <!-- ── En-tête ──────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Mes ordonnances</h4>
        <p class="text-muted small mb-0">{{ svc.prescriptions().length }} ordonnance(s)</p>
      </div>
      <button class="btn btn-outline-secondary btn-sm"
              [disabled]="svc.chargement()"
              (click)="svc.charger()">
        <i class="bi bi-arrow-clockwise me-1"></i>Actualiser
      </button>
    </div>

    @if (svc.erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle"></i>{{ svc.erreur() }}
      </div>
    }

    @if (svc.chargement()) {
      <div class="placeholder-glow d-flex flex-column gap-3">
        @for (i of [1,2]; track i) {
          <div class="placeholder w-100" style="height:140px;border-radius:12px;"></div>
        }
      </div>
    } @else if (svc.prescriptions().length === 0) {
      <div class="text-center py-5 text-muted">
        <i class="bi bi-file-medical fs-1 d-block mb-2"></i>
        <p class="fw-semibold">Aucune ordonnance enregistrée</p>
        <p class="small">Vos ordonnances apparaîtront ici après vos consultations.</p>
      </div>
    } @else {
      <div class="d-flex flex-column gap-4">
        @for (p of svc.prescriptions(); track p.id) {
          <div class="card border-0 shadow-sm ordonnance-card">
            <div class="card-body">

              <!-- En-tête ordonnance -->
              <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-file-medical text-primary fs-5"></i>
                    <span class="fw-bold">
                      Dr {{ p.medecin?.prenom }} {{ p.medecin?.nom }}
                    </span>
                    <span class="badge rounded-pill" [ngClass]="badgeValidite(p)">
                      {{ labelValidite(p) }}
                    </span>
                  </div>
                  <div class="text-muted small">
                    <i class="bi bi-person-badge me-1"></i>
                    {{ p.medecin?.specialisation }}
                  </div>
                  <div class="d-flex gap-3 mt-1">
                    <span class="small text-muted">
                      <i class="bi bi-calendar3 me-1"></i>
                      {{ p.datePrescription | date:'dd/MM/yyyy' }}
                    </span>
                    <span class="small text-muted">
                      <i class="bi bi-clock-history me-1"></i>
                      Valable {{ p.validiteJours }} jours
                      (jusqu'au {{ dateExpiration(p) | date:'dd/MM/yyyy' }})
                    </span>
                  </div>
                </div>

                <!-- Bouton expand -->
                <button class="btn btn-sm btn-outline-secondary"
                        (click)="toggleExpand(p.id)">
                  <i class="bi"
                     [class.bi-chevron-down]="expandedId !== p.id"
                     [class.bi-chevron-up]="expandedId === p.id"></i>
                  {{ (p.lignes?.length ?? 0) }} médicament(s)
                </button>
              </div>

              <!-- Lignes médicaments (expandable) -->
              @if (expandedId === p.id && p.lignes && p.lignes.length > 0) {
                <div class="d-flex flex-column gap-2 mt-2">
                  @for (l of p.lignes; track l.id) {
                    <div class="bg-light rounded p-3 ligne-row">
                      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                          <div class="fw-semibold small">
                            {{ l.medicament?.nom ?? 'Médicament #' + l.medicamentId }}
                            @if (l.medicament?.forme || l.medicament?.dosage) {
                              <span class="text-muted fw-normal ms-1">
                                ({{ [l.medicament?.forme, l.medicament?.dosage]
                                    .filter(x => !!x).join(' · ') }})
                              </span>
                            }
                          </div>
                          <div class="text-muted small mt-1">
                            <i class="bi bi-capsule me-1 text-primary"></i>
                            {{ l.posologie }}
                          </div>
                        </div>
                        <div class="text-end small">
                          <div class="fw-semibold">{{ l.quantite }} unité(s)</div>
                          @if (l.dureeJours) {
                            <div class="text-muted">{{ l.dureeJours }} jours</div>
                          }
                        </div>
                      </div>
                    </div>
                  }
                </div>
              }

              <!-- Toujours visible si 1 seul médicament -->
              @if (p.lignes && p.lignes.length === 1 && expandedId !== p.id) {
                <div class="text-muted small">
                  <i class="bi bi-capsule me-1 text-primary"></i>
                  {{ p.lignes[0].medicament?.nom }} — {{ p.lignes[0].posologie }}
                </div>
              }

            </div>
          </div>
        }
      </div>
    }
  `,
})
export class PrescriptionsComponent implements OnInit {

  svc        = inject(PrescriptionService);
  expandedId: number | null = null;

  ngOnInit(): void { this.svc.charger(); }

  toggleExpand(id: number): void {
    this.expandedId = this.expandedId === id ? null : id;
  }

  dateExpiration(p: IPrescription): Date {
    const d = new Date(p.datePrescription);
    d.setDate(d.getDate() + p.validiteJours);
    return d;
  }

  estValide(p: IPrescription): boolean {
    return this.dateExpiration(p) >= new Date();
  }

  badgeValidite(p: IPrescription): string {
    return this.estValide(p) ? 'bg-success' : 'bg-secondary';
  }

  labelValidite(p: IPrescription): string {
    return this.estValide(p) ? 'Valide' : 'Expirée';
  }
}
