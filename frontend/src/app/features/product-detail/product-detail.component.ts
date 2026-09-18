import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ProductService } from '../../core/services/product.service';
import { CartService } from '../../core/services/cart.service';
import { Product, ProductVariant } from '../../core/models/product.model';

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './product-detail.component.html',
  styleUrl: './product-detail.component.css'
})
export class ProductDetailComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly productService = inject(ProductService);
  private readonly cartService = inject(CartService);

  product = signal<Product | null>(null);
  loading = signal<boolean>(true);
  error = signal<string | null>(null);

  selectedColor = signal<string>('');
  selectedSize = signal<string>('');
  activeImage = signal<string>('');
  addedSuccess = signal<boolean>(false);

  ngOnInit(): void {
    this.route.paramMap.subscribe(params => {
      const idOrSlug = params.get('id');
      if (idOrSlug) {
        this.loadProduct(idOrSlug);
      }
    });
  }

  loadProduct(idOrSlug: string): void {
    this.loading.set(true);
    this.error.set(null);

    this.productService.getProduct(idOrSlug).subscribe({
      next: prod => {
        this.product.set(prod);
        
        // Initial color selection
        if (prod.colors && prod.colors.length > 0) {
          const firstColor = prod.colors[0];
          this.selectedColor.set(firstColor);
          const colorImg = prod.color_images?.[firstColor] || prod.images?.[0] || '';
          this.activeImage.set(colorImg);
        } else {
          this.activeImage.set(prod.images?.[0] || '/images/jordan-chicago.jpg');
        }

        // Initial size selection
        if (prod.available_sizes && prod.available_sizes.length > 0) {
          this.selectedSize.set(prod.available_sizes[0]);
        }

        this.loading.set(false);
      },
      error: () => {
        this.error.set('No se pudo encontrar el calzado.');
        this.loading.set(false);
      }
    });
  }

  selectColor(color: string): void {
    this.selectedColor.set(color);
    const prod = this.product();
    if (prod?.color_images?.[color]) {
      this.activeImage.set(prod.color_images[color]);
    }
  }

  selectSize(size: string): void {
    this.selectedSize.set(size);
  }

  setImage(img: string): void {
    this.activeImage.set(img);
  }

  addToCart(): void {
    const prod = this.product();
    if (!prod) return;

    const size = this.selectedSize() || prod.available_sizes?.[0] || '41';
    const color = this.selectedColor() || prod.available_colors?.[0] || 'Default';

    this.cartService.addItem(prod, size, color);
    this.addedSuccess.set(true);

    setTimeout(() => {
      this.addedSuccess.set(false);
    }, 2500);
  }
}
