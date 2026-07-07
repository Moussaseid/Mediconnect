// features/patient/patient-layout.component.ts
import { Component, inject, signal } from '@angular/core';
import { CommonModule }              from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService }               from '../../core/services/auth.service';

@Component({
  selector  : 'app-patient-layout',
  standalone: true,
  imports   : [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  styles: [`
    .sidebar {
      width: 220px; min-height: 100vh; flex-shrink: 0;
      transition: transform .25s ease;
    }
    .sidebar .nav-link { border-radius: 8px; font-size: .875rem;
                         transition: background .15s, color .15s; }
    .sidebar .nav-link:hover  { background: rgba(255,255,255,.08); }
    .sidebar .nav-link.active-link { background: #0d6efd !important; color: #fff !important; }
    .topbar { height: 56px; }
    .avatar  { width: 32px; height: 32px; font-size: .75rem; }
    .sidebar-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.45); z-index: 1040; cursor: pointer;
    }
    @media (max-width: 767.98px) {
      .sidebar {
        position: fixed; top: 0; left: 0; bottom: 0;
        z-index: 1050; transform: translateX(-100%);
      }
      .sidebar.sidebar-open { transform: translateX(0); }
      .sidebar-overlay.overlay-open { display: block; }
    }
  `],
  template: `
    <!-- Overlay mobile -->
    <div class="sidebar-overlay" [class.overlay-open]="open()"
         (click)="open.set(false)"></div>

    <div class="d-flex" style="min-height:100vh; background:#f1f5f9;">

      <!-- ── Sidebar ── -->
      <aside class="sidebar bg-dark text-white d-flex flex-column"
             [class.sidebar-open]="open()">

        <!-- Brand -->
        <div class="p-3 border-bottom border-secondary">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-heart-pulse text-primary fs-5"></i>
            <div>
              <div class="fw-bold lh-1" style="font-size:.9rem;">MediConnect</div>
              <div class="text-muted" style="font-size:.68rem;letter-spacing:.05em;">ESPACE PATIENT</div>
            </div>
          </div>
        </div>

        <!-- Nav -->
        <nav class="flex-grow-1 p-2 pt-3">
          <p class="text-muted px-2 mb-1"
             style="font-size:.65rem;letter-spacing:.08em;text-transform:uppercase;">
            Mon espace
          </p>
          <ul class="nav flex-column gap-1">
            <li class="nav-item">
              <a routerLink="rdv" routerLinkActive="active-link"
                 (click)="open.set(false)"
                 class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2">
                <i class="bi bi-calendar-check fs-5"></i>
                <span class="d-inline">Rendez-vous</span>
              </a>
            </li>
            <li class="nav-item">
              <a routerLink="prescriptions" routerLinkActive="active-link"
                 (click)="open.set(false)"
                 class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2">
                <i class="bi bi-file-medical fs-5"></i>
                <span class="d-inline">Ordonnances</span>
              </a>
            </li>
            <li class="nav-item">
              <a routerLink="commandes" routerLinkActive="active-link"
                 (click)="open.set(false)"
                 class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2">
                <i class="bi bi-bag fs-5"></i>
                <span class="d-inline">Commandes</span>
              </a>
            </li>
          </ul>
        </nav>

        <!-- Profil -->
        <div class="p-3 border-top border-secondary">
          <div class="d-flex align-items-center gap-2">
            <div class="avatar rounded-circle bg-primary d-flex align-items-center
                        justify-content-center text-white fw-bold">
              {{ initiales() }}
            </div>
            <div class="flex-grow-1 overflow-hidden">
              <div class="small fw-semibold text-truncate lh-1">
                {{ auth.user()?.prenom }} {{ auth.user()?.nom }}
              </div>
              <div class="text-muted" style="font-size:.68rem;">Patient</div>
            </div>
            <button class="btn btn-sm btn-outline-danger border-0"
                    title="Déconnexion" (click)="auth.logout()">
              <i class="bi bi-box-arrow-right"></i>
            </button>
          </div>
        </div>
      </aside>

      <!-- ── Contenu principal ── -->
      <div class="flex-grow-1 d-flex flex-column overflow-hidden">

        <!-- Topbar -->
        <header class="topbar bg-white border-bottom px-3 px-md-4 d-flex align-items-center
                       justify-content-between shadow-sm">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-md-none border-0"
                    (click)="open.set(!open())" aria-label="Menu">
              <i class="bi bi-list fs-4"></i>
            </button>
            <i class="bi bi-heart-pulse text-muted d-none d-md-inline"></i>
            <span class="fw-semibold text-dark" style="font-size:.9rem;">Espace patient</span>
          </div>
          <span class="badge rounded-pill bg-primary-subtle text-primary small">
            <i class="bi bi-check-circle me-1"></i>
            <span class="d-none d-md-inline">Patient</span>
          </span>
        </header>

        <!-- Vue routée -->
        <main class="flex-grow-1 overflow-auto p-3 p-md-4">
          <router-outlet />
        </main>
      </div>
    </div>
  `,
})
export class PatientLayoutComponent {
  auth = inject(AuthService);
  open = signal(false);

  initiales(): string {
    const u = this.auth.user();
    if (!u) return 'P';
    return ((u.prenom?.[0] ?? '') + (u.nom?.[0] ?? '')).toUpperCase() || 'P';
  }
}
