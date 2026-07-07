import { Component, OnInit, inject } from '@angular/core';
import { CommonModule }              from '@angular/common';
import { RouterLink }                from '@angular/router';
import { CentreSanteService }        from '../../../core/services/centre-sante.service';

@Component({
  selector  : 'app-dashboard-centre-sante',
  standalone: true,
  imports   : [CommonModule, RouterLink],
  template  : `
    <div class="mb-4">
      <h4 class="fw-bold mb-0">Tableau de bord</h4>
      @if (centre()) {
        <p class="text-muted mb-0">{{ centre()!.nom }}</p>
      }
    </div>

    @if (centre(); as c) {
      <div class="row g-3 mb-4">
        <!-- Statut -->
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                   [class]="c.actif ? 'bg-success-subtle' : 'bg-danger-subtle'"
                   style="width:48px;height:48px;">
                <i class="fs-5" [class]="c.actif ? 'bi bi-check-circle text-success' : 'bi bi-x-circle text-danger'"></i>
              </div>
              <div>
                <div class="fw-bold">{{ c.actif ? 'Actif' : 'Inactif' }}</div>
                <div class="text-muted small">Statut</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Spécialités -->
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle bg-primary-subtle d-flex align-items-center
                          justify-content-center" style="width:48px;height:48px;">
                <i class="bi bi-clipboard2-pulse text-primary fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">{{ nbSpecialites(c.specialites) }}</div>
                <div class="text-muted small">Spécialités</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Services -->
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle bg-info-subtle d-flex align-items-center
                          justify-content-center" style="width:48px;height:48px;">
                <i class="bi bi-gear text-info fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">{{ nbServices(c.services) }}</div>
                <div class="text-muted small">Services</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Photo -->
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle bg-warning-subtle d-flex align-items-center
                          justify-content-center" style="width:48px;height:48px;">
                <i class="bi bi-image text-warning fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">{{ c.photoPath ? 'Oui' : 'Non' }}</div>
                <div class="text-muted small">Photo</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Infos -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-bottom fw-semibold">Informations</div>
        <div class="card-body row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Adresse</div>
            <div>{{ c.adresse ?? '—' }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Téléphone</div>
            <div>{{ c.telephone ?? '—' }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Email</div>
            <div>{{ c.email ?? '—' }}</div>
          </div>
          @if (c.description) {
            <div class="col-12">
              <div class="text-muted small">Description</div>
              <div>{{ c.description }}</div>
            </div>
          }
        </div>
      </div>

      <!-- Tags spécialités -->
      @if (c.specialites) {
        <div class="mb-3">
          <div class="text-muted small mb-1">Spécialités</div>
          @for (s of splitTags(c.specialites); track s) {
            <span class="badge bg-primary-subtle text-primary me-1 mb-1">{{ s }}</span>
          }
        </div>
      }

      <!-- Tags services -->
      @if (c.services) {
        <div class="mb-3">
          <div class="text-muted small mb-1">Services</div>
          @for (s of splitTags(c.services); track s) {
            <span class="badge bg-info-subtle text-info me-1 mb-1">{{ s }}</span>
          }
        </div>
      }
    }

    <a routerLink="../infos" class="btn btn-primary">
      <i class="bi bi-pencil me-1"></i> Modifier les informations
    </a>
  `,
})
export class DashboardCentreSanteComponent implements OnInit {
  private svc = inject(CentreSanteService);
  centre = this.svc.centre;

  ngOnInit(): void {
    this.svc.chargerInfos().subscribe();
  }

  nbSpecialites(s: string | undefined): number {
    return s ? this.splitTags(s).length : 0;
  }

  nbServices(s: string | undefined): number {
    return s ? this.splitTags(s).length : 0;
  }

  splitTags(s: string): string[] {
    return s.split(',').map(t => t.trim()).filter(Boolean);
  }
}
