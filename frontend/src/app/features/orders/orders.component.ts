import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { OrderService } from '../../core/services/order.service';
import {
  BankTransferInfo,
  DELIVERY_METHOD_LABELS,
  ORDER_LABELS,
  Order,
  PAYMENT_METHOD_LABELS,
  PAYMENT_STATUS_LABELS,
  Page
} from '../../core/models/order.model';

@Component({
  selector: 'app-orders',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './orders.component.html',
  styleUrl: './orders.component.css'
})
export class OrdersComponent implements OnInit {
  private readonly api = inject(OrderService);
  readonly detail = signal<Order | null>(null);
  readonly page = signal<Page<Order> | null>(null);
  readonly bankInfo = signal<BankTransferInfo | null>(null);
  readonly loading = signal(false);
  readonly downloadingId = signal<number | null>(null);
  readonly cancellingId = signal<number | null>(null);
  readonly uploadingProofId = signal<number | null>(null);
  readonly viewingProofId = signal<number | null>(null);
  readonly copiedAlias = signal(false);
  readonly feedback = signal('');
  readonly error = signal('');
  paymentReference = '';

  readonly labels = ORDER_LABELS;
  readonly paymentMethodLabels = PAYMENT_METHOD_LABELS;
  readonly paymentStatusLabels = PAYMENT_STATUS_LABELS;
  readonly deliveryMethodLabels = DELIVERY_METHOD_LABELS;
  readonly id = inject(ActivatedRoute).snapshot.paramMap.get('id');

  ngOnInit(): void {
    this.load();
    this.api.paymentInfo().subscribe({
      next: res => this.bankInfo.set(res.bank || null)
    });
  }

  load(page = 1) {
    this.loading.set(true);
    this.error.set('');
    if (this.id) {
      this.api.detail(Number(this.id)).subscribe({
        next: order => {
          this.detail.set(order);
          this.loading.set(false);
        },
        error: () => this.fail()
      });
    } else {
      this.api.list(page).subscribe({
        next: result => {
          this.page.set(result);
          this.loading.set(false);
        },
        error: () => this.fail()
      });
    }
  }

  copyText(text: string): void {
    navigator.clipboard?.writeText(text).then(() => {
      this.copiedAlias.set(true);
      setTimeout(() => this.copiedAlias.set(false), 1800);
    }).catch(() => {});
  }

  onProofFileSelected(order: Order, event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.uploadingProofId.set(order.id);
    this.error.set('');
    this.feedback.set('');

    this.api.uploadPaymentProof(order.id, file, this.paymentReference).subscribe({
      next: res => {
        this.uploadingProofId.set(null);
        this.paymentReference = '';
        input.value = '';
        this.applyUpdatedOrder(res.order);
        this.feedback.set(res.message);
      },
      error: err => {
        this.uploadingProofId.set(null);
        input.value = '';
        this.error.set(err?.error?.message || 'No se pudo subir el comprobante.');
      }
    });
  }

  viewProof(orderId: number): void {
    this.viewingProofId.set(orderId);
    this.api.downloadPaymentProof(orderId).subscribe({
      next: blob => {
        this.viewingProofId.set(null);
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      },
      error: () => {
        this.viewingProofId.set(null);
        this.error.set('No se pudo abrir el comprobante.');
      }
    });
  }

  downloadReceipt(orderId: number, displayNumber?: number) {
    this.downloadingId.set(orderId);
    const num = displayNumber || orderId;
    this.api.receipt(orderId).subscribe({
      next: blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `recibo-urbansole-pedido-${num}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        this.downloadingId.set(null);
      },
      error: () => {
        this.downloadingId.set(null);
        this.error.set('No se pudo descargar el recibo en PDF.');
      }
    });
  }

  cancelOrder(order: Order) {
    if (order.status !== 'pending' || this.cancellingId()) return;

    this.cancellingId.set(order.id);
    this.error.set('');
    this.feedback.set('');
    const num = order.user_order_number || order.id;

    this.api.cancel(order.id).subscribe({
      next: res => {
        this.cancellingId.set(null);
        this.applyUpdatedOrder(res.order);
        this.feedback.set(`Pedido #${num} cancelado correctamente. El stock fue devuelto.`);
      },
      error: err => {
        this.cancellingId.set(null);
        this.error.set(err?.error?.message || 'No se pudo cancelar el pedido.');
      }
    });
  }

  private applyUpdatedOrder(updated: Order): void {
    if (this.detail()?.id === updated.id) {
      this.detail.set(updated);
    }
    const currentPage = this.page();
    if (currentPage) {
      this.page.set({
        ...currentPage,
        data: currentPage.data.map(o => (o.id === updated.id ? updated : o))
      });
    }
  }

  private fail() {
    this.loading.set(false);
    this.error.set('No se pudieron cargar los pedidos.');
  }
}
