import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { IHoraireSemaine } from '../../../core/models/interfaces';
import { RdvService } from '../../../core/services/rdv.service';

@Component({
  selector: 'app-creneaux-medecin',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './creneaux-medecin.component.html',
})
export class CreneauxMedecinComponent {
  private fb = inject(FormBuilder);
  private rdvService = inject(RdvService);

  horaires = signal<IHoraireSemaine[]>([]);
  message = signal<string | null>(null);
  erreur = signal<string | null>(null);

  jours = [
    { value: 1, label: 'Lundi' },
    { value: 2, label: 'Mardi' },
    { value: 3, label: 'Mercredi' },
    { value: 4, label: 'Jeudi' },
    { value: 5, label: 'Vendredi' },
    { value: 6, label: 'Samedi' },
    { value: 7, label: 'Dimanche' },
  ] as const;

  form = this.fb.nonNullable.group({
    jourSemaine: [1 as 1 | 2 | 3 | 4 | 5 | 6 | 7, Validators.required],
    heureDebut: ['09:00', Validators.required],
    heureFin: ['17:00', Validators.required],
    dureeRdv: [30, [Validators.required, Validators.min(10)]],
  });

  constructor() {
    this.chargerHoraires();
  }

  chargerHoraires(): void {
    this.rdvService.getHorairesMedecin().subscribe({
      next: (res) => this.horaires.set(res.data),
      error: () => this.erreur.set('Impossible de charger les horaires.'),
    });
  }

  ajouterHoraire(): void {
    this.message.set(null);
    this.erreur.set(null);

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.erreur.set('Veuillez remplir tous les champs.');
      return;
    }

    const valeur = this.form.getRawValue();

    if (valeur.heureDebut >= valeur.heureFin) {
      this.erreur.set('L’heure de début doit être inférieure à l’heure de fin.');
      return;
    }

    this.rdvService.ajouterHoraire({
      jourSemaine: valeur.jourSemaine,
      heureDebut: valeur.heureDebut,
      heureFin: valeur.heureFin,
      dureeRdv: valeur.dureeRdv,
    }).subscribe({
      next: () => {
        this.message.set('Horaire ajouté.');
        this.form.reset({
          jourSemaine: 1,
          heureDebut: '09:00',
          heureFin: '17:00',
          dureeRdv: 30,
        });
        this.chargerHoraires();
      },
      error: () => this.erreur.set('Impossible d’ajouter cet horaire.'),
    });
  }

  supprimerHoraire(id: number): void {
    this.message.set(null);
    this.erreur.set(null);

    this.rdvService.supprimerHoraire(id).subscribe({
      next: () => {
        this.message.set('Horaire supprimé.');
        this.chargerHoraires();
      },
      error: () => this.erreur.set('Impossible de supprimer cet horaire.'),
    });
  }

  libelleJour(jour: number): string {
    return this.jours.find(j => j.value === jour)?.label ?? `Jour ${jour}`;
  }
}