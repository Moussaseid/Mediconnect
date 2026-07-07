// src/app/core/services/admin.service.ts
import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpParams }      from '@angular/common/http';
import { Observable }                  from 'rxjs';
import { environment }                 from '../../../environments/environment';
import {
  IApiResponse, IUser,
  IAdminStats, IDemandeProfessionnel,
} from '../models/interfaces';

export interface IPatientListParams {
  page?    : number;
  perPage? : number;
  search?  : string;
  sort?    : string;
  order?   : 'ASC' | 'DESC';
  statut?  : string;
}

export interface IPatientListResponse {
  patients  : IUser[];
  total     : number;
  page      : number;
  parPage   : number;
  totalPages: number;
}

export interface IMedecinAdmin {
  id             : number;
  medecinId      : number;
  nom            : string;
  prenom         : string;
  email          : string;
  statut         : string;
  specialisation : number;
  numeroRpps     : string;
  adresseCabinet : string;
  telephone?     : string;
  adresse?       : string;
  ville?         : string;
  dureeRdv?      : number;
}

export interface IMedecinsListResponse {
  medecins  : IMedecinAdmin[];
  total     : number;
  page      : number;
  parPage   : number;
  totalPages: number;
}

export interface IAuthStatJour {
  date      : string;
  connexions: number;
  echecs    : number;
}

export interface IAuthStats {
  parJour      : IAuthStatJour[];
  topIpsEchecs : { ip: string; tentatives: number }[];
  totalSucces  : number;
  totalEchecs  : number;
  source       : string;
}

export interface IAuthLog {
  userId   : number | null;
  email    : string | null;
  action   : string;
  role     : string | null;
  ip       : string | null;
  statut   : string;
  timestamp: string;
}

@Injectable({ providedIn: 'root' })
export class AdminService {
  private http   = inject(HttpClient);
  private apiUrl = environment.apiUrl;

  // ── Signals partagés (utilisés par les composants Moussa) ─────────────────
  readonly chargement       = signal<boolean>(false);
  readonly erreur           = signal<string | null>(null);

  readonly stats            = signal<IAdminStats | null>(null);

  readonly demandes         = signal<IDemandeProfessionnel[]>([]);
  readonly demandesTotal    = signal<number>(0);
  readonly demandesPages    = signal<number>(1);

  readonly utilisateurs     = signal<IUser[]>([]);
  readonly utilisateursTotal= signal<number>(0);
  readonly utilisateursPages= signal<number>(1);

  // ── Stats dashboard ───────────────────────────────────────────────────────
  chargerStats(): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http.get<IApiResponse<IAdminStats>>(`${this.apiUrl}/admin/stats`).subscribe({
      next : res => { this.stats.set(res.data); this.chargement.set(false); },
      error: err => { this.erreur.set(err.error?.error ?? 'Erreur chargement stats'); this.chargement.set(false); },
    });
  }

  // ── Demandes professionnelles ─────────────────────────────────────────────
  chargerDemandes(page = 1, statut?: string): void {
    this.chargement.set(true);
    this.erreur.set(null);
    let p = new HttpParams().set('page', page).set('perPage', 15);
    if (statut && statut !== 'tous') p = p.set('statut', statut);
    this.http.get<IApiResponse<{ demandes: IDemandeProfessionnel[]; total: number; totalPages: number }>>(`${this.apiUrl}/admin/demandes`, { params: p }).subscribe({
      next : res => {
        this.demandes.set(res.data.demandes ?? []);
        this.demandesTotal.set(res.data.total ?? 0);
        this.demandesPages.set(res.data.totalPages ?? 1);
        this.chargement.set(false);
      },
      error: err => { this.erreur.set(err.error?.error ?? 'Erreur chargement demandes'); this.chargement.set(false); },
    });
  }

  traiterDemande(id: number, action: 'valider' | 'rejeter'): Observable<IApiResponse<null>> {
    return this.http.post<IApiResponse<null>>(`${this.apiUrl}/admin/demandes/${id}/${action}`, {});
  }

  // ── Utilisateurs ──────────────────────────────────────────────────────────
  chargerUtilisateurs(page = 1, role?: string): void {
    this.chargement.set(true);
    this.erreur.set(null);
    let p = new HttpParams().set('page', page).set('perPage', 15);
    if (role) p = p.set('role', role);
    this.http.get<IApiResponse<{ utilisateurs: IUser[]; total: number; totalPages: number }>>(`${this.apiUrl}/admin/utilisateurs`, { params: p }).subscribe({
      next : res => {
        this.utilisateurs.set(res.data.utilisateurs ?? []);
        this.utilisateursTotal.set(res.data.total ?? 0);
        this.utilisateursPages.set(res.data.totalPages ?? 1);
        this.chargement.set(false);
      },
      error: err => { this.erreur.set(err.error?.error ?? 'Erreur chargement utilisateurs'); this.chargement.set(false); },
    });
  }

  modifierUtilisateur(id: number, data: Partial<IUser>): Observable<IApiResponse<null>> {
    return this.http.patch<IApiResponse<null>>(`${this.apiUrl}/admin/utilisateurs/${id}`, data);
  }

  // ── Patients ──────────────────────────────────────────────────────────────
  getPatients(params: IPatientListParams = {}): Observable<IApiResponse<IPatientListResponse>> {
    let p = new HttpParams();
    if (params.page)    p = p.set('page',    params.page);
    if (params.perPage) p = p.set('perPage', params.perPage);
    if (params.search)  p = p.set('search',  params.search);
    if (params.sort)    p = p.set('sort',    params.sort);
    if (params.order)   p = p.set('order',   params.order);
    if (params.statut)  p = p.set('statut',  params.statut);

    return this.http.get<IApiResponse<IPatientListResponse>>(`${this.apiUrl}/admin/patients`, { params: p });
  }

  // ── Médecins ──────────────────────────────────────────────────────────────
  getMedecins(page = 1, perPage = 15): Observable<IApiResponse<IMedecinsListResponse>> {
    const p = new HttpParams().set('page', page).set('perPage', perPage);
    return this.http.get<IApiResponse<IMedecinsListResponse>>(`${this.apiUrl}/admin/medecins`, { params: p });
  }

  getMedecin(id: number): Observable<IApiResponse<IMedecinAdmin>> {
    return this.http.get<IApiResponse<IMedecinAdmin>>(`${this.apiUrl}/admin/medecins/${id}`);
  }

  modifierMedecin(id: number, data: Partial<IMedecinAdmin>): Observable<IApiResponse<null>> {
    return this.http.put<IApiResponse<null>>(`${this.apiUrl}/admin/medecins/${id}`, data);
  }

  changerStatutMedecin(id: number, statut: 'actif' | 'suspendu'): Observable<IApiResponse<null>> {
    return this.http.patch<IApiResponse<null>>(`${this.apiUrl}/admin/medecins/${id}/statut`, { statut });
  }

  supprimerMedecin(id: number): Observable<IApiResponse<null>> {
    return this.http.delete<IApiResponse<null>>(`${this.apiUrl}/admin/medecins/${id}`);
  }

  // ── Logs MongoDB ─────────────────────────────────────────────────────────
  getLogs(limit = 50, action?: string): Observable<IApiResponse<{ logs: IAuthLog[]; total: number }>> {
    let p = new HttpParams().set('limit', limit);
    if (action) p = p.set('action', action);
    return this.http.get<IApiResponse<{ logs: IAuthLog[]; total: number }>>(`${this.apiUrl}/admin/logs`, { params: p });
  }

  // ── Statistiques auth (graphique + alertes) ───────────────────────────────
  getAuthStats(): Observable<IApiResponse<IAuthStats>> {
    return this.http.get<IApiResponse<IAuthStats>>(`${this.apiUrl}/admin/auth-stats`);
  }
}
