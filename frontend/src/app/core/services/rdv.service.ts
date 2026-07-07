// core/services/rdv.service.ts
import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpParams }     from '@angular/common/http';
import { Observable }                 from 'rxjs';
import { environment }                from '../../../environments/environment';
import {
  IApiResponse,
  ICreneau,
  IHoraireSemaine,
  IRdv,
  IRdvAnnulerRequest,
  IRdvCreateRequest,
  IMedecin,
} from '../models/interfaces';

export interface IHorairesMedecinRequest {
  jourSemaine: 1 | 2 | 3 | 4 | 5 | 6 | 7;
  heureDebut: string;
  heureFin: string;
  dureeRdv?: number;
}

@Injectable({ providedIn: 'root' })
export class RdvService {
  private http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  // ── Signaux d'état ────────────────────────────────────────────────────────
  readonly rdvs       = signal<IRdv[]>([]);
  readonly medecins   = signal<IMedecin[]>([]);
  readonly creneaux   = signal<ICreneau[]>([]);
  readonly chargement = signal<boolean>(false);
  readonly erreur     = signal<string | null>(null);

  // ── Patient : liste de ses RDV ────────────────────────────────────────────
  chargerRdv(): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http.get<IApiResponse<IRdv[]>>(`${this.api}/rdv`).subscribe({
      next    : res => this.rdvs.set(res.data),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement des RDV'),
      complete: () => this.chargement.set(false),
    });
  }

  getMesRdvPatient(): Observable<IApiResponse<IRdv[]>> {
    return this.http.get<IApiResponse<IRdv[]>>(`${this.api}/rdv`);
  }

  // ── Patient : liste des médecins disponibles ──────────────────────────────
  chargerMedecins(): void {
    this.http.get<IApiResponse<IMedecin[]>>(`${this.api}/rdv/medecins`).subscribe({
      next : res => this.medecins.set(res.data),
      error: err => this.erreur.set(err.error?.error ?? 'Erreur chargement des médecins'),
    });
  }

  // ── Patient : créneaux disponibles ────────────────────────────────────────
  chargerCreneaux(medecinId: number, date: string): void {
    this.creneaux.set([]);
    const params = new HttpParams().set('medecinId', medecinId).set('date', date);
    this.http.get<IApiResponse<ICreneau[]>>(`${this.api}/rdv/creneaux`, { params }).subscribe({
      next : res => this.creneaux.set(res.data),
      error: err => this.erreur.set(err.error?.error ?? 'Erreur chargement des créneaux'),
    });
  }

  getCreneaux(medecinId: number, date: string): Observable<IApiResponse<ICreneau[]>> {
    const params = new HttpParams().set('medecinId', medecinId).set('date', date);
    return this.http.get<IApiResponse<ICreneau[]>>(`${this.api}/rdv/creneaux`, { params });
  }

  // ── Patient : créer un RDV ────────────────────────────────────────────────
  creerRdv(req: IRdvCreateRequest): Observable<IApiResponse<IRdv>> {
    return this.http.post<IApiResponse<IRdv>>(`${this.api}/rdv`, req);
  }

  // ── Patient : annuler un RDV ──────────────────────────────────────────────
  annulerRdv(id: number, motif: string): Observable<IApiResponse<IRdv>> {
    const body: IRdvAnnulerRequest = { motif };
    return this.http.put<IApiResponse<IRdv>>(`${this.api}/rdv/${id}`, body);
  }

  // ── Médecin : ses rendez-vous ─────────────────────────────────────────────
  getMesRdvMedecin(): Observable<IApiResponse<IRdv[]>> {
    return this.http.get<IApiResponse<IRdv[]>>(`${this.api}/medecin/mes-rdv`);
  }

  // ── Alias courts pour les composants Belva / patient ─────────────────────
  mesRendezVous(): ReturnType<RdvService['getMesRdvMedecin']> {
    return this.getMesRdvMedecin();
  }
  mesRendezVousPatient(): ReturnType<RdvService['getMesRdvPatient']> {
    return this.getMesRdvPatient();
  }
  creer(req: IRdvCreateRequest): ReturnType<RdvService['creerRdv']> {
    return this.creerRdv(req);
  }
  annuler(id: number, motif?: string): ReturnType<RdvService['annulerRdv']> {
    return this.annulerRdv(id, motif ?? '');
  }
  majRdvLocal(updated: IRdv): void {
    this.rdvs.update(list => list.map(r => r.id === updated.id ? { ...r, ...updated } : r));
  }

  // ── Médecin : horaires de travail ─────────────────────────────────────────
  getHorairesMedecin(): Observable<IApiResponse<IHoraireSemaine[]>> {
    return this.http.get<IApiResponse<IHoraireSemaine[]>>(`${this.api}/medecin/horaires`);
  }

  ajouterHoraire(request: IHorairesMedecinRequest): Observable<IApiResponse<IHoraireSemaine>> {
    return this.http.post<IApiResponse<IHoraireSemaine>>(`${this.api}/medecin/horaires`, request);
  }

  supprimerHoraire(id: number): Observable<IApiResponse<null>> {
    return this.http.delete<IApiResponse<null>>(`${this.api}/medecin/horaires/${id}`);
  }
}
