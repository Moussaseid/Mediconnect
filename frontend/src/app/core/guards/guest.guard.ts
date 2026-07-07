// src/app/core/guards/guest.guard.ts
// Empêche un utilisateur connecté d'accéder à /login ou /register
import { inject }      from '@angular/core';
import { CanActivateFn } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  if (auth.estConnecte() && auth.estTokenValide()) {
    auth.redirigerSelonRole();
    return false;
  }
  return true;
};
