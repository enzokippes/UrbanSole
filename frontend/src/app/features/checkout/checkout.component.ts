import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { CartService } from '../../core/services/cart.service';
import { AuthService } from '../../core/services/auth.service';
import { OrderService } from '../../core/services/order.service';
@Component({ selector: 'app-checkout', standalone: true, imports: [CommonModule, FormsModule], templateUrl: './checkout.component.html', styleUrl: './checkout.component.css' })
export class CheckoutComponent {
  readonly cart = inject(CartService);
  private readonly orders = inject(OrderService);
  private readonly router = inject(Router);
  readonly busy = signal(false); readonly error = signal('');
  readonly shipping = 0;
  form = { recipient: inject(AuthService).currentUser()?.name || '', phone: '', address: '', city: '', postal_code: '' };
  private key = crypto.randomUUID();
  private fingerprint = '';
  constructor() {
    try {
      const saved = JSON.parse(sessionStorage.getItem('urbansole_checkout') || 'null');
      if (saved && typeof saved.key === 'string' && typeof saved.fingerprint === 'string') {
        this.key = saved.key; this.fingerprint = saved.fingerprint;
      }
    } catch { /* Storage may be unavailable. */ }
  }
  submit() {
    if (this.busy() || !this.cart.items().length) return;
    const items = this.cart.items().map(({ product_id, size, color, quantity }) => ({ product_id, size, color, quantity }));
    const fingerprint = JSON.stringify({ ...this.form, items });
    if (this.fingerprint && fingerprint !== this.fingerprint) this.key = crypto.randomUUID();
    this.fingerprint = fingerprint;
    try { sessionStorage.setItem('urbansole_checkout', JSON.stringify({ key: this.key, fingerprint })); } catch { /* Retry remains safe in this view. */ }
    this.busy.set(true); this.error.set('');
    this.orders.create({ ...this.form, items, checkout_key: this.key }).subscribe({
      next: order => { try { sessionStorage.removeItem('urbansole_checkout'); } catch {} this.cart.clearCart(); this.cart.toggleCart(false); this.router.navigate(['/orders', order.id]); },
      error: err => { this.busy.set(false); this.error.set(Object.values(err.error?.errors || {}).flat().join(' ') || 'No se pudo confirmar el pedido. Tu carrito se conserva; podés reintentar.'); }
    });
  }
}
