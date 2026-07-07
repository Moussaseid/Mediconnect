// src/app/core/guards/role.guard.ts
import { inject }                from '@angular/core';
import { CanActivateFn, Router, ActivatedRouteSnapshot } from '@angular/router';
import { AuthService }           from '../services/auth.service';
import { UserRole }              from '../models/interfaces';

export const roleGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const auth    = inject(AuthService);
  const router  = inject(Router);
  const roles   = route.data['roles'] as UserRole[];

  if (!auth.estConnecte() || !auth.estTokenValide()) {
    return router.createUrlTree(['/login']);
  }

  if (roles && !roles.includes(auth.role() as UserRole)) {
    // Connecté mais mauvais rôle → rediriger vers son espace
    auth.redirigerSelonRole();
    return false;
  }

  return true;
};
