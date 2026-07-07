// core/services/prescription.service.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient }          from '@angular/common/http';
import { environment }         from '../../../environments/environment';
import { IPrescription, IApiResponse } from '../models/interfaces';

@Injectable({ providedIn: 'root' })
export class PrescriptionService {

  private readonly api = environment.apiUrl;

  readonly prescriptions = signal<IPrescription[]>([]);
  readonly chargement    = signal<boolean>(false);
  readonly erreur        = signal<string | null>(null);

  constructor(private http: HttpClient) {}

  charger(): void {
    this.chargement.set(true);
    this.erreur.set(null);
    this.http.get<IApiResponse<IPrescription[]>>(`${this.api}/prescriptions`).subscribe({
      next    : res => this.prescriptions.set(res.data),
      error   : err => this.erreur.set(err.error?.error ?? 'Erreur chargement des ordonnances'),
      complete: () => this.chargement.set(false),
    });
  }
}