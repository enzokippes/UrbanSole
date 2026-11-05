import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { OrderService } from '../../core/services/order.service';
import { Order, Page, ORDER_LABELS } from '../../core/models/order.model';
@Component({ selector: 'app-orders', standalone: true, imports: [CommonModule, RouterLink], templateUrl: './orders.component.html', styleUrl: './orders.component.css' })
export class OrdersComponent {
  private readonly api = inject(OrderService);
  readonly detail = signal<Order | null>(null); readonly page = signal<Page<Order> | null>(null);
  readonly loading = signal(false); readonly error = signal(''); readonly labels = ORDER_LABELS;
  readonly id = inject(ActivatedRoute).snapshot.paramMap.get('id');
  constructor() { this.load(); }
  load(page = 1) {
    this.loading.set(true); this.error.set('');
    if (this.id) this.api.detail(Number(this.id)).subscribe({ next: order => { this.detail.set(order); this.loading.set(false); }, error: () => this.fail() });
    else this.api.list(page).subscribe({ next: result => { this.page.set(result); this.loading.set(false); }, error: () => this.fail() });
  }
  private fail() { this.loading.set(false); this.error.set('No se pudieron cargar los pedidos.'); }
}
