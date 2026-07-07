import { Component, Input, Output, EventEmitter }  from '@angular/core';
import { CommonModule }      from '@angular/common';
import { RouterLink }        from '@angular/router';
import { IMedecin }          from '../../../core/models/interfaces';

@Component({
  selector   : 'app-medecin-card',
  standalone : true,
  imports    : [CommonModule, RouterLink],
  templateUrl: './medecin-card.component.html',
})
export class MedecinCardComponent {
  @Input({ required: true }) medecin!: IMedecin;

  // Émet le médecin choisi — la prise de rendez-vous elle-même (périmètre P3) n'est pas traitée ici.
  @Output() rdvDemande = new EventEmitter<IMedecin>();
}