import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RdvService } from '../../../core/services/rdv.service';
import { IRdv } from '../../../core/models/interfaces';

@Component({
  selector: 'app-mes-rdv-patient',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './mes-rdv-patient.component.html',
})
export class MesRdvPatientComponent {
  private rdvService = inject(RdvService);

  rdvs = signal<IRdv[]>([]);
  erreur = signal<string | null>(null);

  constructor() {
    this.rdvService.getMesRdvPatient().subscribe({
      next: (res) => {
        this.rdvs.set(res.data.sort((a, b) => a.dateHeure.localeCompare(b.dateHeure)));
      },
      error: () => {
        this.erreur.set('Erreur chargement RDV');
        console.error('Erreur chargement RDV patient');
      },
    });
  }
}
