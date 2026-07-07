// features/admin/dashboard-admin/dashboard-admin.component.ts
import {
  Component, OnInit, AfterViewInit,
  ViewChild, ElementRef,
  inject, Injector, effect,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  Chart,
  CategoryScale, LinearScale,
  BarElement, BarController,
  DoughnutController, ArcElement,
  Tooltip, Legend,
} from 'chart.js';

import { AdminService }  from '../../../core/services/admin.service';
import { IAdminStats }   from '../../../core/models/interfaces';

Chart.register(
  CategoryScale, LinearScale,
  BarElement, BarController,
  DoughnutController, ArcElement,
  Tooltip, Legend,
);

interface IKpi {
  label    : string;
  valeur   : number;
  icon     : string;
  accentHex: string;
}

@Component({
  selector  : 'app-dashboard-admin',
  standalone: true,
  imports   : [CommonModule],
  styles: [`
    .kpi-card { border-left: 4px solid; border-radius: 12px; transition: transform .15s, box-shadow .15s; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.1) !important; }
    .kpi-icon { width: 48px; height: 48px; border-radius: 12px; }
    .alerte-row:hover { background: #fff8e1; }
    .section-title { font-size: .7rem; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; }
  `],
  template: `
    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Tableau de bord</h4>
        <p class="text-muted small mb-0">Vue d'ensemble en temps réel</p>
      </div>
      <button class="btn btn-outline-primary btn-sm" (click)="rafraichir()"
              [disabled]="admin.chargement()">
        <i class="bi bi-arrow-clockwise me-1"
           [class.spin]="admin.chargement()"></i>
        Actualiser
      </button>
    </div>

    <!-- Erreur -->
    @if (admin.erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i>
        {{ admin.erreur() }}
      </div>
    }

    <!-- Skeleton -->
    @if (admin.chargement() && !admin.stats()) {
      <div class="row g-3 mb-4">
        @for (i of [1,2,3,4,5,6]; track i) {
          <div class="col-6 col-xl-4">
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
              <div class="card-body">
                <div class="placeholder-glow">
                  <span class="placeholder col-6 mb-2 rounded"></span>
                  <span class="placeholder col-4 rounded" style="height:2rem;display:block;"></span>
                </div>
              </div>
            </div>
          </div>
        }
      </div>
    }

    <!-- KPI Cards -->
    @if (admin.stats(); as s) {
      <div class="row g-3 mb-4">
        @for (kpi of kpis(s); track kpi.label) {
          <div class="col-6 col-xl-4">
            <div class="card border-0 shadow-sm kpi-card h-100"
                 [style.border-left-color]="kpi.accentHex">
              <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="kpi-icon d-flex align-items-center justify-content-center"
                     [style.background]="kpi.accentHex + '20'">
                  <i class="bi {{ kpi.icon }} fs-4" [style.color]="kpi.accentHex"></i>
                </div>
                <div>
                  <div class="text-muted" style="font-size:.75rem;">{{ kpi.label }}</div>
                  <div class="fw-bold fs-3 lh-1">{{ kpi.valeur | number }}</div>
                </div>
              </div>
            </div>
          </div>
        }
      </div>
    }

    <!-- Graphiques — canvas toujours dans le DOM (static:true) -->
    <div class="row g-3 mb-4" [class.d-none]="!admin.stats()">
      <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
          <div class="card-body">
            <p class="section-title text-muted mb-3">Répartition utilisateurs</p>
            <div style="position:relative;height:220px;">
              <canvas #rolesChart></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
          <div class="card-body">
            <p class="section-title text-muted mb-3">RDV du mois en cours</p>
            <div style="position:relative;height:220px;">
              <canvas #rdvChart></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Alertes stock -->
    @if (admin.stats(); as s) {
      @if (s.alertesStock.length > 0) {
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
              <p class="section-title text-muted mb-0">Alertes stock — quantité &lt; 30</p>
              <span class="badge rounded-pill bg-warning text-dark">
                {{ s.alertesStock.length }}
              </span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Pharmacie</th>
                    <th>Médicament</th>
                    <th class="text-center">Quantité</th>
                    <th class="text-end">Prix unitaire</th>
                  </tr>
                </thead>
                <tbody>
                  @for (a of s.alertesStock; track a.medicamentNom) {
                    <tr class="alerte-row">
                      <td class="small">{{ a.pharmacieNom }}</td>
                      <td class="small fw-semibold">{{ a.medicamentNom }}</td>
                      <td class="text-center">
                        <span class="badge rounded-pill"
                              [class]="a.quantite === 0 ? 'bg-danger' : 'bg-warning text-dark'">
                          {{ a.quantite }}
                        </span>
                      </td>
                      <td class="text-end small">
                        {{ a.prixUnitaire | currency:'EUR':'symbol':'1.2-2':'fr' }}
                      </td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          </div>
        </div>
      }
    }
  `,
})
export class DashboardAdminComponent implements OnInit, AfterViewInit {

  // static: true — le canvas est toujours dans le DOM (hors @if), disponible dès ngAfterViewInit
  @ViewChild('rolesChart', { static: true }) rolesCanvasRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('rdvChart',   { static: true }) rdvCanvasRef!:   ElementRef<HTMLCanvasElement>;

  admin    = inject(AdminService);
  private injector = inject(Injector);

  private rolesChart?: Chart;
  private rdvChart?:   Chart;

  ngOnInit(): void {
    this.admin.chargerStats();
  }

  ngAfterViewInit(): void {
    effect(() => {
      const s = this.admin.stats();
      if (s) this.rendreGraphiques(s);
    }, { injector: this.injector });
  }

  rafraichir(): void {
    this.rolesChart?.destroy();
    this.rdvChart?.destroy();
    this.rolesChart = undefined;
    this.rdvChart   = undefined;
    this.admin.chargerStats();
  }

  kpis(s: IAdminStats): IKpi[] {
    return [
      { label: 'Patients inscrits',   valeur: s.patients,       icon: 'bi-people-fill',       accentHex: '#0D6EFD' },
      { label: 'Médecins actifs',     valeur: s.medecins,       icon: 'bi-person-badge-fill',  accentHex: '#198754' },
      { label: 'Pharmacies actives',  valeur: s.pharmacies,     icon: 'bi-capsule-pill',       accentHex: '#6f42c1' },
      { label: "Centres d'analyse",   valeur: s.centresAnalyse, icon: 'bi-eyedropper-fill',    accentHex: '#fd7e14' },
      { label: 'RDV ce mois',         valeur: s.rdvCeMois,      icon: 'bi-calendar-check',     accentHex: '#20c997' },
      { label: 'Demandes en attente', valeur: s.demandesPro,    icon: 'bi-clock-history',      accentHex: '#FFC107' },
    ];
  }

  private rendreGraphiques(s: IAdminStats): void {
    this.rendreRoles(s);
    this.rendreRdv(s);
  }

  private rendreRoles(s: IAdminStats): void {
    const roleColors: Record<string, string> = {
      patient        : '#0D6EFD',
      medecin        : '#198754',
      pharmacie      : '#6f42c1',
      centre_sante   : '#fd7e14',
      centre_analyse : '#20c997',
      admin          : '#6c757d',
    };
    const roles  = s.repartitionRoles ?? [];
    const labels = roles.map(r => r.role.replace(/_/g, ' '));
    const data   = roles.map(r => r.nb);
    const colors = roles.map(r => roleColors[r.role] ?? '#adb5bd');

    this.rolesChart?.destroy();
    this.rolesChart = new Chart(this.rolesCanvasRef.nativeElement, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors,
          borderWidth    : 2,
          borderColor    : '#fff',
          hoverOffset    : 6,
        }],
      },
      options: {
        responsive         : true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } },
        },
        cutout: '65%',
      },
    });
  }

  private rendreRdv(s: IAdminStats): void {
    const autres = Math.max(0, s.rdvCeMois - s.rdvConfirmes - s.rdvAnnules);
    this.rdvChart?.destroy();
    this.rdvChart = new Chart(this.rdvCanvasRef.nativeElement, {
      type: 'bar',
      data: {
        labels  : ['Confirmés', 'Annulés', 'En attente / autre'],
        datasets: [{
          label          : 'RDV',
          data           : [s.rdvConfirmes, s.rdvAnnules, autres],
          backgroundColor: ['#198754cc', '#DC3545cc', '#FFC107cc'],
          borderColor    : ['#198754',   '#DC3545',   '#FFC107'],
          borderWidth    : 2,
          borderRadius   : 6,
        }],
      },
      options: {
        responsive         : true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales : {
          y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f0f0' } },
          x: { grid: { display: false } },
        },
      },
    });
  }
}
