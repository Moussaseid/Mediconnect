// src/app/core/services/auth.service.ts
import { Injectable, signal, computed } from '@angular/core';
import { HttpClient }                   from '@angular/common/http';
import { Router }                       from '@angular/router';
import { Observable, tap }              from 'rxjs';
import { environment }                  from '../../../environments/environment';
import {
  IAuthResponse,
  ILoginRequest,
  IRegisterRequest,
  IProfilUpdateRequest,
  IUserUpdateRequest,
  IUser,
  IApiResponse,
} from '../models/interfaces';

const TOKEN_KEY = 'mc_token';
const USER_KEY  = 'mc_user';

@Injectable({ providedIn: 'root' })
export class AuthService {

  // ── Signal d'état ─────────────────────────────────────────────────────────
  private _user = signal<IUser | null>(this.chargerUserLocal());
  readonly user  = this._user.asReadonly();
  readonly estConnecte = computed(() => this._user() !== null);
  readonly role        = computed(() => this._user()?.role ?? null);

  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient, private router: Router) {}

  // ── Login ─────────────────────────────────────────────────────────────────
  login(req: ILoginRequest): Observable<IApiResponse<IAuthResponse>> {
    return this.http
      .post<IApiResponse<IAuthResponse>>(`${this.apiUrl}/auth/login`, req)
      .pipe(tap(res => this.sauvegarderSession(res.data)));
  }

  // ── Register ──────────────────────────────────────────────────────────────
  register(req: IRegisterRequest): Observable<IApiResponse<IAuthResponse>> {
    return this.http
      .post<IApiResponse<IAuthResponse>>(`${this.apiUrl}/auth/register`, req)
      .pipe(tap(res => this.sauvegarderSession(res.data)));
  }

  // ── Me — recharge user depuis BDD ─────────────────────────────────────────
  me(): Observable<IApiResponse<IUser>> {
    return this.http
      .get<IApiResponse<IUser>>(`${this.apiUrl}/auth/me`)
      .pipe(tap(res => {
        this._user.set(res.data);
        localStorage.setItem(USER_KEY, JSON.stringify(res.data));
      }));
  }

  // ── Logout — notifie le serveur pour MongoDB, puis nettoie le local ───────
  logout(): void {
    const nettoyer = () => {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
      this._user.set(null);
      this.router.navigate(['/login']);
    };

    this.http.post(`${this.apiUrl}/auth/logout`, {}).subscribe({
      next : () => nettoyer(),
      error: () => nettoyer(), // On nettoie même si l'API est injoignable
    });
  }

  // ── Mise à jour du profil ─────────────────────────────────────────────────
  updateProfil(data: IProfilUpdateRequest): Observable<IApiResponse<IUser>> {
    return this.http
      .patch<IApiResponse<IUser>>(`${this.apiUrl}/auth/profil`, data)
      .pipe(tap(res => {
        // Fusionner les données mises à jour dans le signal et localStorage
        const updated = { ...this._user()!, ...res.data };
        this._user.set(updated);
        localStorage.setItem(USER_KEY, JSON.stringify(updated));
      }));
  }

  // ── Alias étendu (inclut motDePasse optionnel) ───────────────────────────
  mettreAJourProfil(data: IUserUpdateRequest): Observable<IApiResponse<IUser>> {
    return this.http
      .patch<IApiResponse<IUser>>(`${this.apiUrl}/auth/profil`, data)
      .pipe(tap(res => {
        const updated = { ...this._user()!, ...res.data };
        this._user.set(updated);
        localStorage.setItem(USER_KEY, JSON.stringify(updated));
      }));
  }

  // ── Token ─────────────────────────────────────────────────────────────────
  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  estTokenValide(): boolean {
    const token = this.getToken();
    if (!token) return false;
    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      return payload.exp * 1000 > Date.now();
    } catch {
      return false;
    }
  }

  // ── Redirection post-login selon le rôle ──────────────────────────────────
  redirigerSelonRole(): void {
    const routes: Record<string, string> = {
      patient        : '/patient/dashboard',
      medecin        : '/medecin/dashboard',
      admin          : '/admin/dashboard',
      pharmacie      : '/pharmacie/stock',
      centre_analyse : '/centre-analyse/dashboard',
      centre_sante   : '/centre-sante/dashboard',
    };
    const dest = routes[this.role() ?? ''] ?? '/login';
    this.router.navigate([dest]);
  }

  // ── Nettoyage de session (guard + logout) ────────────────────────────────
  clearSession(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this._user.set(null);
  }

  // ── Privé ─────────────────────────────────────────────────────────────────
  private sauvegarderSession(data: IAuthResponse): void {
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(USER_KEY,  JSON.stringify(data.user));
    this._user.set(data.user);
  }

  private chargerUserLocal(): IUser | null {
    try {
      const raw = localStorage.getItem(USER_KEY);
      return raw ? (JSON.parse(raw) as IUser) : null;
    } catch {
      return null;
    }
  }
}
