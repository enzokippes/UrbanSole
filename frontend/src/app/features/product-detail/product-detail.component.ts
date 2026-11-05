import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ProductService } from '../../core/services/product.service';
import { CartService } from '../../core/services/cart.service';
import { Product } from '../../core/models/product.model';
import { ModelViewer3dComponent } from '../../shared/components/model-viewer-3d/model-viewer-3d.component';
import { ProductCardComponent } from '../catalog/components/product-card/product-card.component';

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [CommonModule, RouterLink, ModelViewer3dComponent, ProductCardComponent],
  templateUrl: './product-detail.component.html',
  styleUrl: './product-detail.component.css'
})
export class ProductDetailComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly productService = inject(ProductService);
  private readonly cartService = inject(CartService);

  product = signal<Product | null>(null);
  relatedProducts = signal<Product[]>([]);
  loading = signal<boolean>(true);
  error = signal<string | null>(null);

  viewMode = signal<'3d' | 'image'>('3d');
  selectedColor = signal<string>('');
  selectedSize = signal<string>('');
  activeImage = signal<string>('');
  activeModel3d = signal<string>('');
  addedSuccess = signal<boolean>(false);
  sizeGuideOpen = signal<boolean>(false);

  readonly selectedVariantStock = computed(() => {
    const prod = this.product();
    const color = this.selectedColor();
    const size = this.selectedSize();
    if (!prod || !prod.variants) return null;
    const match = prod.variants.find(v => v.color === color && v.size === size);
    return match ? match.stock : null;
  });

  readonly discountPercent = computed(() => {
    const prod = this.product();
    if (prod?.original_price && prod.original_price > prod.price) {
      return Math.round(((prod.original_price - prod.price) / prod.original_price) * 100);
    }
    return null;
  });

  ngOnInit(): void {
    this.route.queryParamMap.subscribe(qParams => {
      const view = qParams.get('view');
      if (view === 'image') {
        this.viewMode.set('image');
      } else if (view === '3d') {
        this.viewMode.set('3d');
      }
    });

    this.route.paramMap.subscribe(params => {
      const idOrSlug = params.get('id');
      if (idOrSlug) {
        this.loadProduct(idOrSlug);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  loadProduct(idOrSlug: string): void {
    this.loading.set(true);
    this.error.set(null);

    this.productService.getProduct(idOrSlug).subscribe({
      next: prod => {
        this.product.set(prod);

        if (prod.colors && prod.colors.length > 0) {
          const firstColor = prod.colors[0];
          this.selectedColor.set(firstColor);
          const colorImg = prod.color_images?.[firstColor] || prod.images?.[0] || '/images/jordan-chicago.jpg';
          this.activeImage.set(colorImg);
          const colorModel = prod.color_models?.[firstColor] || prod.model_3d_url || '/models/jordan-chicago.glb';
          this.activeModel3d.set(colorModel);
        } else {
          this.activeImage.set(prod.images?.[0] || '/images/jordan-chicago.jpg');
          this.activeModel3d.set(prod.model_3d_url || '/models/jordan-chicago.glb');
        }

        if (!prod.model_3d_url) {
          this.viewMode.set('image');
        } else if (!this.route.snapshot.queryParamMap.get('view')) {
          this.viewMode.set('3d');
        }

        if (prod.available_sizes && prod.available_sizes.length > 0) {
          this.selectedSize.set(prod.available_sizes[0]);
        }

        this.loading.set(false);
        this.loadRelated(prod.category, prod.id);
      },
      error: () => {
        this.error.set('No se pudo encontrar el calzado solicitado.');
        this.loading.set(false);
      }
    });
  }

  loadRelated(category: string, currentId: number): void {
    this.productService.getProducts({ category, per_page: 4 }).subscribe({
      next: res => {
        const filtered = (res.data || []).filter(p => p.id !== currentId).slice(0, 4);
        this.relatedProducts.set(filtered);
      }
    });
  }

  selectColor(color: string): void {
    this.selectedColor.set(color);
    const prod = this.product();
    if (prod?.color_images?.[color]) {
      this.activeImage.set(prod.color_images[color]);
    }
    if (prod?.color_models?.[color]) {
      this.activeModel3d.set(prod.color_models[color]);
    } else if (prod?.model_3d_url) {
      this.activeModel3d.set(prod.model_3d_url);
    }
  }

  selectSize(size: string): void {
    this.selectedSize.set(size);
  }

  setImage(img: string): void {
    this.activeImage.set(img);
    const prod = this.product();
    if (prod?.color_images) {
      const matchingColor = Object.keys(prod.color_images).find(c => prod.color_images![c] === img);
      if (matchingColor) {
        this.selectedColor.set(matchingColor);
        if (prod.color_models?.[matchingColor]) {
          this.activeModel3d.set(prod.color_models[matchingColor]);
        }
      }
    }
  }

  toggleViewMode(mode: '3d' | 'image'): void {
    this.viewMode.set(mode);
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
