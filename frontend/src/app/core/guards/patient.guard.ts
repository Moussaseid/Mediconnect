// src/app/core/guards/patient.guard.ts
import { inject }            from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService }       from '../services/auth.service';

/**
 * PatientGuard — autorise uniquement les utilisateurs avec le rôle "patient".
 * Redirige vers /login si non connecté, sinon vers l'espace du rôle courant.
 */
export const patientGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  if (!auth.estConnecte() || !auth.estTokenValide()) {
    return router.createUrlTree(['/login']);
  }

  if (auth.role() === 'patient') return true;

  // Connecté mais mauvais rôle → rediriger vers son espace
  auth.redirigerSelonRole();
  return false;
};
