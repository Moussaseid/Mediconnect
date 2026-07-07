// src/app/features/admin/patients/patients.component.ts
import { Component, signal, ViewChild, AfterViewInit } from '@angular/core';
import { CommonModule }          from '@angular/common';
import { RouterLink }            from '@angular/router';
import { FormsModule }           from '@angular/forms';
import { HttpErrorResponse }     from '@angular/common/http';
import { debounceTime, distinctUntilChanged, Subject } from 'rxjs';

// Angular Material
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginatorModule, MatPaginator, PageEvent } from '@angular/material/paginator';
import { MatSortModule, MatSort, Sort }       from '@angular/material/sort';
import { MatInputModule }                     from '@angular/material/input';
import { MatFormFieldModule }                 from '@angular/material/form-field';
import { MatChipsModule }                     from '@angular/material/chips';
import { MatIconModule }                      from '@angular/material/icon';
import { MatButtonModule }                    from '@angular/material/button';
import { MatSelectModule }                    from '@angular/material/select';
import { MatProgressSpinnerModule }           from '@angular/material/progress-spinner';
import { MatTooltipModule }                   from '@angular/material/tooltip';

import { AdminService, IPatientListParams } from '../../../core/services/admin.service';
import { IUser }                            from '../../../core/models/interfaces';

@Component({
  selector   : 'app-admin-patients',
  standalone : true,
  imports    : [
    CommonModule, RouterLink, FormsModule,
    MatTableModule, MatPaginatorModule, MatSortModule,
    MatInputModule, MatFormFieldModule, MatChipsModule,
    MatIconModule, MatButtonModule, MatSelectModule,
    MatProgressSpinnerModule, MatTooltipModule,
  ],
  templateUrl: './patients.component.html',
})
export class PatientsComponent implements AfterViewInit {

  @ViewChild(MatSort) sort!: MatSort;

  colonnes = ['nom', 'email', 'telephone', 'ville', 'statut', 'createdAt'];

  datasource   = new MatTableDataSource<IUser>();
  total        = signal(0);
  page         = signal(1);
  parPage      = signal(15);
  totalPages   = signal(0);
  chargement   = signal(true);
  erreur       = signal<string | null>(null);

  recherche    = '';
  filtreStatut = '';
  triColonne   = 'created_at';
  triOrdre: 'ASC' | 'DESC' = 'DESC';

  private recherche$ = new Subject<string>();

  constructor(private admin: AdminService) {
    this.recherche$.pipe(debounceTime(350), distinctUntilChanged())
      .subscribe(v => { this.recherche = v; this.page.set(1); this.charger(); });
  }

  ngAfterViewInit(): void {
    this.charger();
    this.sort.sortChange.subscribe((s: Sort) => {
      if (s.active) {
        this.triColonne = s.active === 'createdAt' ? 'created_at' : s.active;
        this.triOrdre   = s.direction === 'asc' ? 'ASC' : 'DESC';
      }
      this.page.set(1);
      this.charger();
    });
  }

  onRecherche(v: string): void { this.recherche$.next(v); }

  onStatut(v: string): void { this.filtreStatut = v; this.page.set(1); this.charger(); }

  onPage(e: PageEvent): void {
    this.page.set(e.pageIndex + 1);
    this.parPage.set(e.pageSize);
    this.charger();
  }

  private charger(): void {
    this.chargement.set(true);
    this.erreur.set(null);

    const params: IPatientListParams = {
      page   : this.page(),
      perPage: this.parPage(),
      search : this.recherche  || undefined,
      sort   : this.triColonne,
      order  : this.triOrdre,
      statut : this.filtreStatut || undefined,
    };

    this.admin.getPatients(params).subscribe({
      next: res => {
        this.datasource.data = res.data.patients;
        this.total.set(res.data.total);
        this.totalPages.set(res.data.totalPages);
        this.chargement.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.erreur.set(err.error?.error ?? 'Erreur de chargement');
        this.chargement.set(false);
      },
    });
  }

  statutClass(s: string): string {
    return ({ actif: 'success', en_attente: 'warning', rejete: 'danger', suspendu: 'secondary' })[s] ?? 'light';
  }
}
