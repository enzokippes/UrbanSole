import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BankTransferInfo, Checkout, Order, Page } from '../models/order.model';

@Injectable({ providedIn: 'root' })
export class OrderService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';
  private readonly url = `${this.apiUrl}/orders`;

  paymentInfo() {
    return this.http.get<{ bank: BankTransferInfo }>(`${this.apiUrl}/checkout/payment-info`);
  }

  create(data: Checkout) {
    return this.http.post<Order>(this.url, data);
  }

  list(page = 1) {
    return this.http.get<Page<Order>>(this.url, { params: { page } });
  }

  detail(id: number) {
    return this.http.get<Order>(`${this.url}/${id}`);
  }

  receipt(id: number) {
    return this.http.get(`${this.url}/${id}/receipt`, { responseType: 'blob' });
  }

  cancel(id: number) {
    return this.http.post<{ message: string; order: Order }>(`${this.url}/${id}/cancel`, {});
  }

  uploadPaymentProof(orderId: number, file: File, reference?: string) {
    const headers: Record<string, string> = {
      'Content-Type': 'application/octet-stream',
      'X-File-Name': encodeURIComponent(file.name)
    };
    if (reference?.trim()) {
      headers['X-Payment-Reference'] = encodeURIComponent(reference.trim());
    }
    return this.http.post<{ message: string; order: Order }>(`${this.url}/${orderId}/payment-proof`, file, { headers });
  }

  downloadPaymentProof(orderId: number) {
    return this.http.get(`${this.url}/${orderId}/payment-proof`, { responseType: 'blob' });
  }
}
