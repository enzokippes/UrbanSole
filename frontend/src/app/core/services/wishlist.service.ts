import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Product } from '../models/product.model';
import { AuthService } from './auth.service';

@Injectable({
  providedIn: 'root'
})
export class WishlistService {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly storeVersion = signal(0);

  readonly items = computed<Product[]>(() => {
    this.storeVersion();
    const user = this.authService.currentUser();
    if (!this.authService.isAuthenticated() || !user?.id) {
      return [];
    }
    return this.loadForUser(user.id);
  });

  readonly count = computed(() => this.items().length);

  isFavorite(productId: number): boolean {
    return this.items().some(p => p.id === productId);
  }

  toggle(product: Product): boolean {
    const user = this.authService.currentUser();
    if (!this.authService.isAuthenticated() || !user?.id) {
      this.router.navigate(['/login']);
      return false;
    }

    const current = this.loadForUser(user.id);
    const exists = current.some(p => p.id === product.id);
    const updated = exists
      ? current.filter(p => p.id !== product.id)
      : [product, ...current];

    this.saveForUser(user.id, updated);
    return !exists;
  }

  remove(productId: number): void {
    const user = this.authService.currentUser();
    if (!this.authService.isAuthenticated() || !user?.id) return;

    const updated = this.loadForUser(user.id).filter(p => p.id !== productId);
    this.saveForUser(user.id, updated);
  }

  clear(): void {
    const user = this.authService.currentUser();
    if (!user?.id) return;

    try {
      localStorage.removeItem(this.keyForUser(user.id));
    } catch {
      // Ignorar si localStorage no está disponible
    }
    this.storeVersion.update(v => v + 1);
  }

  private keyForUser(userId: number): string {
    return `urbansole_wishlist_user_${userId}`;
  }

  private saveForUser(userId: number, products: Product[]): void {
    try {
      localStorage.setItem(this.keyForUser(userId), JSON.stringify(products));
    } catch {
      // Ignorar si localStorage no está disponible
    }
    this.storeVersion.update(v => v + 1);
  }

  private loadForUser(userId: number): Product[] {
    try {
      const raw = localStorage.getItem(this.keyForUser(userId));
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
}
