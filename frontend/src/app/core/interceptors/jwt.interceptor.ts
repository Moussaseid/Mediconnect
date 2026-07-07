// src/app/core/interceptors/jwt.interceptor.ts
// Injecte automatiquement le header Authorization: Bearer <token>
// sur toutes les requêtes vers /api/

import { HttpInterceptorFn, HttpRequest, HttpHandlerFn, HttpErrorResponse } from '@angular/common/http';
import { inject }         from '@angular/core';
import { Router }         from '@angular/router';
import { catchError }     from 'rxjs/operators';
import { throwError }     from 'rxjs';
import { AuthService }    from '../services/auth.service';
import { environment }    from '../../../environments/environment';

export const jwtInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn
) => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  // N'injecte le token que sur les requêtes vers notre API
  const estRequeteApi = req.url.startsWith(environment.apiUrl);

  if (estRequeteApi) {
    const token = auth.getToken();
    if (token) {
      req = req.clone({
        setHeaders: { Authorization: `Bearer ${token}` },
      });
    }
  }

  return next(req).pipe(
    catchError((err: HttpErrorResponse) => {
      // Token expiré ou invalide → déconnexion automatique
      if (err.status === 401 && estRequeteApi) {
        auth.logout();
      }
      return throwError(() => err);
    })
  );
};
