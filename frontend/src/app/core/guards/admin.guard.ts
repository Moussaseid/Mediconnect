// src/app/core/guards/admin.guard.ts
import { inject }            from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService }       from '../services/auth.service';

/**
 * AdminGuard — autorise uniquement les utilisateurs avec le rôle "admin".
 */
export const adminGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  if (!auth.estConnecte() || !auth.estTokenValide()) {
    return router.createUrlTree(['/login']);
  }

  if (auth.role() === 'admin') return true;

  auth.redirigerSelonRole();
  return false;
};
