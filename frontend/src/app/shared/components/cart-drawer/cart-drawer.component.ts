import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { CommonModule } from '@angular/common';
import { CartService } from '../../../core/services/cart.service';

@Component({
  selector: 'app-cart-drawer',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './cart-drawer.component.html',
  styleUrl: './cart-drawer.component.css'
})
export class CartDrawerComponent {
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);
  readonly cartService = inject(CartService);

  close(): void {
    this.cartService.toggleCart(false);
  }

  increment(index: number, currentQty: number): void {
    this.cartService.updateQuantity(index, currentQty + 1);
  }

  decrement(index: number, currentQty: number): void {
    this.cartService.updateQuantity(index, currentQty - 1);
  }

  remove(index: number): void {
    this.cartService.removeItem(index);
  }

  checkout(): void {
    this.router.navigate([this.auth.isAuthenticated() ? '/checkout' : '/login'], { queryParams: this.auth.isAuthenticated() ? {} : { returnUrl: '/checkout' } });
    this.close();
  }
}
