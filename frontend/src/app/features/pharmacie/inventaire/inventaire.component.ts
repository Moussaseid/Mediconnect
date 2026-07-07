// features/pharmacie/inventaire/inventaire.component.ts
import {
  Component, OnInit, computed, signal, inject,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule }  from '@angular/forms';

import { PharmacieService } from '../../../core/services/pharmacie.service';
import { IInventaire, IPharmacie } from '../../../core/models/interfaces';

type SortField = 'nom' | 'quantite' | 'prixUnitaire' | 'datePeremption';
type SortDir   = 'asc' | 'desc';

interface EditForm {
  quantite       : number;
  prixUnitaire   : number | null;
  datePeremption : string;
}

@Component({
  selector  : 'app-inventaire',
  standalone: true,
  imports   : [CommonModule, FormsModule],
  styles: [`
    .table th { font-size: .72rem; letter-spacing: .06em; text-transform: uppercase;
                white-space: nowrap; cursor: pointer; user-select: none; }
    .table th:hover { background: #e9ecef; }
    .sort-icon { opacity: .35; }
    .sort-icon.actif { opacity: 1; }
    .table tbody tr { transition: background .1s; }
    .table tbody tr:hover { background: #f8f9fa; }
    .edit-input { max-width: 90px; }
    .edit-input-prix { max-width: 100px; }
    .edit-input-date { max-width: 150px; }
    .pharmacie-card { border-radius: 12px; cursor: pointer; transition: box-shadow .15s, transform .1s; }
    .pharmacie-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); transform: translateY(-1px); }
    .pharmacie-card.selected { border-color: #198754 !important; background: #f0fdf4; }
    .section-title { font-size: .7rem; letter-spacing: .1em; text-transform: uppercase;
                     font-weight: 700; color: #6c757d; }
  `],
  template: `
    <!-- ── En-tête ─────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Stock & Inventaire</h4>
        <p class="text-muted small mb-0">
          @if (svc.pharmacieSelectionnee(); as p) {
            {{ p.nom }}
            @if (p.ville) { — {{ p.ville }} }
          } @else {
            Sélectionnez une pharmacie
          }
        </p>
      </div>
      @if (svc.pharmacieSelectionnee()) {
        <button class="btn btn-outline-success btn-sm" (click)="rafraichir()"
                [disabled]="svc.chargement()">
          <i class="bi bi-arrow-clockwise me-1"></i>Actualiser
        </button>
      }
    </div>

    <!-- ── Erreur ───────────────────────────────────────────────────── -->
    @if (svc.erreur()) {
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>{{ svc.erreur() }}
      </div>
    }
    @if (erreurEdition()) {
      <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-circle-fill"></i>{{ erreurEdition() }}
        <button type="button" class="btn-close ms-auto" (click)="erreurEdition.set(null)"></button>
      </div>
    }

    <!-- ── Sélecteur de pharmacie ────────────────────────────────────── -->
    @if (!svc.pharmacieSelectionnee()) {
      @if (svc.chargement()) {
        <div class="text-center py-5">
          <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Chargement…</span>
          </div>
        </div>
      } @else if (svc.pharmacies().length === 0) {
        <div class="text-center py-5 text-muted">
          <i class="bi bi-building-x fs-1 d-block mb-2"></i>
          Aucune pharmacie enregistrée.
        </div>
      } @else {
        <p class="section-title mb-3">Choisir une pharmacie</p>
        <div class="row g-3">
          @for (p of svc.pharmacies(); track p.id) {
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card border-2 pharmacie-card" (click)="svc.selectionnerPharmacie(p)">
                <div class="card-body">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-capsule-pill text-success fs-5"></i>
                    <span class="fw-semibold">{{ p.nom }}</span>
                  </div>
                  @if (p.adresse) { <div class="small text-muted">{{ p.adresse }}</div> }
                  @if (p.ville)   { <div class="small text-muted">{{ p.ville }}</div> }
                  @if (p.telephone) { <div class="small text-muted"><i class="bi bi-telephone me-1"></i>{{ p.telephone }}</div> }
                </div>
              </div>
            </div>
          }
        </div>
      }
    }

    <!-- ── Inventaire ─────────────────────────────────────────────── -->
    @if (svc.pharmacieSelectionnee()) {

      <!-- Changer de pharmacie + filtre -->
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if (svc.pharmacies().length > 1) {
          <button class="btn btn-sm btn-outline-secondary"
                  (click)="svc.pharmacieSelectionnee.set(null)">
            <i class="bi bi-arrow-left me-1"></i>Changer
          </button>
        }
        <div class="input-group input-group-sm ms-auto" style="max-width:280px;">
          <span class="input-group-text bg-white">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input type="search" class="form-control border-start-0"
                 placeholder="Rechercher un médicament…"
                 [(ngModel)]="rechercheTexte"
                 (ngModelChange)="recherche.set($event)">
        </div>
        <!-- Légende alertes -->
        <div class="d-flex align-items-center gap-2 ms-2">
          <span class="badge rounded-pill bg-danger">0</span>
          <span class="badge rounded-pill bg-warning text-dark">&lt; 30</span>
          <span class="badge rounded-pill bg-success">OK</span>
        </div>
      </div>

      <!-- Stats rapides -->
      @if (!svc.chargement()) {
        <div class="row g-2 mb-3">
          <div class="col-auto">
            <div class="card border-0 bg-white shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
              <i class="bi bi-box-seam text-primary"></i>
              <div>
                <div class="fw-bold lh-1">{{ svc.inventaire().length }}</div>
                <div style="font-size:.7rem;" class="text-muted">références</div>
              </div>
            </div>
          </div>
          <div class="col-auto">
            <div class="card border-0 bg-white shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
              <i class="bi bi-exclamation-triangle text-warning"></i>
              <div>
                <div class="fw-bold lh-1 text-warning">{{ nbAlertes() }}</div>
                <div style="font-size:.7rem;" class="text-muted">alertes stock</div>
              </div>
            </div>
          </div>
          <div class="col-auto">
            <div class="card border-0 bg-white shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
              <i class="bi bi-x-circle text-danger"></i>
              <div>
                <div class="fw-bold lh-1 text-danger">{{ nbEpuises() }}</div>
                <div style="font-size:.7rem;" class="text-muted">épuisés</div>
              </div>
            </div>
          </div>
        </div>
      }

      <!-- Skeleton chargement -->
      @if (svc.chargement()) {
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            @for (i of [1,2,3,4,5]; track i) {
              <div class="placeholder-glow d-flex gap-3 py-2 border-bottom">
                <span class="placeholder col-3 rounded"></span>
                <span class="placeholder col-1 rounded"></span>
                <span class="placeholder col-2 rounded"></span>
                <span class="placeholder col-2 rounded"></span>
              </div>
            }
          </div>
        </div>
      }

      <!-- État vide -->
      @if (!svc.chargement() && inventaireAffiche().length === 0 && svc.inventaire().length === 0) {
        <div class="text-center py-5 text-muted">
          <i class="bi bi-box fs-1 d-block mb-2"></i>
          <p class="fw-semibold">Aucun médicament en stock</p>
          <p class="small">Ajoutez des médicaments via le module d'approvisionnement.</p>
        </div>
      }

      <!-- Pas de résultat filtre -->
      @if (!svc.chargement() && inventaireAffiche().length === 0 && svc.inventaire().length > 0) {
        <div class="text-center py-4 text-muted">
          <i class="bi bi-search fs-3 d-block mb-1"></i>
          Aucun médicament ne correspond à « {{ recherche() }} »
        </div>
      }

      <!-- Tableau -->
      @if (!svc.chargement() && inventaireAffiche().length > 0) {
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light border-bottom">
                <tr>
                  <th (click)="toggleSort('nom')" class="ps-3">
                    Médicament
                    <i class="bi ms-1"
                       [class]="sortIcon('nom')"
                       [class.actif]="sortField() === 'nom'"></i>
                  </th>
                  <th>Forme / Dosage</th>
                  <th class="text-center" (click)="toggleSort('quantite')">
                    Quantité
                    <i class="bi ms-1"
                       [class]="sortIcon('quantite')"
                       [class.actif]="sortField() === 'quantite'"></i>
                  </th>
                  <th class="text-end" (click)="toggleSort('prixUnitaire')">
                    Prix unit.
                    <i class="bi ms-1"
                       [class]="sortIcon('prixUnitaire')"
                       [class.actif]="sortField() === 'prixUnitaire'"></i>
                  </th>
                  <th (click)="toggleSort('datePeremption')">
                    Péremption
                    <i class="bi ms-1"
                       [class]="sortIcon('datePeremption')"
                       [class.actif]="sortField() === 'datePeremption'"></i>
                  </th>
                  <th class="text-center pe-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                @for (ligne of inventaireAffiche(); track ligne.id) {
                  <tr [class.table-warning]="ligne.quantite > 0 && ligne.quantite < 30"
                      [class.table-danger]="ligne.quantite === 0">
                    <!-- Médicament -->
                    <td class="ps-3">
                      <div class="fw-semibold small">{{ ligne.medicament?.nom ?? '—' }}</div>
                      @if (ligne.medicament?.surOrdonnance) {
                        <span class="badge rounded-pill bg-info-subtle text-info"
                              style="font-size:.6rem;">Ordonnance</span>
                      }
                    </td>

                    <!-- Forme / Dosage -->
                    <td class="small text-muted">
                      {{ ligne.medicament?.forme ?? '' }}
                      @if (ligne.medicament?.dosage) { · {{ ligne.medicament!.dosage }} }
                    </td>

                    <!-- Quantité -->
                    <td class="text-center">
                      @if (editingId() === ligne.id) {
                        <input type="number" min="0"
                               class="form-control form-control-sm edit-input text-center"
                               [(ngModel)]="editForm.quantite">
                      } @else {
                        <span class="badge rounded-pill fs-6 fw-bold"
                              [class]="badgeCouleur(ligne.quantite)">
                          {{ ligne.quantite }}
                          @if (ligne.quantite > 0 && ligne.quantite < 30) { ⚠ }
                        </span>
                      }
                    </td>

                    <!-- Prix unitaire -->
                    <td class="text-end">
                      @if (editingId() === ligne.id) {
                        <input type="number" min="0" step="0.01"
                               class="form-control form-control-sm edit-input-prix text-end ms-auto"
                               [(ngModel)]="editForm.prixUnitaire"
                               placeholder="0.00">
                      } @else {
                        <span class="small">
                          @if (ligne.prixUnitaire != null) {
                            {{ ligne.prixUnitaire | number:'1.2-2' }} €
                          } @else { — }
                        </span>
                      }
                    </td>

                    <!-- Péremption -->
                    <td>
                      @if (editingId() === ligne.id) {
                        <input type="date"
                               class="form-control form-control-sm edit-input-date"
                               [(ngModel)]="editForm.datePeremption">
                      } @else {
                        <span class="small"
                              [class.text-danger]="estPerime(ligne.datePeremption)"
                              [class.text-warning]="bientotPerime(ligne.datePeremption)">
                          @if (ligne.datePeremption) {
                            {{ ligne.datePeremption | date:'dd/MM/yyyy' }}
                            @if (estPerime(ligne.datePeremption)) { <i class="bi bi-exclamation-circle-fill ms-1"></i> }
                          } @else { — }
                        </span>
                      }
                    </td>

                    <!-- Actions -->
                    <td class="text-center pe-3">
                      @if (editingId() === ligne.id) {
                        <div class="d-flex justify-content-center gap-1">
                          <button class="btn btn-sm btn-success"
                                  [disabled]="sauvegardEnCours()"
                                  (click)="sauvegarder(ligne)">
                            @if (sauvegardEnCours()) {
                              <span class="spinner-border spinner-border-sm"></span>
                            } @else {
                              <i class="bi bi-check-lg"></i>
                            }
                          </button>
                          <button class="btn btn-sm btn-outline-secondary"
                                  [disabled]="sauvegardEnCours()"
                                  (click)="annulerEdition()">
                            <i class="bi bi-x-lg"></i>
                          </button>
                        </div>
                      } @else {
                        <button class="btn btn-sm btn-outline-secondary"
                                (click)="ouvrirEdition(ligne)"
                                title="Modifier">
                          <i class="bi bi-pencil"></i>
                        </button>
                      }
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <!-- Pied de tableau -->
          <div class="card-footer bg-transparent border-top-0 py-2 px-3">
            <small class="text-muted">
              {{ inventaireAffiche().length }} référence(s) affichée(s)
              @if (recherche()) { sur {{ svc.inventaire().length }} au total }
            </small>
          </div>
        </div>
      }
    }
  `,
})
export class InventaireComponent implements OnInit {

  svc = inject(PharmacieService);

  // ── État édition ──────────────────────────────────────────────────────────
  readonly editingId       = signal<number | null>(null);
  readonly sauvegardEnCours = signal<boolean>(false);
  readonly erreurEdition   = signal<string | null>(null);

  editForm: EditForm = { quantite: 0, prixUnitaire: null, datePeremption: '' };

  // ── Tri et filtre ─────────────────────────────────────────────────────────
  readonly sortField  = signal<SortField>('nom');
  readonly sortDir    = signal<SortDir>('asc');
  readonly recherche  = signal<string>('');
  rechercheTexte      = '';   // liaison [(ngModel)] → met à jour le signal via (ngModelChange)

  // ── Inventaire traité (filtre + tri) ─────────────────────────────────────
  readonly inventaireAffiche = computed(() => {
    const liste = this.svc.inventaire();
    const q     = this.recherche().toLowerCase().trim();
    const field = this.sortField();
    const dir   = this.sortDir();

    const filtre = q
      ? liste.filter(i => i.medicament?.nom?.toLowerCase().includes(q))
      : [...liste];

    return filtre.sort((a, b) => {
      let va: string | number = 0, vb: string | number = 0;
      switch (field) {
        case 'nom'          : va = a.medicament?.nom ?? ''; vb = b.medicament?.nom ?? ''; break;
        case 'quantite'     : va = a.quantite;              vb = b.quantite;              break;
        case 'prixUnitaire' : va = a.prixUnitaire ?? -1;    vb = b.prixUnitaire ?? -1;    break;
        case 'datePeremption': va = a.datePeremption ?? ''; vb = b.datePeremption ?? ''; break;
      }
      const cmp = va < vb ? -1 : va > vb ? 1 : 0;
      return dir === 'asc' ? cmp : -cmp;
    });
  });

  readonly nbAlertes = computed(() =>
    this.svc.inventaire().filter(i => i.quantite > 0 && i.quantite < 30).length
  );
  readonly nbEpuises = computed(() =>
    this.svc.inventaire().filter(i => i.quantite === 0).length
  );

  ngOnInit(): void {
    this.svc.chargerPharmacies();
  }

  rafraichir(): void {
    const p = this.svc.pharmacieSelectionnee();
    if (p) this.svc.chargerInventaire(p.id);
  }

  // ── Tri ───────────────────────────────────────────────────────────────────
  toggleSort(field: SortField): void {
    if (this.sortField() === field) {
      this.sortDir.update(d => d === 'asc' ? 'desc' : 'asc');
    } else {
      this.sortField.set(field);
      this.sortDir.set('asc');
    }
    this.annulerEdition();
  }

  sortIcon(field: SortField): string {
    if (this.sortField() !== field) return 'bi-arrow-down-up sort-icon';
    return this.sortDir() === 'asc' ? 'bi-sort-up sort-icon actif' : 'bi-sort-down sort-icon actif';
  }

  // ── Édition inline ────────────────────────────────────────────────────────
  ouvrirEdition(ligne: IInventaire): void {
    this.erreurEdition.set(null);
    this.editingId.set(ligne.id);
    this.editForm = {
      quantite       : ligne.quantite,
      prixUnitaire   : ligne.prixUnitaire ?? null,
      datePeremption : ligne.datePeremption ?? '',
    };
  }

  annulerEdition(): void {
    this.editingId.set(null);
    this.erreurEdition.set(null);
  }

  sauvegarder(ligne: IInventaire): void {
    if (this.editForm.quantite < 0) {
      this.erreurEdition.set('La quantité ne peut pas être négative.');
      return;
    }
    this.sauvegardEnCours.set(true);
    this.erreurEdition.set(null);

    this.svc.mettreAJourLigne(ligne.id, {
      quantite       : this.editForm.quantite,
      prixUnitaire   : this.editForm.prixUnitaire,
      datePeremption : this.editForm.datePeremption || null,
    }).subscribe({
      next: res => {
        this.svc.majLigneLocale(res.data);
        this.editingId.set(null);
      },
      error: err => {
        this.erreurEdition.set(err.error?.error ?? 'Erreur lors de la mise à jour');
      },
      complete: () => this.sauvegardEnCours.set(false),
    });
  }

  // ── Helpers visuels ───────────────────────────────────────────────────────
  badgeCouleur(q: number): string {
    if (q === 0) return 'bg-danger';
    if (q < 30)  return 'bg-warning text-dark';
    return 'bg-success';
  }

  estPerime(date?: string): boolean {
    if (!date) return false;
    return new Date(date) < new Date();
  }

  bientotPerime(date?: string): boolean {
    if (!date) return false;
    const d = new Date(date);
    const limite = new Date();
    limite.setDate(limite.getDate() + 30);
    return d >= new Date() && d <= limite;
  }
}