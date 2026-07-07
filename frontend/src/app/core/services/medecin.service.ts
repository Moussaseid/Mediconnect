import { Injectable, inject }       from '@angular/core';
import { HttpClient, HttpParams }   from '@angular/common/http';
import { Observable }               from 'rxjs';
import { environment }              from '../../../environments/environment';
import { IApiResponse, IMedecin, IMedecinUpdateRequest, IRechercheMedecinParams, ISpecialite, IHoraireSemaine, IIndisponibilite } from '../models/interfaces';

export interface IHoraireSaisie { jourSemaine: number; heureDebut: string; heureFin: string; }
export interface IIndisponibiliteCreateRequest { dateDebut: string; dateFin: string; motif?: string; }
export interface ICreneauJour { date: string; creneaux: string[]; }

/**
 * Surcouche de IRechercheMedecinParams (non modifié) : ville en plus, rayon élargi
 * en nombre continu (slider 5-100 km) plutôt que la liste fixe 5|10|20|50.
 */
export interface IRechercheMedecinParamsEtendu {
  specialiteId?: number;
  rayon?: number;
  lat?: number;
  lng?: number;
  ville?: string;
}

@Injectable({ providedIn: 'root' })
export class MedecinService {

  private readonly http   = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  rechercher(filtres: IRechercheMedecinParamsEtendu = {}): Observable<IApiResponse<IMedecin[]>> {
    let params = new HttpParams();
    if (filtres.lat          != null) params = params.set('lat',          filtres.lat);
    if (filtres.lng          != null) params = params.set('lng',          filtres.lng);
    if (filtres.rayon        != null) params = params.set('rayon',        filtres.rayon);
    if (filtres.specialiteId != null) params = params.set('specialiteId', filtres.specialiteId);
    if (filtres.ville)                params = params.set('ville',        filtres.ville);
    return this.http.get<IApiResponse<IMedecin[]>>(`${this.apiUrl}/medecins`, { params });
  }

  getById(id: number): Observable<IApiResponse<IMedecin>> {
    return this.http.get<IApiResponse<IMedecin>>(`${this.apiUrl}/medecins/${id}`);
  }

  moi(): Observable<IApiResponse<IMedecin>> {
    return this.http.get<IApiResponse<IMedecin>>(`${this.apiUrl}/medecins/me`);
  }

  mettreAJour(id: number, data: IMedecinUpdateRequest): Observable<IApiResponse<IMedecin>> {
    return this.http.put<IApiResponse<IMedecin>>(`${this.apiUrl}/medecins/${id}`, data);
  }

  getSpecialites(): Observable<IApiResponse<ISpecialite[]>> {
    return this.http.get<IApiResponse<ISpecialite[]>>(`${this.apiUrl}/specialites`);
  }

  mettreAJourHoraires(id: number, horaires: IHoraireSaisie[]): Observable<IApiResponse<IMedecin>> {
    return this.http.put<IApiResponse<IMedecin>>(`${this.apiUrl}/medecins/${id}/horaires`, { horaires });
  }

  listerIndisponibilites(id: number): Observable<IApiResponse<IIndisponibilite[]>> {
    return this.http.get<IApiResponse<IIndisponibilite[]>>(`${this.apiUrl}/medecins/${id}/indisponibilites`);
  }

  creerIndisponibilite(id: number, data: IIndisponibiliteCreateRequest): Observable<IApiResponse<IIndisponibilite[]>> {
    return this.http.post<IApiResponse<IIndisponibilite[]>>(`${this.apiUrl}/medecins/${id}/indisponibilites`, data);
  }

  supprimerIndisponibilite(id: number, indispoId: number): Observable<IApiResponse<IIndisponibilite[]>> {
    return this.http.delete<IApiResponse<IIndisponibilite[]>>(`${this.apiUrl}/medecins/${id}/indisponibilites/${indispoId}`);
  }

  getCreneaux(id: number, jours = 14): Observable<IApiResponse<ICreneauJour[]>> {
    const params = new HttpParams().set('jours', jours);
    return this.http.get<IApiResponse<ICreneauJour[]>>(`${this.apiUrl}/medecins/${id}/creneaux`, { params });
  }
}