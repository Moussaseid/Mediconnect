import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { RdvService } from '../../../core/services/rdv.service';
import { ICreneau } from '../../../core/models/interfaces';

@Component({
  selector: 'app-prise-rdv',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './prise-rdv.component.html',
})
export class PriseRdvComponent {
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private rdvService = inject(RdvService);

  medecinId = Number(this.route.snapshot.paramMap.get('medecinId'));
  creneaux = signal<ICreneau[]>([]);
  message = signal<string | null>(null);
  erreur = signal<string | null>(null);
  loading = signal(false);

  form = this.fb.group({
    date: [this.route.snapshot.queryParamMap.get('date') ?? '', Validators.required],
    heureDebut: [this.route.snapshot.queryParamMap.get('heure') ?? '', Validators.required],
  });

  constructor() {
    if (this.form.value.date) {
      this.chargerCreneaux();
    }
  }

  chargerCreneaux(): void {
    const date = this.form.value.date;
    this.message.set(null);
    this.erreur.set(null);

    if (!date) return;

    if (new Date(date) < new Date(new Date().toISOString().substring(0, 10))) {
      this.erreur.set('La date du rendez-vous doit être dans le futur.');
      this.creneaux.set([]);
      return;
    }

    this.rdvService.getCreneaux(this.medecinId, date).subscribe({
      next: (res) => this.creneaux.set(res.data),
      error: () => this.erreur.set('Erreur lors du chargement des créneaux.'),
    });
  }

  prendreRdv(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const date = this.form.value.date!;
    const heureDebut = this.form.value.heureDebut!;
    const dateHeure = `${date} ${heureDebut}:00`;

    this.loading.set(true);
    this.message.set(null);
    this.erreur.set(null);

    this.rdvService.creerRdv({ medecinId: this.medecinId, dateHeure }).subscribe({
      next: () => {
        this.loading.set(false);
        this.message.set('Rendez-vous confirmé.');
        this.chargerCreneaux();
      },
      error: () => {
        this.loading.set(false);
        this.erreur.set('Impossible de créer le rendez-vous.');
      },
    });
  }
}
