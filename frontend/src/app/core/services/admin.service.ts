import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Order, OrderStatus, Page } from '../models/order.model';
import { AdminStats, Product } from '../models/product.model';
import { User } from '../models/user.model';
export type Stats = AdminStats;
export interface AdminUserOrdersResponse { user: User; orders: Page<Order>; }
export interface MediaImageOption { url: string; label: string; source: string; }
export interface UploadAssetResponse { url: string; path: string; type: 'image' | 'model'; name: string; message: string; }
@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly url = 'http://localhost:8000/api/admin';
  stats() { return this.http.get<Stats>(`${this.url}/stats`); }
  products(page = 1) { return this.http.get<Page<Product>>(`${this.url}/products`, { params: { page } }); }
  save(product: Partial<Product>) { return product.id ? this.http.put<Product>(`${this.url}/products/${product.id}`, product) : this.http.post<Product>(`${this.url}/products`, product); }
  remove(id: number) { return this.http.delete(`${this.url}/products/${id}`); }
  uploadAsset(file: File, type: 'image' | 'model') {
    return this.http.post<UploadAssetResponse>(`${this.url}/upload`, file, {
      headers: {
        'Content-Type': 'application/octet-stream',
        'X-File-Name': encodeURIComponent(file.name),
        'X-Asset-Type': type
      }
    });
  }
  mediaLibrary() { return this.http.get<{ images: MediaImageOption[] }>(`${this.url}/media-library`); }
  orders(page = 1) { return this.http.get<Page<Order>>(`${this.url}/orders`, { params: { page } }); }
  orderReceipt(id: number) { return this.http.get(`${this.url}/orders/${id}/receipt`, { responseType: 'blob' }); }
  orderPaymentProof(id: number) { return this.http.get(`${this.url}/orders/${id}/payment-proof`, { responseType: 'blob' }); }
  verifyOrderPayment(id: number, action: 'approve' | 'reject', reason?: string) {
    return this.http.patch<{ order: Order; message: string }>(`${this.url}/orders/${id}/payment`, { action, reason });
  }
  status(id: number, status: OrderStatus) { return this.http.patch<Order>(`${this.url}/orders/${id}`, { status }); }
  users(filters: { search?: string; role?: string; status?: string; page?: number } = {}) {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.role) params = params.set('role', filters.role);
    if (filters.status) params = params.set('status', filters.status);
    if (filters.page) params = params.set('page', filters.page);
    return this.http.get<Page<User>>(`${this.url}/users`, { params });
  }
  userOrders(userId: number, page = 1) {
    return this.http.get<AdminUserOrdersResponse>(`${this.url}/users/${userId}/orders`, { params: { page } });
  }
  updateUserRole(userId: number, role: 'customer' | 'admin') {
    return this.http.patch<{ user: User; message: string }>(`${this.url}/users/${userId}/role`, { role });
  }
  toggleUserBan(userId: number, banned: boolean, reason?: string) {
    return this.http.patch<{ user: User; message: string }>(`${this.url}/users/${userId}/ban`, { banned, reason });
  }
  deleteUser(userId: number) {
    return this.http.delete<{ deleted_user_id: number; preserved_orders_count: number; message: string }>(`${this.url}/users/${userId}`);
  }
  restockVariant(variantId: number, addStock: number) {
    return this.http.patch<{ variant: any; new_stock: number; message: string }>(`${this.url}/variants/${variantId}/stock`, { add_stock: addStock });
  }
}
