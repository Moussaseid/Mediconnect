// src/app/features/auth/login/login.component.ts
import { Component, signal }       from '@angular/core';
import { CommonModule }            from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { RouterLink }              from '@angular/router';
import { AuthService }             from '../../../core/services/auth.service';
import { HttpErrorResponse }       from '@angular/common/http';

@Component({
  selector   : 'app-login',
  standalone : true,
  imports    : [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './login.component.html',
})
export class LoginComponent {

  form: FormGroup;
  erreur  = signal<string | null>(null);
  loading = signal(false);

  constructor(private fb: FormBuilder, private auth: AuthService) {
    this.form = this.fb.group({
      email   : ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
    });
  }

  get email()    { return this.form.get('email')!;    }
  get password() { return this.form.get('password')!; }

  soumettre(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.erreur.set(null);

    this.auth.login(this.form.value).subscribe({
      next : () => {
        this.loading.set(false);
        this.auth.redirigerSelonRole();
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.erreur.set(err.error?.error ?? 'Erreur de connexion');
      },
    });
  }
}
