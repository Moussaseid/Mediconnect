import { Injectable, signal } from '@angular/core';
import { HttpClient }         from '@angular/common/http';
import { Observable, tap }    from 'rxjs';
import { environment }        from '../../../environments/environment';
import {
  ICentreSante,
  ICentreSanteUpdateRequest,
  IApiResponse,
} from '../models/interfaces';

@Injectable({ providedIn: 'root' })
export class CentreSanteService {

  private readonly base = `${environment.apiUrl}/centre-sante`;

  readonly centre = signal<ICentreSante | null>(null);

  constructor(private http: HttpClient) {}

  chargerInfos(): Observable<IApiResponse<ICentreSante>> {
    return this.http.get<IApiResponse<ICentreSante>>(`${this.base}/infos`)
      .pipe(tap(r => this.centre.set(r.data)));
  }

  modifierInfos(data: ICentreSanteUpdateRequest): Observable<IApiResponse<ICentreSante>> {
    return this.http.put<IApiResponse<ICentreSante>>(`${this.base}/infos`, data)
      .pipe(tap(r => this.centre.set(r.data)));
  }

  uploadPhoto(file: File): Observable<IApiResponse<{photoPath:string}>> {
    const form = new FormData();
    form.append('photo', file);
    return this.http.post<IApiResponse<{photoPath:string}>>(`${this.base}/photo`, form)
      .pipe(tap(r => this.centre.update(c => c ? { ...c, photoPath: r.data.photoPath } : c)));
  }
}
