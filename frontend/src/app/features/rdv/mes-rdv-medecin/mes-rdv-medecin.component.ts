import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RdvService } from '../../../core/services/rdv.service';
import { IRdv } from '../../../core/models/interfaces';

@Component({
  selector: 'app-mes-rdv-medecin',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './mes-rdv-medecin.component.html',
})
export class MesRdvMedecinComponent {
  private rdvService = inject(RdvService);

  rdvs = signal<IRdv[]>([]);
  motifs: { [id: number]: string } = {};
  erreur = signal<string | null>(null);
  message = signal<string | null>(null);

  constructor() {
    this.chargerRdv();
  }

  chargerRdv(): void {
    this.rdvService.getMesRdvMedecin().subscribe({
      next: (res) => {
        this.rdvs.set(res.data.sort((a, b) => a.dateHeure.localeCompare(b.dateHeure)));
      },
      error: () => {
        this.erreur.set('Erreur chargement RDV');
        console.error('Erreur chargement RDV médecin');
      },
    });
  }

  annuler(rdv: IRdv): void {
    const motif = this.motifs[rdv.id];
    this.erreur.set(null);
    this.message.set(null);

    if (!motif || motif.trim().length < 3) {
      this.erreur.set('Le motif est obligatoire.');
      return;
    }

    this.rdvService.annulerRdv(rdv.id, motif).subscribe({
      next: () => {
        this.message.set('Rendez-vous annulé.');
        this.chargerRdv();
      },
      error: () => this.erreur.set('Impossible d’annuler le rendez-vous.'),
    });
  }
}
