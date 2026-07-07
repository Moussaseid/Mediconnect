// src/app/features/admin/medecins/medecins.component.ts
import { Component, signal, OnInit } from '@angular/core';
import { CommonModule }              from '@angular/common';
import { RouterLink }                from '@angular/router';

import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginatorModule, PageEvent }      from '@angular/material/paginator';
import { MatButtonModule }                    from '@angular/material/button';
import { MatIconModule }                      from '@angular/material/icon';
import { MatProgressSpinnerModule }           from '@angular/material/progress-spinner';
import { MatTooltipModule }                   from '@angular/material/tooltip';
import { MatDialogModule, MatDialog }         from '@angular/material/dialog';

import { AdminService, IMedecinAdmin }        from '../../../core/services/admin.service';
import { MedecinFormComponent }               from './medecin-form.component';

@Component({
  selector   : 'app-admin-medecins',
  standalone : true,
  imports    : [
    CommonModule, RouterLink,
    MatTableModule, MatPaginatorModule, MatButtonModule,
    MatIconModule, MatProgressSpinnerModule, MatTooltipModule,
    MatDialogModule,
  ],
  templateUrl: './medecins.component.html',
})
export class MedecinsComponent implements OnInit {

  colonnes   = ['nom', 'specialisation', 'numeroRpps', 'statut', 'actions'];
  datasource = new MatTableDataSource<IMedecinAdmin>();

  total      = signal(0);
  page       = signal(1);
  parPage    = signal(15);
  totalPages = signal(0);
  chargement = signal(true);
  erreur     = signal<string | null>(null);

  constructor(private admin: AdminService, private dialog: MatDialog) {}

  ngOnInit(): void { this.charger(); }

  onPage(e: PageEvent): void {
    this.page.set(e.pageIndex + 1);
    this.parPage.set(e.pageSize);
    this.charger();
  }

  ouvrirFormulaire(medecin?: IMedecinAdmin): void {
    const ref = this.dialog.open(MedecinFormComponent, {
      width: '600px',
      data : medecin ?? null,
    });
    ref.afterClosed().subscribe(modifie => { if (modifie) this.charger(); });
  }

  changerStatut(m: IMedecinAdmin): void {
    const nouveau = m.statut === 'actif' ? 'suspendu' : 'actif';
    const label   = nouveau === 'suspendu' ? 'Suspendre' : 'Réactiver';
    if (!confirm(`${label} le Dr ${m.nom} ${m.prenom} ?`)) return;

    this.admin.changerStatutMedecin(m.id, nouveau).subscribe({
      next : () => this.charger(),
      error: err => alert(err.error?.error ?? 'Erreur'),
    });
  }

  supprimer(m: IMedecinAdmin): void {
    if (!confirm(`Supprimer définitivement le Dr ${m.nom} ${m.prenom} ?\nCette action est irréversible.`)) return;

    this.admin.supprimerMedecin(m.id).subscribe({
      next : () => this.charger(),
      error: err => alert(err.error?.error ?? 'Erreur'),
    });
  }

  private charger(): void {
    this.chargement.set(true);
    this.erreur.set(null);

    this.admin.getMedecins(this.page(), this.parPage()).subscribe({
      next: res => {
        this.datasource.data = res.data.medecins;
        this.total.set(res.data.total);
        this.totalPages.set(res.data.totalPages);
        this.chargement.set(false);
      },
      error: err => {
        this.erreur.set(err.error?.error ?? 'Erreur de chargement');
        this.chargement.set(false);
      },
    });
  }

  statutClass(s: string): string {
    return s === 'actif' ? 'success' : 'secondary';
  }
}
