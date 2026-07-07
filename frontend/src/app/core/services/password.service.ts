// src/app/core/services/password.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient }         from '@angular/common/http';
import { Observable }         from 'rxjs';
import { environment }        from '../../../environments/environment';
import { IApiResponse }       from '../models/interfaces';

@Injectable({ providedIn: 'root' })
export class PasswordService {
  private http   = inject(HttpClient);
  private apiUrl = environment.apiUrl;

  forgot(email: string): Observable<IApiResponse<{ message: string; token?: string }>> {
    return this.http.post<IApiResponse<{ message: string; token?: string }>>(
      `${this.apiUrl}/auth/forgot`, { email }
    );
  }

  reset(token: string, password: string): Observable<IApiResponse<null>> {
    return this.http.post<IApiResponse<null>>(
      `${this.apiUrl}/auth/reset`, { token, password }
    );
  }
}
