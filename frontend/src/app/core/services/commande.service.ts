// core/services/commande.service.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient }          from '@angular/common/http';
import { Observable }          from 'rxjs';
import { environment }         from '../../../environments/environment';
import { ICommande, ICommandeCreateRequest, IApiResponse, CommandeStatut } from '../models/interfaces';

@Injectable({ providedIn: 'root' })
export class CommandeService {

  private readonly api = environment.apiUrl;

  readonly commandes    = signal<ICommande[]>([]);
  readonly chargement   = signal<boolean>(false);
  readonly erreur       = signal<string | null>(null);

  constructor(private http: HttpClient) {}

  chargerParPharmacie(pharmacieId: number): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http
      .get<IApiResponse<ICommande[]>>(`${this.api}/commandes?pharmacieId=${pharmacieId}`)
      .subscribe({
        next    : res => this.commandes.set(res.data),
        error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement des commandes'),
        complete: () => this.chargement.set(false),
      });
  }

  creer(data: ICommandeCreateRequest): Observable<IApiResponse<ICommande>> {
    return this.http.post<IApiResponse<ICommande>>(`${this.api}/commandes`, data);
  }

  mettreAJourStatut(id: number, statut: CommandeStatut): Observable<IApiResponse<ICommande>> {
    return this.http.put<IApiResponse<ICommande>>(`${this.api}/commandes/${id}`, { statut });
  }

  majCommandeLocale(updated: ICommande): void {
    this.commandes.update(list =>
      list.map(c => c.id === updated.id ? { ...c, ...updated } : c)
    );
  }
}
