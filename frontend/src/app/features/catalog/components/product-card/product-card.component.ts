import { Component, Input, inject, signal, OnInit } from '@angular/core';
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
export class ProductCardComponent implements OnInit {
  @Input({ required: true }) product!: Product;
  private readonly cartService = inject(CartService);

  selectedSize = '';
  previewImage = signal<string>('');
  previewColor = signal<string>('');
  addedFeedback = signal<boolean>(false);

  ngOnInit(): void {
    this.previewImage.set(this.product.images?.[0] || '/images/jordan-chicago.jpg');
    if (this.product.colors && this.product.colors.length > 0) {
      this.previewColor.set(this.product.colors[0]);
    }
  }

  get primaryImage(): string {
    return this.previewImage() || this.product.images?.[0] || '/images/jordan-chicago.jpg';
  }

  get discountPercent(): number | null {
    if (this.product.original_price && this.product.original_price > this.product.price) {
      return Math.round(((this.product.original_price - this.product.price) / this.product.original_price) * 100);
    }
    return null;
  }

  selectPreviewColor(color: string, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    this.previewColor.set(color);
    const img = this.product.color_images?.[color];
    if (img) {
      this.previewImage.set(img);
    }
  }

  quickAdd(): void {
    const size = this.selectedSize || this.product.available_sizes?.[0] || '41';
    const color = this.previewColor() || this.product.available_colors?.[0] || this.product.colors?.[0] || 'Default';
    this.cartService.addItem(this.product, size, color);
    this.addedFeedback.set(true);
    setTimeout(() => this.addedFeedback.set(false), 1400);
  }
}
