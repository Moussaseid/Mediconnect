// src/app/features/admin/logs/logs.component.ts
import { Component, signal, OnInit } from '@angular/core';
import { CommonModule }              from '@angular/common';
import { RouterLink }                from '@angular/router';
import { FormsModule }               from '@angular/forms';

import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatFormFieldModule }         from '@angular/material/form-field';
import { MatInputModule }             from '@angular/material/input';
import { MatSelectModule }            from '@angular/material/select';
import { MatButtonModule }            from '@angular/material/button';
import { MatIconModule }              from '@angular/material/icon';
import { MatProgressSpinnerModule }   from '@angular/material/progress-spinner';
import { MatChipsModule }             from '@angular/material/chips';

import { AdminService, IAuthLog } from '../../../core/services/admin.service';

@Component({
  selector   : 'app-admin-logs',
  standalone : true,
  imports    : [
    CommonModule, RouterLink, FormsModule,
    MatTableModule, MatFormFieldModule, MatInputModule,
    MatSelectModule, MatButtonModule, MatIconModule,
    MatProgressSpinnerModule, MatChipsModule,
  ],
  templateUrl: './logs.component.html',
})
export class LogsComponent implements OnInit {

  colonnes   = ['timestamp', 'action', 'email', 'role', 'ip', 'statut'];
  datasource = new MatTableDataSource<IAuthLog>();

  chargement = signal(true);
  erreur     = signal<string | null>(null);
  total      = signal(0);

  filtreAction = '';
  limite       = 50;

  readonly actions = [
    { value: '',                      label: 'Toutes les actions' },
    { value: 'connexion_reussie',     label: 'Connexion réussie' },
    { value: 'connexion_echouee',     label: 'Connexion échouée' },
    { value: 'deconnexion',           label: 'Déconnexion' },
    { value: 'inscription',           label: 'Inscription' },
    { value: 'reset_demande',         label: 'Reset demandé' },
    { value: 'reset_succes',          label: 'Reset réussi' },
    { value: 'profil_mis_a_jour',     label: 'Profil modifié' },
    { value: 'admin_suspension_medecin',   label: 'Suspension médecin' },
    { value: 'admin_reactivation_medecin', label: 'Réactivation médecin' },
    { value: 'admin_suppression_medecin',  label: 'Suppression médecin' },
  ];

  constructor(private admin: AdminService) {}

  ngOnInit(): void { this.charger(); }

  onFiltre(): void { this.charger(); }

  charger(): void {
    this.chargement.set(true);
    this.erreur.set(null);

    this.admin.getLogs(this.limite, this.filtreAction || undefined).subscribe({
      next: res => {
        this.datasource.data = res.data.logs;
        this.total.set(res.data.total);
        this.chargement.set(false);
      },
      error: err => {
        this.erreur.set(err.error?.error ?? 'Impossible de charger les logs MongoDB');
        this.chargement.set(false);
      },
    });
  }

  statutClass(s: string): string {
    return s === 'succes' ? 'success' : 'danger';
  }

  actionClass(a: string): string {
    if (a.includes('echec') || a.includes('suppression')) return 'danger';
    if (a.includes('suspension'))  return 'warning';
    if (a.includes('connexion_reussie') || a.includes('inscription')) return 'success';
    return 'primary';
  }
}
