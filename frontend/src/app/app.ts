import { Component, OnInit, inject } from '@angular/core';
import { RouterOutlet }              from '@angular/router';
import { AuthService }               from './core/services/auth.service';

@Component({
  selector: 'app-root',
  imports : [RouterOutlet],
  template: '<router-outlet />',
})
export class App implements OnInit {
  private auth = inject(AuthService);

  ngOnInit(): void {
    // Si un token est présent en localStorage, le revalider côté serveur
    // au démarrage. Cela expulse les tokens expirés ou révoqués
    // sans attendre qu'une route protégée soit visitée.
    if (this.auth.estConnecte() && this.auth.estTokenValide()) {
      this.auth.me().subscribe({
        error: () => {
          // Token rejeté par le serveur (expiré, révoqué, utilisateur suspendu…)
          // Nettoyer la session et laisser les guards rediriger si besoin.
          this.auth.logout();
        },
      });
    }
  }
}
