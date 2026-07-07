// core/services/centre.service.ts
import { Injectable }    from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable }    from 'rxjs';
import { environment }   from '../../../environments/environment';
import { IApiResponse, ICentreSante, ICentreAnalyse } from '../models/interfaces';

export type CentreType = 'sante' | 'analyse';
export type CentreUnion = ICentreSante | ICentreAnalyse;

export interface IRechercheCentreParams {
  lat?  : number;
  lng?  : number;
  rayon?: number;
  ville?: string;
}

@Injectable({ providedIn: 'root' })
export class CentreService {

  private readonly api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  lister(type: CentreType): Observable<IApiResponse<CentreUnion[]>> {
    return this.http.get<IApiResponse<CentreUnion[]>>(`${this.api}/centres/${type}`);
  }

  creer(type: CentreType, data: Record<string, unknown>): Observable<IApiResponse<CentreUnion>> {
    return this.http.post<IApiResponse<CentreUnion>>(`${this.api}/centres/${type}`, data);
  }

  modifier(type: CentreType, id: number, data: Record<string, unknown>): Observable<IApiResponse<CentreUnion>> {
    return this.http.put<IApiResponse<CentreUnion>>(`${this.api}/centres/${type}/${id}`, data);
  }

  supprimer(type: CentreType, id: number): Observable<IApiResponse<void>> {
    return this.http.delete<IApiResponse<void>>(`${this.api}/centres/${type}/${id}`);
  }

  // ── Recherche géolocalisée (utilisée par CentresComponent) ───────────────
  getCentresSante(params: IRechercheCentreParams = {}): Observable<IApiResponse<ICentreSante[]>> {
    return this.http.get<IApiResponse<ICentreSante[]>>(
      `${this.api}/centres/sante`, { params: this.buildParams(params) }
    );
  }

  getCentresAnalyse(params: IRechercheCentreParams = {}): Observable<IApiResponse<ICentreAnalyse[]>> {
    return this.http.get<IApiResponse<ICentreAnalyse[]>>(
      `${this.api}/centres/analyse`, { params: this.buildParams(params) }
    );
  }

  getCentreSanteById(id: number): Observable<IApiResponse<ICentreSante>> {
    return this.http.get<IApiResponse<ICentreSante>>(`${this.api}/centres/sante/${id}`);
  }

  getCentreAnalyseById(id: number): Observable<IApiResponse<ICentreAnalyse>> {
    return this.http.get<IApiResponse<ICentreAnalyse>>(`${this.api}/centres/analyse/${id}`);
  }

  private buildParams(p: IRechercheCentreParams): HttpParams {
    let params = new HttpParams();
    if (p.lat   != null) params = params.set('lat',   p.lat);
    if (p.lng   != null) params = params.set('lng',   p.lng);
    if (p.rayon != null) params = params.set('rayon', p.rayon);
    if (p.ville)         params = params.set('ville', p.ville);
    return params;
  }
}