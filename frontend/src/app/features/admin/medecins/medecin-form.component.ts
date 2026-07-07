// src/app/features/admin/medecins/medecin-form.component.ts
import { Component, Inject, signal, OnInit } from '@angular/core';
import { CommonModule }                from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';

import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatFormFieldModule }          from '@angular/material/form-field';
import { MatInputModule }              from '@angular/material/input';
import { MatButtonModule }             from '@angular/material/button';
import { MatSelectModule }             from '@angular/material/select';
import { MatProgressSpinnerModule }    from '@angular/material/progress-spinner';

import { AdminService, IMedecinAdmin } from '../../../core/services/admin.service';
import { HttpErrorResponse }           from '@angular/common/http';

/**
 * MedecinFormComponent — Dialog Material pour créer ou modifier un médecin.
 * Reçoit via MAT_DIALOG_DATA : IMedecinAdmin (modification) | null (création).
 * Retourne true après fermeture si une modification a été appliquée.
 */
@Component({
  selector   : 'app-medecin-form',
  standalone : true,
  imports    : [
    CommonModule, ReactiveFormsModule,
    MatDialogModule, MatFormFieldModule, MatInputModule,
    MatButtonModule, MatSelectModule, MatProgressSpinnerModule,
  ],
  template: `
    <h2 mat-dialog-title>
      {{ estCreation ? 'Ajouter un médecin' : 'Modifier Dr ' + data?.nom + ' ' + data?.prenom }}
    </h2>

    <mat-dialog-content>
      @if (erreur()) {
        <div class="alert alert-danger py-2 mb-3">{{ erreur() }}</div>
      }

      <form [formGroup]="form" novalidate class="row g-3">
        <div class="col-6">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Nom</mat-label>
            <input matInput formControlName="nom" />
            @if (form.get('nom')?.invalid && form.get('nom')?.touched) {
              <mat-error>2 caractères minimum</mat-error>
            }
          </mat-form-field>
        </div>
        <div class="col-6">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Prénom</mat-label>
            <input matInput formControlName="prenom" />
            @if (form.get('prenom')?.invalid && form.get('prenom')?.touched) {
              <mat-error>2 caractères minimum</mat-error>
            }
          </mat-form-field>
        </div>
        <div class="col-12">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Email</mat-label>
            <input matInput type="email" formControlName="email" />
          </mat-form-field>
        </div>
        <div class="col-6">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Téléphone</mat-label>
            <input matInput formControlName="telephone" />
          </mat-form-field>
        </div>
        <div class="col-6">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Ville</mat-label>
            <input matInput formControlName="ville" />
          </mat-form-field>
        </div>
        <div class="col-12">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Adresse cabinet</mat-label>
            <input matInput formControlName="adresseCabinet" />
          </mat-form-field>
        </div>
        <div class="col-6">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>ID Spécialité</mat-label>
            <input matInput type="number" formControlName="specialisation" min="1" />
          </mat-form-field>
        </div>
        <div class="col-6">
          <mat-form-field appearance="outline" class="w-100">
            <mat-label>Durée RDV (min)</mat-label>
            <mat-select formControlName="dureeRdv">
              @for (d of [15, 20, 30, 45, 60]; track d) {
                <mat-option [value]="d">{{ d }} min</mat-option>
              }
            </mat-select>
          </mat-form-field>
        </div>
      </form>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button (click)="fermer()">Annuler</button>
      <button mat-flat-button color="primary" (click)="soumettre()" [disabled]="loading()">
        @if (loading()) {
          <mat-spinner diameter="20" style="display:inline-block"></mat-spinner>
        } @else {
          {{ estCreation ? 'Créer' : 'Enregistrer' }}
        }
      </button>
    </mat-dialog-actions>
  `,
})
export class MedecinFormComponent implements OnInit {
  form    !: FormGroup;
  loading  = signal(false);
  erreur   = signal<string | null>(null);

  get estCreation() { return !this.data; }

  constructor(
    private fb     : FormBuilder,
    private admin  : AdminService,
    private ref    : MatDialogRef<MedecinFormComponent>,
    @Inject(MAT_DIALOG_DATA) public data: IMedecinAdmin | null,
  ) {}

  ngOnInit(): void {
    this.form = this.fb.group({
      nom           : [this.data?.nom            ?? '', [Validators.required, Validators.minLength(2)]],
      prenom        : [this.data?.prenom         ?? '', [Validators.required, Validators.minLength(2)]],
      email         : [this.data?.email          ?? '', [Validators.required, Validators.email]],
      telephone     : [this.data?.telephone      ?? ''],
      ville         : [this.data?.ville          ?? ''],
      adresseCabinet: [this.data?.adresseCabinet ?? ''],
      specialisation: [this.data?.specialisation ?? 1,  [Validators.required, Validators.min(1)]],
      dureeRdv      : [this.data?.dureeRdv       ?? 30],
    });
  }

  soumettre(): void {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.loading.set(true);
    this.erreur.set(null);

    const payload = this.form.value;

    // Modification uniquement — la création de médecin passe par une demande pro
    if (!this.data) {
      this.loading.set(false);
      this.erreur.set('La création directe n\'est pas supportée — utilisez le flux de demande professionnelle.');
      return;
    }

    this.admin.modifierMedecin(this.data.id, payload).subscribe({
      next : () => { this.loading.set(false); this.ref.close(true); },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.erreur.set(err.error?.error ?? 'Erreur lors de la sauvegarde');
      },
    });
  }

  fermer(): void { this.ref.close(false); }
}
