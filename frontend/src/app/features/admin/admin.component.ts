import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { AdminService, Stats } from '../../core/services/admin.service';
import { Product } from '../../core/models/product.model';
import { Order, OrderStatus, Page, ORDER_LABELS } from '../../core/models/order.model';
@Component({ selector: 'app-admin', standalone: true, imports: [CommonModule, FormsModule], templateUrl: './admin.component.html', styleUrl: './admin.component.css' })
export class AdminComponent {
  private readonly api = inject(AdminService);
  readonly stats = signal<Stats | null>(null); readonly products = signal<Page<Product> | null>(null); readonly orders = signal<Page<Order> | null>(null);
  readonly busy = signal(false); readonly error = signal(''); readonly notice = signal(''); readonly labels = ORDER_LABELS;
  tab: 'stats' | 'products' | 'orders' = 'stats';
  editor: Partial<Product> | null = null;
  constructor() { this.load(); }
  load(productPage = this.products()?.current_page || 1, orderPage = this.orders()?.current_page || 1) {
    this.busy.set(true); this.error.set('');
    forkJoin({ stats: this.api.stats(), products: this.api.products(productPage), orders: this.api.orders(orderPage) }).subscribe({
      next: data => { this.stats.set(data.stats); this.products.set(data.products); this.orders.set(data.orders); this.busy.set(false); }, error: err => this.fail(err)
    });
  }
  edit(product?: Product) {
    this.notice.set(''); this.error.set('');
    this.editor = product ? structuredClone(product) : { name: '', brand: 'Nike', description: '', category: 'Hombre', price: 1, featured: false, active: true, images: [], variants: [] };
  }
  addVariant() { this.editor?.variants?.push({ id: 0, product_id: this.editor.id || 0, size: '', color: '', stock: 0 }); }
  save() {
    if (!this.editor || this.busy()) return;
    this.busy.set(true); this.error.set('');
    const payload = { ...this.editor, variants: this.editor.variants?.map(v => ({ ...v, id: v.id || undefined })) } as Partial<Product>;
    this.api.save(payload).subscribe({ next: () => { this.editor = null; this.notice.set('Producto guardado.'); this.load(); }, error: err => this.fail(err) });
  }
  remove(product: Product) {
    if (this.busy() || !confirm(`¿Desactivar ${product.name}? Se conservará el historial.`)) return;
    this.busy.set(true); this.api.remove(product.id).subscribe({ next: () => { this.notice.set('Producto desactivado.'); this.load(); }, error: err => this.fail(err) });
  }
  transitions(order: Order): OrderStatus[] {
    const map: Record<OrderStatus, OrderStatus[]> = { pending: ['confirmed', 'cancelled'], confirmed: ['shipped', 'cancelled'], shipped: ['delivered'], delivered: [], cancelled: [] };
    return map[order.status];
  }
  change(order: Order, status: OrderStatus) {
    if (this.busy() || !confirm(`¿Cambiar pedido #${order.id} a ${this.labels[status]}?`)) return;
    this.busy.set(true); this.api.status(order.id, status).subscribe({ next: () => { this.notice.set('Estado actualizado.'); this.load(); }, error: err => this.fail(err) });
  }
  private fail(err: any) { this.busy.set(false); this.error.set(Object.values(err.error?.errors || {}).flat().join(' ') || 'No se pudo completar la operación.'); }
}
