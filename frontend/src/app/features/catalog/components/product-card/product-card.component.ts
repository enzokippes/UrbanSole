import { Component, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Product } from '../../../../core/models/product.model';
import { CartService } from '../../../../core/services/cart.service';

@Component({
  selector: 'app-product-card',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './product-card.component.html',
  styleUrl: './product-card.component.css'
})
export class ProductCardComponent {
  @Input({ required: true }) product!: Product;
  private readonly cartService = inject(CartService);

  selectedSize = '';

  get primaryImage(): string {
    return this.product.images?.[0] || '/images/jordan-chicago.jpg';
  }

  quickAdd(): void {
    const size = this.selectedSize || this.product.available_sizes?.[0] || '41';
    const color = this.product.available_colors?.[0] || this.product.colors?.[0] || 'Default';
    this.cartService.addItem(this.product, size, color);
  }
}
