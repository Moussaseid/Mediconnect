import {
  Component, OnInit, inject, signal, computed, ViewChild, ElementRef
} from '@angular/core';
import { CommonModule }         from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { CentreAnalyseService } from '../../../core/services/centre-analyse.service';
import { IAnalysePropre }       from '../../../core/models/interfaces';

@Component({
  selector  : 'app-analyses',
  standalone: true,
  imports   : [CommonModule, ReactiveFormsModule],
  template  : `
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Mes analyses</h4>
      <button class="btn btn-success" (click)="ouvrirCreer()">
        <i class="bi bi-plus-lg me-1"></i> Nouvelle analyse
      </button>
    </div>

    <!-- Toast -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1100;">
      <div #toastEl class="toast align-items-center text-bg-success border-0"
           role="alert" aria-live="assertive">
        <div class="d-flex">
          <div class="toast-body">{{ toastMsg() }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto"
                  data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>

    <!-- Table -->
    @if (chargement()) {
      <div class="text-center py-5">
        <div class="spinner-border text-success"></div>
      </div>
    } @else if (!analyses().length) {
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
          <i class="bi bi-list-check fs-1 mb-2 d-block"></i>
          Aucune analyse — cliquez sur « Nouvelle analyse » pour commencer.
        </div>
      </div>
    } @else {
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Nom</th>
                <th>Description</th>
                <th class="text-end">Prix (€)</th>
                <th class="text-center">Durée (min)</th>
                <th class="text-center">Disponible</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (a of analyses(); track a.id) {
                <tr>
                  <td class="fw-semibold">{{ a.nom }}</td>
                  <td class="text-muted small">{{ a.description ?? '—' }}</td>
                  <td class="text-end">{{ a.prix.toFixed(2) }}</td>
                  <td class="text-center">{{ a.dureeMinutes }}</td>
                  <td class="text-center">
                    <div class="form-check form-switch d-flex justify-content-center m-0">
                      <input class="form-check-input" type="checkbox" role="switch"
                             [checked]="a.disponible"
                             (change)="toggleDispo(a)"
                             [id]="'sw' + a.id">
                    </div>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1" (click)="ouvrirModifier(a)">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" (click)="confirmerSuppression(a)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>
    }

    <!-- Modal créer / modifier -->
    <div class="modal fade" id="modalAnalyse" tabindex="-1" aria-hidden="true" #modalEl>
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editionId() ? 'Modifier' : 'Nouvelle' }} analyse</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form [formGroup]="form" (ngSubmit)="soumettre()">
            <div class="modal-body row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                <input formControlName="nom" class="form-control"
                       [class.is-invalid]="form.get('nom')!.invalid && form.get('nom')!.touched">
                <div class="invalid-feedback">Le nom est requis.</div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea formControlName="description" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Prix (€) <span class="text-danger">*</span></label>
                <input formControlName="prix" type="number" step="0.01" min="0" class="form-control"
                       [class.is-invalid]="form.get('prix')!.invalid && form.get('prix')!.touched">
                <div class="invalid-feedback">Prix invalide.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Durée (min) <span class="text-danger">*</span></label>
                <input formControlName="dureeMinutes" type="number" min="1" class="form-control"
                       [class.is-invalid]="form.get('dureeMinutes')!.invalid && form.get('dureeMinutes')!.touched">
                <div class="invalid-feedback">Durée invalide (min. 1 min).</div>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" formControlName="disponible" id="swDispo">
                  <label class="form-check-label" for="swDispo">Disponible immédiatement</label>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-success" [disabled]="form.invalid || enCours()">
                @if (enCours()) { <span class="spinner-border spinner-border-sm me-1"></span> }
                {{ editionId() ? 'Enregistrer' : 'Créer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal confirmation suppression -->
    <div class="modal fade" id="modalSuppression" tabindex="-1" aria-hidden="true" #modalDelEl>
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Supprimer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            Supprimer <strong>{{ analyseASupprimer()?.nom }}</strong> ? Cette action est irréversible.
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-danger btn-sm" (click)="supprimer()">Supprimer</button>
          </div>
        </div>
      </div>
    </div>
  `,
})
export class AnalysesComponent implements OnInit {
  private svc = inject(CentreAnalyseService);
  private fb  = inject(FormBuilder);

  @ViewChild('modalEl')    modalElRef!:    ElementRef;
  @ViewChild('modalDelEl') modalDelElRef!: ElementRef;
  @ViewChild('toastEl')    toastElRef!:    ElementRef;

  analyses  = this.svc.analyses;
  chargement = signal(true);
  enCours    = signal(false);
  editionId  = signal<number | null>(null);
  analyseASupprimer = signal<IAnalysePropre | null>(null);
  toastMsg   = signal('');

  form = this.fb.group({
    nom          : ['', Validators.required],
    description  : [''],
    prix         : [0, [Validators.required, Validators.min(0)]],
    dureeMinutes : [30, [Validators.required, Validators.min(1)]],
    disponible   : [true],
  });

  private modal!:    any;
  private modalDel!: any;

  ngOnInit(): void {
    this.svc.chargerAnalyses().subscribe({ complete: () => this.chargement.set(false) });
  }

  ouvrirCreer(): void {
    this.editionId.set(null);
    this.form.reset({ nom: '', description: '', prix: 0, dureeMinutes: 30, disponible: true });
    this.getModal().show();
  }

  ouvrirModifier(a: IAnalysePropre): void {
    this.editionId.set(a.id);
    this.form.setValue({
      nom         : a.nom,
      description : a.description ?? '',
      prix        : a.prix,
      dureeMinutes: a.dureeMinutes,
      disponible  : a.disponible,
    });
    this.getModal().show();
  }

  soumettre(): void {
    if (this.form.invalid) return;
    this.enCours.set(true);
    const data = {
      nom          : this.form.value.nom!,
      description  : this.form.value.description || undefined,
      prix         : this.form.value.prix!,
      dureeMinutes : this.form.value.dureeMinutes!,
      disponible   : this.form.value.disponible!,
    };
    const id = this.editionId();
    const req = id
      ? this.svc.modifier(id, data)
      : this.svc.creer(data);

    req.subscribe({
      next: () => {
        this.getModal().hide();
        this.afficherToast(id ? 'Analyse mise à jour' : 'Analyse créée');
      },
      complete: () => this.enCours.set(false),
    });
  }

  confirmerSuppression(a: IAnalysePropre): void {
    this.analyseASupprimer.set(a);
    this.getModalDel().show();
  }

  supprimer(): void {
    const a = this.analyseASupprimer();
    if (!a) return;
    this.svc.supprimer(a.id).subscribe(() => {
      this.getModalDel().hide();
      this.afficherToast('Analyse supprimée');
    });
  }

  toggleDispo(a: IAnalysePropre): void {
    this.svc.toggle(a.id).subscribe();
  }

  private getModal(): any {
    const { Modal } = (window as any).bootstrap ?? {};
    if (!this.modal && Modal) this.modal = new Modal(this.modalElRef.nativeElement);
    return this.modal;
  }

  private getModalDel(): any {
    const { Modal } = (window as any).bootstrap ?? {};
    if (!this.modalDel && Modal) this.modalDel = new Modal(this.modalDelElRef.nativeElement);
    return this.modalDel;
  }

  private afficherToast(msg: string): void {
    this.toastMsg.set(msg);
    const { Toast } = (window as any).bootstrap ?? {};
    if (Toast) new Toast(this.toastElRef.nativeElement).show();
  }
}
