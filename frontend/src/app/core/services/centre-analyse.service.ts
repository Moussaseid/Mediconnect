import { Injectable, signal } from '@angular/core';
import { HttpClient }         from '@angular/common/http';
import { Observable, tap }    from 'rxjs';
import { environment }        from '../../../environments/environment';
import {
  IAnalysePropre,
  IAnalysePropreRequest,
  ICentreAnalyse,
  IApiResponse,
} from '../models/interfaces';

@Injectable({ providedIn: 'root' })
export class CentreAnalyseService {

  private readonly base = `${environment.apiUrl}/centre-analyse`;

  readonly analyses = signal<IAnalysePropre[]>([]);
  readonly centre   = signal<ICentreAnalyse | null>(null);

  constructor(private http: HttpClient) {}

  chargerInfos(): Observable<IApiResponse<ICentreAnalyse>> {
    return this.http.get<IApiResponse<ICentreAnalyse>>(`${this.base}/infos`)
      .pipe(tap(r => this.centre.set(r.data)));
  }

  chargerAnalyses(): Observable<IApiResponse<IAnalysePropre[]>> {
    return this.http.get<IApiResponse<IAnalysePropre[]>>(`${this.base}/analyses`)
      .pipe(tap(r => this.analyses.set(r.data)));
  }

  creer(data: IAnalysePropreRequest): Observable<IApiResponse<IAnalysePropre>> {
    return this.http.post<IApiResponse<IAnalysePropre>>(`${this.base}/analyses`, data)
      .pipe(tap(r => this.analyses.update(list => [...list, r.data])));
  }

  modifier(id: number, data: IAnalysePropreRequest): Observable<IApiResponse<IAnalysePropre>> {
    return this.http.put<IApiResponse<IAnalysePropre>>(`${this.base}/analyses/${id}`, data)
      .pipe(tap(r => this.analyses.update(list => list.map(a => a.id === id ? r.data : a))));
  }

  supprimer(id: number): Observable<IApiResponse<{id:number}>> {
    return this.http.delete<IApiResponse<{id:number}>>(`${this.base}/analyses/${id}`)
      .pipe(tap(() => this.analyses.update(list => list.filter(a => a.id !== id))));
  }

  toggle(id: number): Observable<IApiResponse<{id:number; disponible:boolean}>> {
    return this.http.patch<IApiResponse<{id:number; disponible:boolean}>>(`${this.base}/analyses/${id}/toggle`, {})
      .pipe(tap(r => this.analyses.update(list =>
        list.map(a => a.id === id ? { ...a, disponible: r.data.disponible } : a)
      )));
  }
}
