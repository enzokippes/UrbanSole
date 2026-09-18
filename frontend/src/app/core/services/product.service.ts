import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PaginatedProducts, Product, ProductFilters } from '../models/product.model';

@Injectable({
  providedIn: 'root'
})
export class ProductService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  getProducts(filters: ProductFilters = {}): Observable<PaginatedProducts> {
    let params = new HttpParams();

    if (filters.search) {
      params = params.set('search', filters.search);
    }
    if (filters.category) {
      params = params.set('category', filters.category);
    }
    if (filters.sizes && filters.sizes.length > 0) {
      params = params.set('size', filters.sizes.join(','));
    }
    if (filters.colors && filters.colors.length > 0) {
      params = params.set('color', filters.colors.join(','));
    }
    if (filters.min_price !== undefined && filters.min_price !== '') {
      params = params.set('min_price', filters.min_price.toString());
    }
    if (filters.max_price !== undefined && filters.max_price !== '') {
      params = params.set('max_price', filters.max_price.toString());
    }
    if (filters.sort) {
      const [sortBy, sortDir] = filters.sort.split(':');
      if (sortBy) params = params.set('sort_by', sortBy);
      if (sortDir) params = params.set('sort_dir', sortDir);
    }
    if (filters.page) {
      params = params.set('page', filters.page.toString());
    }
    if (filters.per_page) {
      params = params.set('per_page', filters.per_page.toString());
    }

    return this.http.get<PaginatedProducts>(`${this.apiUrl}/products`, { params });
  }

  getProduct(idOrSlug: string | number): Observable<Product> {
    return this.http.get<Product>(`${this.apiUrl}/products/${idOrSlug}`);
  }

  getCategories(): Observable<string[]> {
    return this.http.get<string[]>(`${this.apiUrl}/categories`);
  }
}
