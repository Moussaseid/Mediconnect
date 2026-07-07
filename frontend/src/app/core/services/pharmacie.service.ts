// core/services/pharmacie.service.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable }          from 'rxjs';
import { environment }         from '../../../environments/environment';
import {
  IInventaire,
  IPharmacie,
  IApiResponse,
} from '../models/interfaces';

export interface IRecherchePharmacieParamsEtendu {
  lat?          : number;
  lng?          : number;
  rayon?        : number;
  medicamentNom?: string;
  ville?        : string;
}

@Injectable({ providedIn: 'root' })
export class PharmacieService {

  private readonly api = environment.apiUrl;

  // ── Signaux d'état ────────────────────────────────────────────────────────
  readonly pharmacies             = signal<IPharmacie[]>([]);
  readonly pharmacieSelectionnee  = signal<IPharmacie | null>(null);
  readonly inventaire             = signal<IInventaire[]>([]);
  readonly chargement             = signal<boolean>(false);
  readonly erreur                 = signal<string | null>(null);

  constructor(private http: HttpClient) {}

  // ── Pharmacies ────────────────────────────────────────────────────────────
  chargerPharmacies(): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http.get<IApiResponse<IPharmacie[]>>(`${this.api}/pharmacies`).subscribe({
      next: res => {
        this.pharmacies.set(res.data);
        // Auto-sélection si une seule pharmacie disponible
        if (res.data.length === 1) {
          this.selectionnerPharmacie(res.data[0]);
        }
      },
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement des pharmacies'),
      complete: () => { if (!this.pharmacieSelectionnee()) this.chargement.set(false); },
    });
  }

  selectionnerPharmacie(p: IPharmacie): void {
    this.pharmacieSelectionnee.set(p);
    this.chargerInventaire(p.id);
  }

  // ── Inventaire ────────────────────────────────────────────────────────────
  chargerInventaire(pharmacieId: number): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http.get<IApiResponse<IInventaire[]>>(`${this.api}/inventaire/${pharmacieId}`).subscribe({
      next    : res => this.inventaire.set(res.data),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement de l\'inventaire'),
      complete: () => this.chargement.set(false),
    });
  }

  mettreAJourLigne(
    id: number,
    data: { quantite?: number; prixUnitaire?: number | null; datePeremption?: string | null }
  ): Observable<IApiResponse<IInventaire>> {
    return this.http.put<IApiResponse<IInventaire>>(`${this.api}/inventaire/${id}`, data);
  }

  majLigneLocale(updated: IInventaire): void {
    this.inventaire.update(inv =>
      inv.map(i => i.id === updated.id ? { ...i, ...updated } : i)
    );
  }

  // ── Recherche géolocalisée (utilisée par PharmaciesComponent) ───────────
  getById(id: number): Observable<IApiResponse<IPharmacie>> {
    return this.http.get<IApiResponse<IPharmacie>>(`${this.api}/pharmacies/${id}`);
  }

  rechercher(params: IRecherchePharmacieParamsEtendu = {}): Observable<IApiResponse<IPharmacie[]>> {
    let p = new HttpParams();
    if (params.lat           != null) p = p.set('lat',          params.lat);
    if (params.lng           != null) p = p.set('lng',          params.lng);
    if (params.rayon         != null) p = p.set('rayon',        params.rayon);
    if (params.medicamentNom)         p = p.set('medicamentNom', params.medicamentNom);
    if (params.ville)                 p = p.set('ville',         params.ville);
    return this.http.get<IApiResponse<IPharmacie[]>>(`${this.api}/pharmacies`, { params: p });
  }

  // ── CRUD admin ────────────────────────────────────────────────────────────
  creerPharmacie(data: Partial<IPharmacie>): Observable<IApiResponse<IPharmacie>> {
    return this.http.post<IApiResponse<IPharmacie>>(`${this.api}/pharmacies`, data);
  }

  modifierPharmacie(id: number, data: Partial<IPharmacie>): Observable<IApiResponse<IPharmacie>> {
    return this.http.put<IApiResponse<IPharmacie>>(`${this.api}/pharmacies/${id}`, data);
  }

  supprimerPharmacie(id: number): Observable<IApiResponse<void>> {
    return this.http.delete<IApiResponse<void>>(`${this.api}/pharmacies/${id}`);
  }
}