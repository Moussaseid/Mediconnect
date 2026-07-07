import {
  Component, OnInit, inject, signal, ViewChild, ElementRef
} from '@angular/core';
import { CommonModule }         from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { CentreSanteService }   from '../../../core/services/centre-sante.service';

@Component({
  selector  : 'app-infos-centre',
  standalone: true,
  imports   : [CommonModule, ReactiveFormsModule],
  template  : `
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Informations du centre</h4>
    </div>

    <!-- Toast -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1100;">
      <div #toastEl class="toast align-items-center text-bg-primary border-0"
           role="alert" aria-live="assertive">
        <div class="d-flex">
          <div class="toast-body">{{ toastMsg() }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto"
                  data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Formulaire infos -->
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom fw-semibold">Modifier les informations</div>
          <div class="card-body">
            <form [formGroup]="form" (ngSubmit)="soumettre()">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                  <input formControlName="nom" class="form-control"
                         [class.is-invalid]="form.get('nom')!.invalid && form.get('nom')!.touched">
                  <div class="invalid-feedback">Le nom est requis.</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Adresse</label>
                  <input formControlName="adresse" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Téléphone</label>
                  <input formControlName="telephone" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Email</label>
                  <input formControlName="email" type="email" class="form-control"
                         [class.is-invalid]="form.get('email')!.invalid && form.get('email')!.touched">
                  <div class="invalid-feedback">Email invalide.</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Description</label>
                  <textarea formControlName="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">
                    Spécialités
                    <small class="text-muted fw-normal">(séparées par des virgules)</small>
                  </label>
                  <input formControlName="specialites" class="form-control"
                         placeholder="ex: Cardiologie, Pédiatrie, Urgences">
                  <!-- Aperçu tags -->
                  @if (form.get('specialites')!.value) {
                    <div class="mt-2">
                      @for (t of splitTags(form.get('specialites')!.value!); track t) {
                        <span class="badge bg-primary-subtle text-primary me-1">{{ t }}</span>
                      }
                    </div>
                  }
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">
                    Services
                    <small class="text-muted fw-normal">(séparés par des virgules)</small>
                  </label>
                  <input formControlName="services" class="form-control"
                         placeholder="ex: Imagerie, Chirurgie, Maternité">
                  @if (form.get('services')!.value) {
                    <div class="mt-2">
                      @for (t of splitTags(form.get('services')!.value!); track t) {
                        <span class="badge bg-info-subtle text-info me-1">{{ t }}</span>
                      }
                    </div>
                  }
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary" [disabled]="form.invalid || enCours()">
                    @if (enCours()) { <span class="spinner-border spinner-border-sm me-1"></span> }
                    Enregistrer
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Photo de couverture -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom fw-semibold">Photo de couverture</div>
          <div class="card-body text-center">
            @if (svc.centre()?.photoPath) {
              <img [src]="svc.centre()!.photoPath!" alt="Photo du centre"
                   class="img-fluid rounded mb-3" style="max-height:200px; object-fit:cover; width:100%;">
            } @else {
              <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3"
                   style="height:160px;">
                <i class="bi bi-image text-muted fs-1"></i>
              </div>
            }

            <input type="file" #fileInput class="d-none" accept=".jpg,.jpeg,.png,.webp"
                   (change)="onFichierChoisi($event)">
            <button class="btn btn-outline-primary w-100" (click)="fileInput.click()"
                    [disabled]="photoEnCours()">
              @if (photoEnCours()) {
                <span class="spinner-border spinner-border-sm me-1"></span>
              } @else {
                <i class="bi bi-upload me-1"></i>
              }
              {{ photoEnCours() ? 'Envoi en cours…' : 'Changer la photo' }}
            </button>
            <div class="text-muted small mt-1">JPG, PNG ou WebP · max 5 Mo</div>
          </div>
        </div>
      </div>
    </div>
  `,
})
export class InfosCentreComponent implements OnInit {
  svc = inject(CentreSanteService);
  private fb = inject(FormBuilder);

  @ViewChild('toastEl') toastElRef!: ElementRef;

  enCours     = signal(false);
  photoEnCours = signal(false);
  toastMsg    = signal('');

  form = this.fb.group({
    nom         : ['', Validators.required],
    adresse     : [''],
    telephone   : [''],
    email       : ['', Validators.email],
    description : [''],
    specialites : [''],
    services    : [''],
  });

  ngOnInit(): void {
    this.svc.chargerInfos().subscribe(r => {
      const c = r.data;
      this.form.setValue({
        nom         : c.nom         ?? '',
        adresse     : c.adresse     ?? '',
        telephone   : c.telephone   ?? '',
        email       : c.email       ?? '',
        description : c.description ?? '',
        specialites : c.specialites ?? '',
        services    : c.services    ?? '',
      });
    });
  }

  soumettre(): void {
    if (this.form.invalid) return;
    this.enCours.set(true);
    const v = this.form.value;
    this.svc.modifierInfos({
      nom         : v.nom!,
      adresse     : v.adresse     || undefined,
      telephone   : v.telephone   || undefined,
      email       : v.email       || undefined,
      description : v.description || undefined,
      specialites : v.specialites || undefined,
      services    : v.services    || undefined,
    }).subscribe({
      next: () => this.afficherToast('Informations mises à jour'),
      complete: () => this.enCours.set(false),
    });
  }

  onFichierChoisi(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;
    this.photoEnCours.set(true);
    this.svc.uploadPhoto(file).subscribe({
      next: () => this.afficherToast('Photo mise à jour'),
      complete: () => {
        this.photoEnCours.set(false);
        input.value = '';
      },
    });
  }

  splitTags(s: string): string[] {
    return s.split(',').map(t => t.trim()).filter(Boolean);
  }

  private afficherToast(msg: string): void {
    this.toastMsg.set(msg);
    const { Toast } = (window as any).bootstrap ?? {};
    if (Toast) new Toast(this.toastElRef.nativeElement).show();
  }
}
