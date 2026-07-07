// features/pharmacie/commande-pharmacie/commande-pharmacie.component.ts
import { Component, OnInit, computed, signal, inject } from '@angular/core';
import { CommonModule }     from '@angular/common';
import { PharmacieService } from '../../../core/services/pharmacie.service';
import { CommandeService }  from '../../../core/services/commande.service';
import { ICommande, CommandeStatut } from '../../../core/models/interfaces';

@Component({
  selector  : 'app-commande-pharmacie',
  standalone: true,
  imports   : [CommonModule],
  styles: [`
    .pharmacie-card { border-radius:12px; cursor:pointer; transition:box-shadow .15s, transform .1s; }
    .pharmacie-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.1); transform:translateY(-1px); }
    .section-title { font-size:.7rem; letter-spacing:.1em; text-transform:uppercase;
                     font-weight:700; color:#6c757d; }
    .commande-card { border-radius:12px; transition:box-shadow .12s; }
    .commande-card:hover { box-shadow:0 2px 12px rgba(0,0,0,.08); }
    .lignes-table th { font-size:.7rem; letter-spacing:.05em; text-transform:uppercase; }
    .statut-badge { font-size:.72rem; letter-spacing:.04em; }
  `],
  template: `
    <!-- ── En-tête ────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Commandes</h4>
        <p class="text-muted small mb-0">
          @if (pharSvc.pharmacieSelectionnee(); as p) {
            {{ p.nom }}@if (p.ville) { — {{ p.ville }} }
          } @else {
            Sélectionnez une pharmacie
          }
        </p>
      </div>
      @if (pharSvc.pharmacieSelectionnee()) {
        <button class="btn btn-outline-success btn-sm" (click)="rafraichir()"
                [disabled]="cmdSvc.chargement()">
          <i class="bi bi-arrow-clockwise me-1"></i>Actualiser
        </button>
      }
    </div>

    <!-- ── Erreur ────────────────────────────────────────────────────── -->
    @if (cmdSvc.erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>{{ cmdSvc.erreur() }}
      </div>
    }

    <!-- ── Sélecteur pharmacie ───────────────────────────────────────── -->
    @if (!pharSvc.pharmacieSelectionnee()) {
      @if (pharSvc.chargement()) {
        <div class="text-center py-5">
          <div class="spinner-border text-success" role="status"></div>
        </div>
      } @else if (pharSvc.pharmacies().length === 0) {
        <div class="text-center py-5 text-muted">
          <i class="bi bi-building-x fs-1 d-block mb-2"></i>
          Aucune pharmacie enregistrée.
        </div>
      } @else {
        <p class="section-title mb-3">Choisir une pharmacie</p>
        <div class="row g-3">
          @for (p of pharSvc.pharmacies(); track p.id) {
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card border-2 pharmacie-card" (click)="selectionner(p.id)">
                <div class="card-body">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-capsule-pill text-success fs-5"></i>
                    <span class="fw-semibold">{{ p.nom }}</span>
                  </div>
                  @if (p.adresse)   { <div class="small text-muted">{{ p.adresse }}</div> }
                  @if (p.ville)     { <div class="small text-muted">{{ p.ville }}</div> }
                  @if (p.telephone) {
                    <div class="small text-muted">
                      <i class="bi bi-telephone me-1"></i>{{ p.telephone }}
                    </div>
                  }
                </div>
              </div>
            </div>
          }
        </div>
      }
    }

    <!-- ── Vue commandes ────────────────────────────────────────────── -->
    @if (pharSvc.pharmacieSelectionnee()) {

      <!-- Changer de pharmacie + filtres statut -->
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if (pharSvc.pharmacies().length > 1) {
          <button class="btn btn-sm btn-outline-secondary"
                  (click)="pharSvc.pharmacieSelectionnee.set(null)">
            <i class="bi bi-arrow-left me-1"></i>Changer
          </button>
        }
        <div class="ms-auto d-flex gap-2 flex-wrap">
          @for (s of statuts; track s.val) {
            <button class="btn btn-sm"
                    [class.btn-success]="filtreStatut() === s.val"
                    [class.btn-outline-secondary]="filtreStatut() !== s.val"
                    (click)="filtreStatut.set(s.val)">
              {{ s.label }}
              @if (compterStatut(s.val) > 0) {
                <span class="badge ms-1"
                      [class.bg-white]="filtreStatut() === s.val"
                      [class.text-success]="filtreStatut() === s.val"
                      [class.bg-secondary]="filtreStatut() !== s.val">
                  {{ compterStatut(s.val) }}
                </span>
              }
            </button>
          }
        </div>
      </div>

      <!-- Skeleton chargement -->
      @if (cmdSvc.chargement()) {
        <div class="placeholder-glow d-flex flex-column gap-3">
          @for (i of [1,2,3]; track i) {
            <div class="placeholder w-100" style="height:120px;border-radius:12px;"></div>
          }
        </div>
      }

      <!-- État vide -->
      @if (!cmdSvc.chargement() && commandesAffichees().length === 0) {
        <div class="text-center py-5 text-muted">
          <i class="bi bi-bag-x fs-1 d-block mb-2"></i>
          @if (filtreStatut() === 'tous') {
            <p class="fw-semibold">Aucune commande reçue</p>
          } @else {
            <p class="fw-semibold">Aucune commande « {{ labelStatut(filtreStatut()) }} »</p>
          }
        </div>
      }

      <!-- Cartes commandes -->
      @if (!cmdSvc.chargement()) {
        <div class="d-flex flex-column gap-3">
          @for (cmd of commandesAffichees(); track cmd.id) {
            <div class="card border-0 shadow-sm commande-card">
              <div class="card-body">

                <!-- En-tête commande -->
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="fw-bold" style="font-size:.9rem;">#{{ cmd.id }}</span>
                      <span class="badge rounded-pill statut-badge" [ngClass]="badgeStatut(cmd.statut)">
                        {{ labelStatut(cmd.statut) }}
                      </span>
                      @if (cmd.modeRetrait === 'livraison') {
                        <span class="badge rounded-pill bg-info-subtle text-info statut-badge">
                          <i class="bi bi-truck me-1"></i>Livraison
                        </span>
                      } @else {
                        <span class="badge rounded-pill bg-light text-muted statut-badge border">
                          <i class="bi bi-shop me-1"></i>Sur place
                        </span>
                      }
                    </div>
                    <div class="text-muted small mt-1">
                      <i class="bi bi-calendar3 me-1"></i>{{ cmd.createdAt | date:'dd/MM/yyyy à HH:mm' }}
                      · Patient #{{ cmd.patientId }}
                    </div>
                  </div>

                  <!-- Actions statut -->
                  <div class="d-flex gap-2 flex-wrap">
                    @if (cmd.statut === 'en_attente') {
                      <button class="btn btn-sm btn-primary"
                              [disabled]="enCours() === cmd.id"
                              (click)="changerStatut(cmd, 'preparee')">
                        @if (enCours() === cmd.id) {
                          <span class="spinner-border spinner-border-sm me-1"></span>
                        }
                        <i class="bi bi-play-circle me-1"></i>Prendre en charge
                      </button>
                      <button class="btn btn-sm btn-outline-danger"
                              [disabled]="enCours() === cmd.id"
                              (click)="annuler(cmd)">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                      </button>
                    }
                    @if (cmd.statut === 'preparee') {
                      <button class="btn btn-sm btn-success"
                              [disabled]="enCours() === cmd.id"
                              (click)="changerStatut(cmd, 'prete')">
                        @if (enCours() === cmd.id) {
                          <span class="spinner-border spinner-border-sm me-1"></span>
                        }
                        <i class="bi bi-check-circle me-1"></i>Marquer prête
                      </button>
                      <button class="btn btn-sm btn-outline-danger"
                              [disabled]="enCours() === cmd.id"
                              (click)="annuler(cmd)">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                      </button>
                    }
                    @if (cmd.statut === 'prete') {
                      <button class="btn btn-sm btn-success"
                              [disabled]="enCours() === cmd.id"
                              (click)="changerStatut(cmd, 'livree')">
                        @if (enCours() === cmd.id) {
                          <span class="spinner-border spinner-border-sm me-1"></span>
                        }
                        <i class="bi bi-bag-check me-1"></i>
                        {{ cmd.modeRetrait === 'livraison' ? 'Marquer livrée' : 'Marquer retirée' }}
                      </button>
                    }
                  </div>
                </div>

                <!-- Adresse livraison + notes -->
                @if (cmd.adresseLivraison || cmd.notes) {
                  <div class="d-flex flex-wrap gap-3 mb-2">
                    @if (cmd.adresseLivraison) {
                      <div class="small text-muted">
                        <i class="bi bi-geo-alt me-1"></i>{{ cmd.adresseLivraison }}
                      </div>
                    }
                    @if (cmd.notes) {
                      <div class="small text-muted fst-italic">
                        <i class="bi bi-chat-left-text me-1"></i>{{ cmd.notes }}
                      </div>
                    }
                  </div>
                }

                <!-- Détail lignes -->
                <div class="d-flex align-items-center gap-2 mb-2">
                  <button class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                          (click)="toggleExpand(cmd.id)">
                    <i class="bi me-1"
                       [class.bi-chevron-down]="expandedId() !== cmd.id"
                       [class.bi-chevron-up]="expandedId() === cmd.id"></i>
                    {{ (cmd.lignes?.length ?? 0) }} médicament(s)
                  </button>
                </div>

                @if (expandedId() === cmd.id && cmd.lignes && cmd.lignes.length > 0) {
                  <div class="table-responsive mt-1">
                    <table class="table table-sm mb-0">
                      <thead class="table-light lignes-table">
                        <tr>
                          <th>Médicament</th>
                          <th class="text-center">Qté</th>
                          <th class="text-end">Prix unit.</th>
                          <th class="text-end">Sous-total</th>
                        </tr>
                      </thead>
                      <tbody>
                        @for (l of cmd.lignes; track l.id) {
                          <tr>
                            <td class="small">
                              {{ l.medicament?.nom ?? 'Médicament #' + l.medicamentId }}
                            </td>
                            <td class="text-center small">{{ l.quantite }}</td>
                            <td class="text-end small">{{ l.prixAchat | number:'1.2-2' }} €</td>
                            <td class="text-end small fw-semibold">
                              {{ (l.prixAchat * l.quantite) | number:'1.2-2' }} €
                            </td>
                          </tr>
                        }
                        <tr class="table-light fw-bold">
                          <td colspan="3" class="text-end small">Total</td>
                          <td class="text-end small">{{ totalCommande(cmd) | number:'1.2-2' }} €</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                }

              </div>
            </div>
          }
        </div>
      }
    }
  `,
})
export class CommandePharmacieComponent implements OnInit {

  pharSvc = inject(PharmacieService);
  cmdSvc  = inject(CommandeService);

  filtreStatut = signal<string>('en_attente');
  expandedId   = signal<number | null>(null);
  enCours      = signal<number | null>(null);

  statuts = [
    { val: 'tous',       label: 'Toutes'         },
    { val: 'en_attente', label: 'En attente'      },
    { val: 'preparee',   label: 'En préparation'  },
    { val: 'prete',      label: 'Prêtes'          },
    { val: 'livree',     label: 'Livrées'         },
    { val: 'annulee',    label: 'Annulées'        },
  ];

  commandesAffichees = computed(() => {
    const f = this.filtreStatut();
    return f === 'tous'
      ? this.cmdSvc.commandes()
      : this.cmdSvc.commandes().filter(c => c.statut === f);
  });

  ngOnInit(): void {
    this.pharSvc.chargerPharmacies();
  }

  selectionner(pharmacieId: number): void {
    const p = this.pharSvc.pharmacies().find(ph => ph.id === pharmacieId);
    if (p) {
      this.pharSvc.pharmacieSelectionnee.set(p);
      this.cmdSvc.chargerParPharmacie(pharmacieId);
    }
  }

  rafraichir(): void {
    const p = this.pharSvc.pharmacieSelectionnee();
    if (p) this.cmdSvc.chargerParPharmacie(p.id);
  }

  toggleExpand(id: number): void {
    this.expandedId.update(cur => cur === id ? null : id);
  }

  compterStatut(statut: string): number {
    if (statut === 'tous') return this.cmdSvc.commandes().length;
    return this.cmdSvc.commandes().filter(c => c.statut === statut).length;
  }

  changerStatut(cmd: ICommande, statut: CommandeStatut): void {
    this.enCours.set(cmd.id);
    this.cmdSvc.mettreAJourStatut(cmd.id, statut).subscribe({
      next    : res => this.cmdSvc.majCommandeLocale(res.data),
      error   : err => alert(err.error?.error ?? 'Erreur lors de la mise à jour'),
      complete: () => this.enCours.set(null),
    });
  }

  annuler(cmd: ICommande): void {
    if (!confirm(`Annuler la commande #${cmd.id} ?`)) return;
    this.changerStatut(cmd, 'annulee');
  }

  totalCommande(cmd: ICommande): number {
    return (cmd.lignes ?? []).reduce((sum, l) => sum + l.prixAchat * l.quantite, 0);
  }

  badgeStatut(s: string): string {
    const m: Record<string, string> = {
      en_attente: 'bg-warning text-dark',
      preparee  : 'bg-info text-dark',
      prete     : 'bg-primary',
      livree    : 'bg-success',
      annulee   : 'bg-danger',
    };
    return m[s] ?? 'bg-secondary';
  }

  labelStatut(s: string): string {
    const m: Record<string, string> = {
      en_attente: 'En attente',
      preparee  : 'En préparation',
      prete     : 'Prête',
      livree    : 'Livrée',
      annulee   : 'Annulée',
      tous      : 'Toutes',
    };
    return m[s] ?? s;
  }
}
