// features/patient/rdv/rdv.component.ts
import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule }  from '@angular/common';
import { FormsModule }   from '@angular/forms';
import { RdvService }    from '../../../core/services/rdv.service';
import { IRdv }          from '../../../core/models/interfaces';

type FiltrRdv = 'tous' | 'avenir' | 'passes' | 'annules';

@Component({
  selector  : 'app-rdv',
  standalone: true,
  imports   : [CommonModule, FormsModule],
  styles: [`
    .rdv-card { border-radius: 12px; transition: box-shadow .12s; }
    .rdv-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .slot-btn { min-width: 80px; font-size: .8rem; }
  `],
  template: `
    <!-- ── En-tête ──────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Mes rendez-vous</h4>
        <p class="text-muted small mb-0">{{ svc.rdvs().length }} RDV au total</p>
      </div>
      <button class="btn btn-sm"
              [class.btn-primary]="!showForm()"
              [class.btn-outline-secondary]="showForm()"
              (click)="showForm.set(!showForm())">
        @if (showForm()) {
          <i class="bi bi-x-lg me-1"></i>Fermer
        } @else {
          <i class="bi bi-plus-lg me-1"></i>Prendre un RDV
        }
      </button>
    </div>

    @if (svc.erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-triangle"></i>{{ svc.erreur() }}
      </div>
    }
    @if (erreurForm()) {
      <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-circle"></i>{{ erreurForm() }}
        <button class="btn-close ms-auto" (click)="erreurForm.set(null)"></button>
      </div>
    }

    <!-- ── Formulaire prise de RDV ───────────────────────────────────── -->
    @if (showForm()) {
      <div class="card border-0 shadow-sm p-4 mb-4">
        <h5 class="fw-semibold mb-3">Prendre un rendez-vous</h5>

        <div class="row g-3">
          <!-- Médecin -->
          <div class="col-md-5">
            <label class="form-label">Médecin <span class="text-danger">*</span></label>
            <select class="form-select" [(ngModel)]="form.medecinId"
                    (change)="onMedecinChange()">
              <option [ngValue]="0" disabled>Choisir un médecin…</option>
              @for (m of svc.medecins(); track m.id) {
                <option [ngValue]="m.id">
                  Dr {{ m.prenom }} {{ m.nom }} — {{ m.specialisation }}
                </option>
              }
            </select>
          </div>

          <!-- Date -->
          <div class="col-md-3">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control"
                   [(ngModel)]="form.date" [min]="today"
                   (change)="onDateChange()">
          </div>

          <!-- Créneaux -->
          @if (form.medecinId > 0 && form.date) {
            <div class="col-12">
              <label class="form-label">Créneau</label>
              @if (svc.creneaux().length === 0) {
                <div class="text-muted small">
                  <i class="bi bi-calendar-x me-1"></i>Aucun creneau disponible pour cette date.
                </div>
              } @else {
                <div class="d-flex flex-wrap gap-2">
                  @for (c of svc.creneaux(); track c.heureDebut) {
                    <button class="btn btn-sm slot-btn"
                            [class.btn-outline-primary]="form.creneau !== c.heureDebut && c.disponible"
                            [class.btn-primary]="form.creneau === c.heureDebut"
                            [class.btn-outline-secondary]="!c.disponible"
                            [disabled]="!c.disponible"
                            (click)="form.creneau = c.heureDebut">
                      {{ c.heureDebut }}
                      @if (!c.disponible) { <i class="bi bi-lock-fill ms-1"></i> }
                    </button>
                  }
                </div>
              }
            </div>
          }
        </div>

        <div class="d-flex gap-2 mt-4">
          <button class="btn btn-primary"
                  [disabled]="!form.medecinId || !form.date || !form.creneau || saving()"
                  (click)="confirmerRdv()">
            @if (saving()) { <span class="spinner-border spinner-border-sm me-1"></span> }
            <i class="bi bi-check-lg me-1"></i>Confirmer
          </button>
          <button class="btn btn-outline-secondary" (click)="annulerForm()">Annuler</button>
        </div>
      </div>
    }

    <!-- ── Filtres ────────────────────────────────────────────────────── -->
    <div class="mb-3 d-flex gap-2 flex-wrap">
      @for (f of filtres; track f.val) {
        <button class="btn btn-sm"
                [class.btn-primary]="filtre() === f.val"
                [class.btn-outline-secondary]="filtre() !== f.val"
                (click)="filtre.set(f.val)">
          {{ f.label }}
          @if (compter(f.val) > 0 && f.val !== 'tous') {
            <span class="badge ms-1"
                  [class.bg-white]="filtre() === f.val"
                  [class.text-primary]="filtre() === f.val"
                  [class.bg-secondary]="filtre() !== f.val">
              {{ compter(f.val) }}
            </span>
          }
        </button>
      }
    </div>

    <!-- ── Liste RDV ──────────────────────────────────────────────────── -->
    @if (svc.chargement()) {
      <div class="placeholder-glow d-flex flex-column gap-3">
        @for (i of [1,2,3]; track i) {
          <div class="placeholder w-100" style="height:100px;border-radius:12px;"></div>
        }
      </div>
    } @else if (rdvsAffichees().length === 0) {
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
        <p class="fw-semibold">Aucun rendez-vous{{ filtre() !== 'tous' ? ' pour ce filtre' : '' }}</p>
      </div>
    } @else {
      <div class="d-flex flex-column gap-3">
        @for (rdv of rdvsAffichees(); track rdv.id) {
          <div class="card border-0 shadow-sm rdv-card">
            <div class="card-body">
              <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">

                <!-- Info RDV -->
                <div>
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="fw-bold" style="font-size:.95rem;">
                      Dr {{ rdv.medecin?.prenom }} {{ rdv.medecin?.nom }}
                    </span>
                    <span class="badge rounded-pill" [ngClass]="badgeStatut(rdv.statut)">
                      {{ labelStatut(rdv.statut) }}
                    </span>
                    @if (estAvenir(rdv)) {
                      <span class="badge rounded-pill bg-primary-subtle text-primary"
                            style="font-size:.65rem;">À venir</span>
                    }
                  </div>
                  <div class="text-muted small">
                    <i class="bi bi-person-badge me-1"></i>
                    {{ rdv.medecin?.specialisation }}
                  </div>
                  <div class="mt-1 d-flex align-items-center gap-3">
                    <span class="small">
                      <i class="bi bi-calendar3 me-1 text-primary"></i>
                      {{ rdv.dateHeure | date:'EEEE d MMMM yyyy':'':'fr' }}
                    </span>
                    <span class="small fw-semibold">
                      <i class="bi bi-clock me-1 text-primary"></i>
                      {{ rdv.dateHeure | date:'HH:mm' }}
                    </span>
                  </div>
                  @if (rdv.motifAnnulation) {
                    <div class="text-muted small fst-italic mt-1">
                      <i class="bi bi-chat-left-text me-1"></i>{{ rdv.motifAnnulation }}
                    </div>
                  }
                </div>

                <!-- Action annuler -->
                @if (estAvenir(rdv) && rdv.statut !== 'annule') {
                  <button class="btn btn-sm btn-outline-danger"
                          [disabled]="enCours() === rdv.id"
                          (click)="annuler(rdv)">
                    @if (enCours() === rdv.id) {
                      <span class="spinner-border spinner-border-sm me-1"></span>
                    }
                    <i class="bi bi-x-circle me-1"></i>Annuler
                  </button>
                }
              </div>
            </div>
          </div>
        }
      </div>
    }
  `,
})
export class RdvComponent implements OnInit {

  svc     = inject(RdvService);
  filtre  = signal<FiltrRdv>('tous');
  showForm = signal<boolean>(false);
  saving   = signal<boolean>(false);
  enCours  = signal<number | null>(null);
  erreurForm = signal<string | null>(null);

  today = new Date().toISOString().split('T')[0];

  form = { medecinId: 0, date: '', creneau: '' };

  filtres: { val: FiltrRdv; label: string }[] = [
    { val: 'tous',    label: 'Tous' },
    { val: 'avenir',  label: 'À venir' },
    { val: 'passes',  label: 'Passés' },
    { val: 'annules', label: 'Annulés' },
  ];

  rdvsAffichees = computed(() => {
    const f    = this.filtre();
    const list = this.svc.rdvs();
    const now  = new Date();
    if (f === 'avenir')  return list.filter(r => r.statut !== 'annule' && new Date(r.dateHeure) >= now);
    if (f === 'passes')  return list.filter(r => r.statut !== 'annule' && new Date(r.dateHeure) < now);
    if (f === 'annules') return list.filter(r => r.statut === 'annule');
    return list;
  });

  ngOnInit(): void {
    this.svc.chargerRdv();
    this.svc.chargerMedecins();
  }

  compter(f: FiltrRdv): number {
    const now = new Date();
    const list = this.svc.rdvs();
    if (f === 'avenir')  return list.filter(r => r.statut !== 'annule' && new Date(r.dateHeure) >= now).length;
    if (f === 'passes')  return list.filter(r => r.statut !== 'annule' && new Date(r.dateHeure) < now).length;
    if (f === 'annules') return list.filter(r => r.statut === 'annule').length;
    return list.length;
  }

  onMedecinChange(): void {
    this.form.creneau = '';
    if (this.form.medecinId > 0 && this.form.date) {
      this.svc.chargerCreneaux(this.form.medecinId, this.form.date);
    }
  }

  onDateChange(): void {
    this.form.creneau = '';
    if (this.form.medecinId > 0 && this.form.date) {
      this.svc.chargerCreneaux(this.form.medecinId, this.form.date);
    }
  }

  confirmerRdv(): void {
    if (!this.form.medecinId || !this.form.date || !this.form.creneau) return;
    this.saving.set(true);
    this.erreurForm.set(null);
    const dateHeure = `${this.form.date} ${this.form.creneau}:00`;
    this.svc.creer({ medecinId: this.form.medecinId, dateHeure }).subscribe({
      next: () => {
        this.annulerForm();
        this.svc.chargerRdv();
      },
      error   : err => { this.erreurForm.set(err.error?.error ?? 'Erreur lors de la réservation'); this.saving.set(false); },
      complete: () => this.saving.set(false),
    });
  }

  annulerForm(): void {
    this.showForm.set(false);
    this.form = { medecinId: 0, date: '', creneau: '' };
    this.erreurForm.set(null);
  }

  annuler(rdv: IRdv): void {
    const motif = prompt(`Motif d'annulation du RDV du ${new Date(rdv.dateHeure).toLocaleDateString('fr')} ?`);
    if (!motif?.trim()) return;
    this.enCours.set(rdv.id);
    this.svc.annuler(rdv.id, motif).subscribe({
      next    : res => this.svc.majRdvLocal(res.data),
      error   : err => alert(err.error?.error ?? 'Erreur lors de l\'annulation'),
      complete: () => this.enCours.set(null),
    });
  }

  estAvenir(rdv: IRdv): boolean {
    return new Date(rdv.dateHeure) >= new Date();
  }

  badgeStatut(s: string): string {
    return s === 'confirme' ? 'bg-success' : s === 'annule' ? 'bg-danger' : 'bg-warning text-dark';
  }

  labelStatut(s: string): string {
    return s === 'confirme' ? 'Confirmé' : s === 'annule' ? 'Annulé' : 'En attente';
  }
}
