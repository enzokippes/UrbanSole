import { Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { AuthResponse, User } from '../models/user.model';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  readonly currentUser = signal<User | null>(this.getStoredUser());
  readonly token = signal<string | null>(this.getStoredToken());

  readonly isAuthenticated = computed(() => !!this.token() && !!this.currentUser());
  readonly isAdmin = computed(() => this.currentUser()?.role === 'admin');

  register(data: { name: string; email: string; password: string; password_confirmation: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/auth/register`, data).pipe(
      tap(res => this.handleAuthSuccess(res))
    );
  }

  login(credentials: { email: string; password: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/auth/login`, credentials).pipe(
      tap(res => this.handleAuthSuccess(res))
    );
  }

  logout(): Observable<any> {
    return this.http.post(`${this.apiUrl}/auth/logout`, {}).pipe(
      tap({
        next: () => this.clearSession(),
        error: () => this.clearSession()
      })
    );
  }

  clearSession(): void {
    localStorage.removeItem('urbansole_token');
    localStorage.removeItem('urbansole_user');
    this.token.set(null);
    this.currentUser.set(null);
  }

  private handleAuthSuccess(res: AuthResponse): void {
    if (res.token) {
      localStorage.setItem('urbansole_token', res.token);
      this.token.set(res.token);
    }
    if (res.user) {
      localStorage.setItem('urbansole_user', JSON.stringify(res.user));
      this.currentUser.set(res.user);
    }
  }

  private getStoredToken(): string | null {
    try {
      return localStorage.getItem('urbansole_token');
    } catch {
      return null;
    }
  }

  private getStoredUser(): User | null {
    try {
      const data = localStorage.getItem('urbansole_user');
      return data ? JSON.parse(data) : null;
    } catch {
      return null;
    }
  }
}
