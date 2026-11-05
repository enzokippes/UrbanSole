import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Checkout, Order, Page } from '../models/order.model';
@Injectable({ providedIn: 'root' })
export class OrderService {
  private readonly http = inject(HttpClient);
  private readonly url = 'http://localhost:8000/api/orders';
  create(data: Checkout) { return this.http.post<Order>(this.url, data); }
  list(page = 1) { return this.http.get<Page<Order>>(this.url, { params: { page } }); }
  detail(id: number) { return this.http.get<Order>(`${this.url}/${id}`); }
}
