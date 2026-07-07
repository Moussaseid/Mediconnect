import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { RdvService } from '../../../core/services/rdv.service';
import { ICreneau } from '../../../core/models/interfaces';

interface IJourDisponibilite {
  date: string;
  label: string;
  creneaux: ICreneau[];
}

@Component({
  selector: 'app-calendrier-disponibilites',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './calendrier-disponibilites.component.html',
})
export class CalendrierDisponibilitesComponent {
  private route = inject(ActivatedRoute);
  private rdvService = inject(RdvService);

  medecinId = Number(this.route.snapshot.paramMap.get('medecinId'));
  jours = signal<IJourDisponibilite[]>([]);
  erreur = signal<string | null>(null);

  constructor() {
    this.genererSemaine();
  }

  genererSemaine(): void {
    const aujourdHui = new Date();
    const joursInitiaux: IJourDisponibilite[] = [];

    for (let i = 0; i < 7; i++) {
      const date = new Date(aujourdHui);
      date.setDate(aujourdHui.getDate() + i);
      const dateIso = date.toISOString().substring(0, 10);

      joursInitiaux.push({
        date: dateIso,
        label: date.toLocaleDateString('fr-FR', {
          weekday: 'short',
          day: '2-digit',
          month: '2-digit',
        }),
        creneaux: [],
      });
    }

    this.jours.set(joursInitiaux);

    joursInitiaux.forEach((jour, index) => {
      this.rdvService.getCreneaux(this.medecinId, jour.date).subscribe({
        next: (res) => {
          const copie = [...this.jours()];
          copie[index] = { ...copie[index], creneaux: res.data };
          this.jours.set(copie);
        },
        error: () => this.erreur.set('Erreur lors du chargement du calendrier.'),
      });
    });
  }
}
