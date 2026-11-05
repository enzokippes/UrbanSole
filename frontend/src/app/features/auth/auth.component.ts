import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-auth',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './auth.component.html',
  styleUrl: './auth.component.css'
})
export class AuthComponent {
  private readonly fb = inject(FormBuilder);
  readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  private afterLogin(): void {
    const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
    if (returnUrl === '/checkout') {
      this.router.navigateByUrl('/checkout');
    } else if (this.authService.isAdmin()) {
      this.router.navigate(['/admin']);
    } else {
      this.router.navigate(['/catalog']);
    }
  }

  isLoginMode = signal<boolean>(true);
  loading = signal<boolean>(false);
  errorMessage = signal<string | null>(null);
  successMessage = signal<string | null>(null);
  showPassword = signal<boolean>(false);

  loginForm: FormGroup = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]]
  });

  registerForm: FormGroup = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(2)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]]
  });

  toggleMode(login: boolean): void {
    this.isLoginMode.set(login);
    this.errorMessage.set(null);
    this.successMessage.set(null);
  }

  togglePasswordVisibility(): void {
    this.showPassword.update(prev => !prev);
  }

  onLogin(): void {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService.login(this.loginForm.value).subscribe({
      next: () => {
        this.loading.set(false);
        this.afterLogin();
      },
      error: (err: any) => {
        this.loading.set(false);
        const msg = err.error?.message || 'Credenciales incorrectas o error en el servidor.';
        this.errorMessage.set(msg);
      }
    });
  }

  onRegister(): void {
    if (this.registerForm.invalid) {
      this.registerForm.markAllAsTouched();
      return;
    }

    if (this.registerForm.value.password !== this.registerForm.value.password_confirmation) {
      this.errorMessage.set('Las contraseñas no coinciden.');
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService.register(this.registerForm.value).subscribe({
      next: () => {
        this.loading.set(false);
        this.afterLogin();
      },
      error: (err: any) => {
        this.loading.set(false);
        const msg = err.error?.message || 'Error al registrar el usuario.';
        this.errorMessage.set(msg);
      }
    });
  }

  /**
   * Semana 7: Autenticación con Proveedor Externo OAuth 2.0 (Google / GitHub)
   */
  onSocialLogin(provider: 'google' | 'github'): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService.socialLogin(provider).subscribe({
      next: () => {
        this.loading.set(false);
        this.afterLogin();
      },
      error: (err: any) => {
        this.loading.set(false);
        this.errorMessage.set(err.error?.message || `Error en autenticación con ${provider}.`);
      }
    });
  }

  fillDemoCredentials(role: 'admin' | 'customer'): void {
    this.isLoginMode.set(true);
    if (role === 'admin') {
      this.loginForm.patchValue({
        email: 'admin@urbansole.com',
        password: 'password'
      });
    } else {
      this.loginForm.patchValue({
        email: 'customer@urbansole.com',
        password: 'password'
      });
    }
  }
}
