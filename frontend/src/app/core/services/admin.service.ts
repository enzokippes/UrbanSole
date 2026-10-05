import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Order, OrderStatus, Page } from '../models/order.model';
import { Product } from '../models/product.model';
export interface Stats { total_users: number; total_products: number; total_orders: number; pending_orders: number; sales_total: number | string; average_order: number | string; low_stock_variants: number; orders_by_status: { status: OrderStatus; count: number }[]; }
@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly url = 'http://localhost:8000/api/admin';
  stats() { return this.http.get<Stats>(`${this.url}/stats`); }
  products(page = 1) { return this.http.get<Page<Product>>(`${this.url}/products`, { params: { page } }); }
  save(product: Partial<Product>) { return product.id ? this.http.put<Product>(`${this.url}/products/${product.id}`, product) : this.http.post<Product>(`${this.url}/products`, product); }
  remove(id: number) { return this.http.delete(`${this.url}/products/${id}`); }
  orders(page = 1) { return this.http.get<Page<Order>>(`${this.url}/orders`, { params: { page } }); }
  status(id: number, status: OrderStatus) { return this.http.patch<Order>(`${this.url}/orders/${id}`, { status }); }
}
