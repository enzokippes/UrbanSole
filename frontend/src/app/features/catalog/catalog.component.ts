import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ProductService } from '../../core/services/product.service';
import { Product, ProductFilters } from '../../core/models/product.model';
import { ProductCardComponent } from './components/product-card/product-card.component';

@Component({
  selector: 'app-catalog',
  standalone: true,
  imports: [CommonModule, FormsModule, ProductCardComponent],
  templateUrl: './catalog.component.html',
  styleUrl: './catalog.component.css'
})
export class CatalogComponent implements OnInit {
  private readonly productService = inject(ProductService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  products = signal<Product[]>([]);
  loading = signal<boolean>(true);
  sidebarOpen = signal<boolean>(false);

  // Pagination meta
  currentPage = signal<number>(1);
  lastPage = signal<number>(1);
  totalProducts = signal<number>(0);

  // Filters state
  filters: ProductFilters = {
    category: '',
    search: '',
    sizes: [],
    colors: [],
    min_price: '',
    max_price: '',
    sort: 'created_at:desc',
    page: 1,
    per_page: 12
  };

  // Filter options
  readonly categories = ['Hombre', 'Mujer', 'Niño'];
  readonly sizes = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
  readonly colors = ['Blanco', 'Negro', 'Rojo', 'Azul', 'Rosa', 'Beige', 'Verde', 'Gris'];
  readonly sortOptions = [
    { value: 'created_at:desc', label: 'Más recientes' },
    { value: 'price:asc', label: 'Precio: menor a mayor' },
    { value: 'price:desc', label: 'Precio: mayor a menor' },
    { value: 'name:asc', label: 'Nombre A-Z' }
  ];

  // 9 skeletons array for KAN-98
  readonly skeletons = Array(9).fill(0);

  get activeFilterCount(): number {
    let count = 0;
    if (this.filters.category) count++;
    if (this.filters.sizes && this.filters.sizes.length > 0) count += this.filters.sizes.length;
    if (this.filters.colors && this.filters.colors.length > 0) count += this.filters.colors.length;
    if (this.filters.min_price) count++;
    if (this.filters.max_price) count++;
    return count;
  }

  ngOnInit(): void {
    this.route.queryParams.subscribe(params => {
      this.filters.search = params['search'] || '';
      this.filters.category = params['category'] || '';
      if (params['page']) {
        this.filters.page = Number(params['page']);
      }
      this.fetchProducts();
    });
  }

  fetchProducts(): void {
    this.loading.set(true);
    this.productService.getProducts(this.filters).subscribe({
      next: res => {
        this.products.set(res.data || []);
        this.currentPage.set(res.current_page || 1);
        this.lastPage.set(res.last_page || 1);
        this.totalProducts.set(res.total || 0);
        this.loading.set(false);
      },
      error: err => {
        console.error('Error fetching products:', err);
        this.products.set([]);
        this.loading.set(false);
      }
    });
  }

  setCategory(cat: string): void {
    this.filters.category = this.filters.category === cat ? '' : cat;
    this.filters.page = 1;
    this.fetchProducts();
  }

  toggleSize(size: string): void {
    if (!this.filters.sizes) this.filters.sizes = [];
    const index = this.filters.sizes.indexOf(size);
    if (index > -1) {
      this.filters.sizes.splice(index, 1);
    } else {
      this.filters.sizes.push(size);
    }
    this.filters.page = 1;
    this.fetchProducts();
  }

  isSizeSelected(size: string): boolean {
    return !!this.filters.sizes && this.filters.sizes.includes(size);
  }

  toggleColor(color: string): void {
    if (!this.filters.colors) this.filters.colors = [];
    const index = this.filters.colors.indexOf(color);
    if (index > -1) {
      this.filters.colors.splice(index, 1);
    } else {
      this.filters.colors.push(color);
    }
    this.filters.page = 1;
    this.fetchProducts();
  }

  isColorSelected(color: string): boolean {
    return !!this.filters.colors && this.filters.colors.includes(color);
  }

  applyPriceFilter(): void {
    this.filters.page = 1;
    this.fetchProducts();
  }

  onSortChange(): void {
    this.filters.page = 1;
    this.fetchProducts();
  }

  setPage(page: number): void {
    this.filters.page = page;
    this.fetchProducts();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  getPageNumbers(): number[] {
    const pages: number[] = [];
    for (let i = 1; i <= this.lastPage(); i++) {
      pages.push(i);
    }
    return pages;
  }

  clearFilters(): void {
    this.filters = {
      category: '',
      search: '',
      sizes: [],
      colors: [],
      min_price: '',
      max_price: '',
      sort: 'created_at:desc',
      page: 1,
      per_page: 12
    };
    this.router.navigate(['/catalog']);
    this.fetchProducts();
  }

  removeCategory(): void {
    this.filters.category = '';
    this.filters.page = 1;
    this.fetchProducts();
  }

  removeSize(size: string): void {
    this.toggleSize(size);
  }

  removeColor(color: string): void {
    this.toggleColor(color);
  }

  removeMinPrice(): void {
    this.filters.min_price = '';
    this.filters.page = 1;
    this.fetchProducts();
  }

  removeMaxPrice(): void {
    this.filters.max_price = '';
    this.filters.page = 1;
    this.fetchProducts();
  }
}
