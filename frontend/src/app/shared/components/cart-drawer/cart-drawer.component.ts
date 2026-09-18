import { Component, inject } from '@angular/core';
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
    alert('¡Gracias por tu compra en UrbanSole!');
    this.cartService.clearCart();
    this.close();
  }
}
