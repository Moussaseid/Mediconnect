// src/app/features/profil/profil.component.ts
import { Component, signal, inject, OnInit } from '@angular/core';
import { CommonModule }          from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { HttpErrorResponse }     from '@angular/common/http';
import { AuthService }           from '../../core/services/auth.service';

/**
 * ProfilComponent — Affichage et modification du profil utilisateur.
 * Route : /profil — protégée par authGuard.
 */
@Component({
  selector   : 'app-profil',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule],
  templateUrl: './profil.component.html',
})
export class ProfilComponent implements OnInit {

  auth    = inject(AuthService);
  private fb = inject(FormBuilder);

  form!: FormGroup;
  loading   = signal(false);
  sauvegarde = signal(false);
  erreur    = signal<string | null>(null);

  ngOnInit(): void {
    const u = this.auth.user();
    this.form = this.fb.group({
      nom      : [u?.nom       ?? '', [Validators.required, Validators.minLength(2)]],
      prenom   : [u?.prenom    ?? '', [Validators.required, Validators.minLength(2)]],
      telephone: [u?.telephone ?? ''],
      adresse  : [u?.adresse   ?? ''],
      ville    : [u?.ville     ?? ''],
    });

    // Charger les données fraîches depuis le serveur
    this.auth.me().subscribe({
      next: res => {
        const u2 = res.data;
        this.form.patchValue({
          nom      : u2.nom       ?? '',
          prenom   : u2.prenom    ?? '',
          telephone: u2.telephone ?? '',
          adresse  : u2.adresse   ?? '',
          ville    : u2.ville     ?? '',
        });
      },
    });
  }

  get nom()    { return this.form.get('nom')!; }
  get prenom() { return this.form.get('prenom')!; }

  soumettre(): void {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }

    this.loading.set(true);
    this.erreur.set(null);
    this.sauvegarde.set(false);

    this.auth.updateProfil(this.form.value).subscribe({
      next: () => {
        this.loading.set(false);
        this.sauvegarde.set(true);
        setTimeout(() => this.sauvegarde.set(false), 3000);
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.erreur.set(err.error?.error ?? 'Erreur lors de la mise à jour');
      },
    });
  }

  retourDashboard(): void {
    this.auth.redirigerSelonRole();
  }
}
