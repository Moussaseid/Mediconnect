import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, of }     from 'rxjs';
import { map, catchError }    from 'rxjs/operators';

export interface IAdresseSuggestion {
  label: string;
  lat: number;
  lng: number;
}

/**
 * AdresseService — Autocomplétion et géocodage d'adresses françaises.
 * Utilise l'API Adresse (Base Adresse Nationale, data.gouv.fr) : gratuite, sans clé.
 */
@Injectable({ providedIn: 'root' })
export class AdresseService {

  private readonly http           = inject(HttpClient);
  private readonly baseUrl        = 'https://api-adresse.data.gouv.fr/search/';
  private readonly baseUrlReverse = 'https://api-adresse.data.gouv.fr/reverse/';

  rechercher(q: string, limit = 5): Observable<IAdresseSuggestion[]> {
    if (!q || q.trim().length < 3) return of([]);

    const params = new HttpParams().set('q', q.trim()).set('limit', limit);

    return this.http.get<{ features: any[] }>(this.baseUrl, { params }).pipe(
      map(res => (res.features ?? []).map(f => ({
        label: f.properties.label as string,
        lat  : f.geometry.coordinates[1] as number,
        lng  : f.geometry.coordinates[0] as number,
      }))),
      catchError(() => of([]))
    );
  }

  /**
   * Géocodage inverse : retrouve le libellé d'adresse le plus proche de coordonnées
   * (utilisé pour afficher la position GPS exacte dans le champ adresse).
   */
  inverser(lat: number, lng: number): Observable<string | null> {
    const params = new HttpParams().set('lon', lng).set('lat', lat);

    return this.http.get<{ features: any[] }>(this.baseUrlReverse, { params }).pipe(
      map(res => res.features?.[0]?.properties?.label ?? null),
      catchError(() => of(null))
    );
  }
}