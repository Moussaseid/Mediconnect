// src/app/core/guards/medecin.guard.ts
import { inject }            from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService }       from '../services/auth.service';

/**
 * MedecinGuard — autorise uniquement les utilisateurs avec le rôle "medecin".
 */
export const medecinGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  if (!auth.estConnecte() || !auth.estTokenValide()) {
    return router.createUrlTree(['/login']);
  }

  if (auth.role() === 'medecin') return true;

  auth.redirigerSelonRole();
  return false;
};
