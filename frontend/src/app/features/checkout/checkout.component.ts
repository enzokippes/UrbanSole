import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { CartService } from '../../core/services/cart.service';
import { AuthService } from '../../core/services/auth.service';
import { OrderService } from '../../core/services/order.service';
import { BankTransferInfo, DeliveryMethod, PaymentMethod } from '../../core/models/order.model';

@Component({
  selector: 'app-checkout',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './checkout.component.html',
  styleUrl: './checkout.component.css'
})
export class CheckoutComponent implements OnInit {
  readonly cart = inject(CartService);
  readonly authService = inject(AuthService);
  private readonly orders = inject(OrderService);
  private readonly router = inject(Router);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly bankInfo = signal<BankTransferInfo | null>(null);
  readonly shipping = 0;

  deliveryMethod: DeliveryMethod = 'standard';
  paymentMethod: PaymentMethod = 'transfer';

  form = {
    recipient: this.authService.currentUser()?.name || '',
    phone: '',
    address: '',
    city: '',
    postal_code: ''
  };

  card = {
    holder: this.authService.currentUser()?.name || '',
    number: '',
    expiry: '',
    cvv: '',
    installments: 1 as 1 | 3 | 6
  };

  private key = crypto.randomUUID();
  private fingerprint = '';

  constructor() {
    try {
      const saved = JSON.parse(sessionStorage.getItem('urbansole_checkout') || 'null');
      if (saved && typeof saved.key === 'string' && typeof saved.fingerprint === 'string') {
        this.key = saved.key;
        this.fingerprint = saved.fingerprint;
      }
    } catch { /* Storage may be unavailable. */ }
  }

  ngOnInit(): void {
    this.orders.paymentInfo().subscribe({
      next: res => this.bankInfo.set(res.bank || null)
    });
  }

  detectedCardBrand(): string {
    const d = this.card.number.replace(/\D/g, '');
    if (d.startsWith('4')) return 'VISA';
    if (/^(5[1-5]|2[2-7])/.test(d)) return 'MASTERCARD';
    if (/^3[47]/.test(d)) return 'AMEX';
    return 'TARJETA';
  }

  maskedCardDisplay(): string {
    const d = this.card.number.replace(/\D/g, '');
    const last4 = d.slice(-4).padStart(4, '•');
    return d.length >= 4 ? `•••• •••• •••• ${last4}` : '•••• •••• •••• ••••';
  }

  formatCardNumber(): void {
    const digits = this.card.number.replace(/\D/g, '').slice(0, 16);
    this.card.number = digits.replace(/(.{4})/g, '$1 ').trim();
  }

  formatCardExpiry(): void {
    const digits = this.card.expiry.replace(/\D/g, '').slice(0, 4);
    this.card.expiry = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
  }

  fillTestCard(): void {
    this.card = {
      holder: (this.form.recipient || this.authService.currentUser()?.name || 'CLIENTE URBANSOLE').toUpperCase(),
      number: '4532 0151 1283 0366',
      expiry: '12/29',
      cvv: '123',
      installments: this.card.installments
    };
  }

  installmentAmount(installments: number): number {
    return this.cart.total() / installments;
  }

  submit() {
    if (this.busy() || !this.cart.items().length) return;
    const items = this.cart.items().map(({ product_id, size, color, quantity }) => ({
      product_id,
      size,
      color,
      quantity
    }));
    const cardPayload = this.paymentMethod === 'card'
      ? {
          card_holder: this.card.holder,
          card_number: this.card.number,
          card_expiry: this.card.expiry,
          card_cvv: this.card.cvv,
          card_installments: this.card.installments
        }
      : {};
    const fingerprint = JSON.stringify({
      ...this.form,
      delivery_method: this.deliveryMethod,
      payment_method: this.paymentMethod,
      items
    });
    if (this.fingerprint && fingerprint !== this.fingerprint) this.key = crypto.randomUUID();
    this.fingerprint = fingerprint;
    try {
      sessionStorage.setItem('urbansole_checkout', JSON.stringify({ key: this.key, fingerprint }));
    } catch { /* Retry remains safe in this view. */ }

    this.busy.set(true);
    this.error.set('');
    this.orders.create({
      ...this.form,
      delivery_method: this.deliveryMethod,
      payment_method: this.paymentMethod,
      ...cardPayload,
      items,
      checkout_key: this.key
    }).subscribe({
      next: order => {
        try { sessionStorage.removeItem('urbansole_checkout'); } catch {}
        this.cart.clearCart();
        this.cart.toggleCart(false);
        this.router.navigate(['/orders', order.id]);
      },
      error: err => {
        this.busy.set(false);
        this.error.set(
          Object.values(err.error?.errors || {}).flat().join(' ') ||
          err?.error?.message ||
          'No se pudo confirmar el pedido. Tu carrito se conserva; podés reintentar.'
        );
      }
    });
  }
}
