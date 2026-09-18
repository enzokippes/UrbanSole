import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ProductService } from '../../core/services/product.service';
import { Product } from '../../core/models/product.model';
import { ProductCardComponent } from '../catalog/components/product-card/product-card.component';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink, ProductCardComponent],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnInit {
  private readonly productService = inject(ProductService);

  featuredProducts = signal<Product[]>([]);
  loading = signal<boolean>(true);

  readonly categories = [
    { label: 'Hombre', query: 'Hombre', desc: 'Air Jordan 1 & Velocity Pro', count: '4 modelos' },
    { label: 'Mujer', query: 'Mujer', desc: 'Velocity Sport & Smoke Grey', count: '3 modelos' },
    { label: 'Niño', query: 'Niño', desc: 'Air Jordan Heritage Kids', count: '1 modelo' }
  ];

  readonly stats = [
    { num: '8+', label: 'Modelos Exclusivos' },
    { num: '360°', label: 'Rotación Total' },
    { num: '100%', label: 'Calidad Premium' }
  ];

  ngOnInit(): void {
    this.productService.getProducts({ per_page: 8 }).subscribe({
      next: res => {
        this.featuredProducts.set(res.data || []);
        this.loading.set(false);
      },
      error: () => {
        this.featuredProducts.set([]);
        this.loading.set(false);
      }
    });
  }
}
