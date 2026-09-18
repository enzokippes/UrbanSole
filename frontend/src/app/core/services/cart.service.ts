import { Injectable, signal, computed } from '@angular/core';
import { CartItem } from '../models/cart.model';
import { Product } from '../models/product.model';

@Injectable({
  providedIn: 'root'
})
export class CartService {
  readonly items = signal<CartItem[]>(this.loadStoredCart());
  readonly isOpen = signal<boolean>(false);

  readonly count = computed(() => {
    return this.items().reduce((sum, item) => sum + item.quantity, 0);
  });

  readonly total = computed(() => {
    return this.items().reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0);
  });

  addItem(product: Product, size: string, color: string, quantity = 1): void {
    const current = this.items();
    const existingIndex = current.findIndex(
      item => item.product_id === product.id && item.size === size && item.color === color
    );

    let updated: CartItem[];
    if (existingIndex > -1) {
      updated = current.map((item, idx) =>
        idx === existingIndex ? { ...item, quantity: item.quantity + quantity } : item
      );
    } else {
      updated = [
        ...current,
        {
          product_id: product.id,
          product,
          size,
          color,
          quantity,
          price: Number(product.price)
        }
      ];
    }

    this.items.set(updated);
    this.saveCart(updated);
    this.isOpen.set(true);
  }

  removeItem(index: number): void {
    const updated = this.items().filter((_, i) => i !== index);
    this.items.set(updated);
    this.saveCart(updated);
  }

  updateQuantity(index: number, quantity: number): void {
    if (quantity <= 0) {
      this.removeItem(index);
      return;
    }
    const updated = this.items().map((item, i) =>
      i === index ? { ...item, quantity } : item
    );
    this.items.set(updated);
    this.saveCart(updated);
  }

  clearCart(): void {
    this.items.set([]);
    localStorage.removeItem('urbansole_cart');
  }

  toggleCart(open?: boolean): void {
    if (open !== undefined) {
      this.isOpen.set(open);
    } else {
      this.isOpen.update(prev => !prev);
    }
  }

  private saveCart(items: CartItem[]): void {
    try {
      localStorage.setItem('urbansole_cart', JSON.stringify(items));
    } catch {
      // LocalStorage error handled safely
    }
  }

  private loadStoredCart(): CartItem[] {
    try {
      const data = localStorage.getItem('urbansole_cart');
      return data ? JSON.parse(data) : [];
    } catch {
      return [];
    }
  }
}
