import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule }               from '@angular/common';
import { RouterLink }                 from '@angular/router';
import { CentreAnalyseService }       from '../../../core/services/centre-analyse.service';

@Component({
  selector  : 'app-dashboard-centre-analyse',
  standalone: true,
  imports   : [CommonModule, RouterLink],
  template  : `
    <div class="mb-4">
      <h4 class="fw-bold mb-0">Tableau de bord</h4>
      @if (centre()) {
        <p class="text-muted mb-0">{{ centre()!.nom }}</p>
      }
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success-subtle d-flex align-items-center
                        justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-list-check text-success fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-4 lh-1">{{ totalAnalyses() }}</div>
              <div class="text-muted small">Analyses</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary-subtle d-flex align-items-center
                        justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-check2-circle text-primary fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-4 lh-1">{{ analysesDisponibles() }}</div>
              <div class="text-muted small">Disponibles</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-danger-subtle d-flex align-items-center
                        justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-x-circle text-danger fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-4 lh-1">{{ analysesIndisponibles() }}</div>
              <div class="text-muted small">Indisponibles</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning-subtle d-flex align-items-center
                        justify-content-center" style="width:48px;height:48px;">
              <i class="bi bi-currency-euro text-warning fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-4 lh-1">{{ prixMoyen() }} €</div>
              <div class="text-muted small">Prix moyen</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Infos centre -->
    @if (centre()) {
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom fw-semibold">
          Informations du centre
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted small">Adresse</div>
              <div>{{ centre()!.adresse ?? '—' }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Téléphone</div>
              <div>{{ centre()!.telephone ?? '—' }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Email</div>
              <div>{{ centre()!.email ?? '—' }}</div>
            </div>
          </div>
        </div>
      </div>
    }

    <!-- Raccourci -->
    <div class="mt-3">
      <a routerLink="../analyses" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i> Gérer les analyses
      </a>
    </div>
  `,
})
export class DashboardCentreAnalyseComponent implements OnInit {
  private svc = inject(CentreAnalyseService);

  centre   = this.svc.centre;
  analyses = this.svc.analyses;

  totalAnalyses       = computed(() => this.analyses().length);
  analysesDisponibles = computed(() => this.analyses().filter(a => a.disponible).length);
  analysesIndisponibles = computed(() => this.analyses().filter(a => !a.disponible).length);
  prixMoyen = computed(() => {
    const list = this.analyses();
    if (!list.length) return '0.00';
    return (list.reduce((s, a) => s + a.prix, 0) / list.length).toFixed(2);
  });

  ngOnInit(): void {
    this.svc.chargerInfos().subscribe();
    this.svc.chargerAnalyses().subscribe();
  }
}
