import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  template: `
    <main class="profile-page">
      <div class="container">
        <div class="profile-hero">
          <div class="hero-left">
            <div class="avatar-circle">{{ initials() }}</div>
            <div>
              <span class="role-badge" [class.admin]="authService.isAdmin()">
                {{ authService.isAdmin() ? 'Administrador' : 'Cliente UrbanSole' }}
              </span>
              <h1>{{ authService.currentUser()?.name || 'Mi Perfil' }}</h1>
              <p class="hero-email">{{ authService.currentUser()?.email }}</p>
            </div>
          </div>
          <div class="hero-actions">
            <a routerLink="/orders" class="btn-secondary">Mis pedidos</a>
            <a routerLink="/catalog" class="btn-primary">Ir al catálogo</a>
          </div>
        </div>

        <div class="profile-grid">
          <section class="profile-card">
            <div class="card-head">
              <h2>Datos personales</h2>
              <p>Actualizá tu nombre y correo electrónico asociado a tu cuenta.</p>
            </div>

            <div *ngIf="profileSuccess()" class="alert alert-success" role="status">{{ profileSuccess() }}</div>
            <div *ngIf="profileError()" class="alert alert-error" role="alert">{{ profileError() }}</div>

            <form [formGroup]="profileForm" (ngSubmit)="saveProfile()" class="profile-form">
              <div class="field">
                <label for="profName">Nombre completo</label>
                <input
                  id="profName"
                  type="text"
                  formControlName="name"
                  autocomplete="name"
                  class="input"
                  [class.invalid]="profileForm.get('name')?.touched && profileForm.get('name')?.invalid"
                />
                <span *ngIf="profileForm.get('name')?.touched && profileForm.get('name')?.invalid" class="field-error">
                  Ingresá un nombre de al menos 2 caracteres.
                </span>
              </div>

              <div class="field">
                <label for="profEmail">Correo electrónico</label>
                <input
                  id="profEmail"
                  type="email"
                  formControlName="email"
                  autocomplete="email"
                  class="input"
                  [class.invalid]="profileForm.get('email')?.touched && profileForm.get('email')?.invalid"
                />
                <span *ngIf="profileForm.get('email')?.touched && profileForm.get('email')?.invalid" class="field-error">
                  Ingresá un correo electrónico válido.
                </span>
              </div>

              <button type="submit" [disabled]="savingProfile()" class="btn-primary submit-btn">
                {{ savingProfile() ? 'Guardando...' : 'Guardar cambios' }}
              </button>
            </form>
          </section>

          <section class="profile-card">
            <div class="card-head">
              <div class="head-row">
                <div>
                  <h2>Seguridad y contraseña</h2>
                  <p>Elegí una contraseña segura de al menos 8 caracteres.</p>
                </div>
                <button type="button" (click)="showPasswords.set(!showPasswords())" class="toggle-btn">
                  {{ showPasswords() ? 'Ocultar' : 'Ver' }}
                </button>
              </div>
            </div>

            <div *ngIf="passwordSuccess()" class="alert alert-success" role="status">{{ passwordSuccess() }}</div>
            <div *ngIf="passwordError()" class="alert alert-error" role="alert">{{ passwordError() }}</div>

            <form [formGroup]="passwordForm" (ngSubmit)="savePassword()" class="profile-form">
              <div class="field">
                <label for="curPass">Contraseña actual</label>
                <input
                  id="curPass"
                  [type]="showPasswords() ? 'text' : 'password'"
                  formControlName="current_password"
                  autocomplete="current-password"
                  placeholder="••••••••"
                  class="input"
                  [class.invalid]="passwordForm.get('current_password')?.touched && passwordForm.get('current_password')?.invalid"
                />
                <span *ngIf="passwordForm.get('current_password')?.touched && passwordForm.get('current_password')?.invalid" class="field-error">
                  Ingresá tu contraseña actual.
                </span>
              </div>

              <div class="field">
                <label for="newPass">Nueva contraseña</label>
                <input
                  id="newPass"
                  [type]="showPasswords() ? 'text' : 'password'"
                  formControlName="password"
                  autocomplete="new-password"
                  placeholder="Mínimo 8 caracteres"
                  class="input"
                  [class.invalid]="passwordForm.get('password')?.touched && passwordForm.get('password')?.invalid"
                />
                <span *ngIf="passwordForm.get('password')?.touched && passwordForm.get('password')?.invalid" class="field-error">
                  La nueva contraseña debe tener al menos 8 caracteres.
                </span>
              </div>

              <div class="field">
                <label for="confPass">Confirmar nueva contraseña</label>
                <input
                  id="confPass"
                  [type]="showPasswords() ? 'text' : 'password'"
                  formControlName="password_confirmation"
                  autocomplete="new-password"
                  placeholder="Repetí la nueva contraseña"
                  class="input"
                  [class.invalid]="passwordForm.get('password_confirmation')?.touched && (passwordForm.get('password_confirmation')?.invalid || passwordMismatch())"
                />
                <span *ngIf="passwordForm.get('password_confirmation')?.touched && passwordMismatch()" class="field-error">
                  Las contraseñas no coinciden.
                </span>
              </div>

              <button type="submit" [disabled]="savingPassword()" class="btn-primary submit-btn">
                {{ savingPassword() ? 'Actualizando...' : 'Actualizar contraseña' }}
              </button>
            </form>
          </section>
        </div>
      </div>
    </main>
  `,
  styles: [`
    .profile-page { min-height: 100vh; padding: 108px 0 4rem; background: var(--bg-main); }
    .profile-hero {
      display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;
      background: #141418; border: 1px solid rgba(255,255,255,.09); border-radius: 1.25rem;
      padding: 1.75rem; margin-bottom: 1.75rem;
    }
    .hero-left { display: flex; align-items: center; gap: 1.15rem; }
    .avatar-circle {
      width: 64px; height: 64px; border-radius: 9999px; flex-shrink: 0;
      background: linear-gradient(135deg, #4ade80, #22c55e); color: #052e16;
      font-size: 1.4rem; font-weight: 900; display: grid; place-items: center;
    }
    .role-badge {
      display: inline-block; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
      padding: .2rem .65rem; border-radius: 9999px; background: rgba(255,255,255,.08); color: #d4d4d8;
    }
    .role-badge.admin { background: rgba(74,222,128,.16); color: #4ade80; }
    h1 { font-size: 1.6rem; font-weight: 900; color: #fff; margin: .35rem 0 .15rem; }
    .hero-email { color: rgba(255,255,255,.6); font-size: .9rem; margin: 0; }
    .hero-actions { display: flex; gap: .65rem; flex-wrap: wrap; }
    .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 1.5rem; }
    .profile-card {
      background: #141418; border: 1px solid rgba(255,255,255,.09); border-radius: 1.25rem;
      padding: 1.75rem; display: flex; flex-direction: column; gap: 1.1rem;
    }
    .card-head h2 { font-size: 1.15rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
    .card-head p { font-size: .85rem; color: rgba(255,255,255,.6); margin: 0; }
    .head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .toggle-btn {
      background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); color: #fff;
      border-radius: .5rem; padding: .35rem .75rem; font-size: .78rem; font-weight: 700; cursor: pointer;
    }
    .profile-form { display: flex; flex-direction: column; gap: 1rem; }
    .field { display: flex; flex-direction: column; gap: .4rem; }
    .field label { font-size: .8rem; font-weight: 700; color: rgba(255,255,255,.78); }
    .input {
      background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); border-radius: .65rem;
      padding: .7rem .9rem; color: #fff; font-size: .9rem; font-family: inherit;
    }
    .input:focus { outline: none; border-color: #4ade80; }
    .input.invalid { border-color: #ef4444; }
    .field-error { font-size: .76rem; color: #f87171; }
    .submit-btn { align-self: flex-start; margin-top: .25rem; }
    .alert { padding: .75rem 1rem; border-radius: .7rem; font-size: .85rem; font-weight: 600; }
    .alert-success { background: rgba(74,222,128,.12); border: 1px solid rgba(74,222,128,.35); color: #86efac; }
    .alert-error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }
  `]
})
export class ProfileComponent {
  readonly authService = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  readonly initials = computed(() => {
    const name = (this.authService.currentUser()?.name || 'US').trim();
    const parts = name.split(/\s+/);
    return (parts.length >= 2 ? parts[0][0] + parts[1][0] : name.slice(0, 2)).toUpperCase();
  });

  savingProfile = signal(false);
  profileSuccess = signal<string | null>(null);
  profileError = signal<string | null>(null);

  savingPassword = signal(false);
  passwordSuccess = signal<string | null>(null);
  passwordError = signal<string | null>(null);
  showPasswords = signal(false);

  profileForm: FormGroup = this.fb.group({
    name: [this.authService.currentUser()?.name || '', [Validators.required, Validators.minLength(2)]],
    email: [this.authService.currentUser()?.email || '', [Validators.required, Validators.email]]
  });

  passwordForm: FormGroup = this.fb.group({
    current_password: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]]
  });

  passwordMismatch(): boolean {
    const p = this.passwordForm.get('password')?.value;
    const c = this.passwordForm.get('password_confirmation')?.value;
    return Boolean(c) && p !== c;
  }

  private extractError(err: any, fallback: string): string {
    const errors = err?.error?.errors;
    if (errors && typeof errors === 'object') {
      const msgs = Object.values(errors).flat().filter((m): m is string => typeof m === 'string' && m.trim().length > 0);
      if (msgs.length) return msgs.join(' ');
    }
    return err?.error?.message || fallback;
  }

  saveProfile(): void {
    if (this.profileForm.invalid) {
      this.profileForm.markAllAsTouched();
      return;
    }

    this.savingProfile.set(true);
    this.profileSuccess.set(null);
    this.profileError.set(null);

    this.authService.updateProfile(this.profileForm.value).subscribe({
      next: res => {
        this.savingProfile.set(false);
        this.profileSuccess.set(res.message || 'Perfil actualizado correctamente.');
      },
      error: err => {
        this.savingProfile.set(false);
        this.profileError.set(this.extractError(err, 'No se pudo actualizar tu perfil.'));
      }
    });
  }

  savePassword(): void {
    if (this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }
    if (this.passwordMismatch()) {
      this.passwordError.set('Las contraseñas no coinciden.');
      return;
    }

    this.savingPassword.set(true);
    this.passwordSuccess.set(null);
    this.passwordError.set(null);

    this.authService.changePassword(this.passwordForm.value).subscribe({
      next: res => {
        this.savingPassword.set(false);
        this.passwordForm.reset({ current_password: '', password: '', password_confirmation: '' });
        this.passwordSuccess.set(res.message || 'Contraseña actualizada correctamente.');
      },
      error: err => {
        this.savingPassword.set(false);
        this.passwordError.set(this.extractError(err, 'No se pudo cambiar la contraseña.'));
      }
    });
  }
}
