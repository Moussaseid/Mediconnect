import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { RouterLink }             from '@angular/router';
import { ReactiveFormsModule, FormBuilder, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { catchError, of }  from 'rxjs';

import { AuthService }       from '../../../core/services/auth.service';
import { IUserUpdateRequest } from '../../../core/models/interfaces';

function confirmerMotDePasseValidator(group: AbstractControl): ValidationErrors | null {
  const mdp     = group.get('motDePasse')?.value;
  const confirm = group.get('confirmation')?.value;
  if (mdp && mdp !== confirm) return { confirmation: true };
  return null;
}

@Component({
  selector   : 'app-profil-patient',
  standalone : true,
  imports    : [CommonModule, RouterLink, ReactiveFormsModule],
  templateUrl: './profil-patient.component.html',
})
export class ProfilPatientComponent implements OnInit {

  readonly auth         = inject(AuthService);
  private readonly fb       = inject(FormBuilder);
  private readonly location = inject(Location);

  succes    = signal(false);
  erreur    = signal<string | null>(null);
  en_cours  = signal(false);

  form = this.fb.group({
    nom       : ['', [Validators.required, Validators.maxLength(100)]],
    prenom    : ['', [Validators.required, Validators.maxLength(100)]],
    telephone : ['', Validators.maxLength(20)],
    adresse   : ['', Validators.maxLength(255)],
    ville     : ['', Validators.maxLength(100)],
    motDePasse  : ['', Validators.minLength(8)],
    confirmation: [''],
  }, { validators: confirmerMotDePasseValidator });

  ngOnInit(): void {
    const u = this.auth.user();
    if (!u) return;
    this.form.patchValue({
      nom      : u.nom,
      prenom   : u.prenom,
      telephone: u.telephone ?? '',
      adresse  : u.adresse   ?? '',
      ville    : u.ville     ?? '',
    });
  }

  soumettre(): void {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }

    const v = this.form.value;
    const payload: IUserUpdateRequest = {
      nom   : v.nom!.trim(),
      prenom: v.prenom!.trim(),
      telephone: v.telephone?.trim() || undefined,
      adresse  : v.adresse?.trim()   || undefined,
      ville    : v.ville?.trim()     || undefined,
    };
    if (v.motDePasse?.trim()) payload.motDePasse = v.motDePasse.trim();

    this.en_cours.set(true);
    this.succes.set(false);
    this.erreur.set(null);

    this.auth.mettreAJourProfil(payload)
      .pipe(catchError(err => {
        this.erreur.set(err?.error?.error ?? 'Une erreur est survenue.');
        this.en_cours.set(false);
        return of(null);
      }))
      .subscribe(res => {
        if (!res) return;
        this.en_cours.set(false);
        this.succes.set(true);
        // Vider les champs mot de passe
        this.form.patchValue({ motDePasse: '', confirmation: '' });
        this.form.get('motDePasse')?.setErrors(null);
        this.form.get('confirmation')?.setErrors(null);
      });
  }

  retour(): void { this.location.back(); }

  champ(name: string) {
    return this.form.get(name);
  }

  invalide(name: string): boolean {
    const c = this.champ(name);
    return !!(c?.invalid && c.touched);
  }
}