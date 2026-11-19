import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ProductService } from '../../core/services/product.service';
import { AdminService, MediaImageOption } from '../../core/services/admin.service';
import { AuthService } from '../../core/services/auth.service';
import { AdminStats, Model3dOption, Product } from '../../core/models/product.model';
import {
  DELIVERY_METHOD_LABELS,
  ORDER_LABELS,
  Order,
  OrderStatus,
  PAYMENT_METHOD_LABELS,
  PAYMENT_STATUS_LABELS
} from '../../core/models/order.model';
import { User } from '../../core/models/user.model';
import { ModelViewer3dComponent } from '../../shared/components/model-viewer-3d/model-viewer-3d.component';

@Component({
  selector: 'app-admin',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterLink, ModelViewer3dComponent],
  template: `
    <div class="admin-page">
      <div class="container py-10">
        <!-- Header -->
        <div class="admin-header">
          <div>
            <span class="admin-tag">UrbanSole · Administración</span>
            <h1 class="admin-title">Panel de Administración</h1>
          </div>

          <div class="admin-top-actions">
            <a href="http://localhost:8000/db-viewer" target="_blank" rel="noopener" class="btn-secondary">
              Base de Datos ↗
            </a>
            <a href="http://localhost:8000/api/docs" target="_blank" rel="noopener" class="btn-secondary">
              Documentación API ↗
            </a>
            <button type="button" (click)="openCreateModal()" class="btn-primary">
              + Crear Nueva Zapatilla
            </button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="admin-tabs">
          <button
            type="button"
            (click)="activeTab.set('dashboard')"
            class="admin-tab"
            [class.active]="activeTab() === 'dashboard'"
          >
            Dashboard & Métricas
          </button>
          <button
            type="button"
            (click)="activeTab.set('products')"
            class="admin-tab"
            [class.active]="activeTab() === 'products'"
          >
            Productos ({{ products().length }})
          </button>
          <button
            type="button"
            (click)="activeTab.set('orders')"
            class="admin-tab"
            [class.active]="activeTab() === 'orders'"
          >
            Pedidos ({{ orders().length }})
          </button>
          <button
            type="button"
            (click)="activeTab.set('users')"
            class="admin-tab"
            [class.active]="activeTab() === 'users'"
          >
            Usuarios ({{ usersTotal() }})
          </button>
          <button
            type="button"
            (click)="activeTab.set('notifications')"
            class="admin-tab"
            [class.active]="activeTab() === 'notifications'"
          >
            Notificaciones
          </button>
        </div>

        <!-- TAB 1: DASHBOARD -->
        <div *ngIf="activeTab() === 'dashboard' && stats()" class="dashboard-grid">
          <!-- KPI Row -->
          <div class="stat-card stat-card-accent">
            <span class="stat-card-label">Facturación Confirmada</span>
            <span class="stat-card-val">\${{ asNumber(stats()?.sales_total) | number:'1.2-2' }}</span>
            <span class="stat-card-sub">
              Ticket promedio: \${{ asNumber(stats()?.average_order) | number:'1.2-2' }}
              <ng-container *ngIf="asNumber(stats()?.pending_revenue) > 0">
                · \${{ asNumber(stats()?.pending_revenue) | number:'1.2-2' }} pendientes
              </ng-container>
            </span>
          </div>
          <div class="stat-card">
            <span class="stat-card-label">Pedidos & Ventas</span>
            <span class="stat-card-val">{{ stats()?.total_orders || 0 }}</span>
            <span class="stat-card-sub">
              {{ stats()?.units_sold || 0 }} pares vendidos · {{ stats()?.pending_orders || 0 }} pendientes
            </span>
          </div>
          <div class="stat-card">
            <span class="stat-card-label">Catálogo & Modelos 3D</span>
            <span class="stat-card-val">{{ stats()?.total_products }}</span>
            <span class="stat-card-sub">
              {{ stats()?.active_products }} activas · {{ stats()?.models_3d_count }} modelos .GLB
            </span>
          </div>
          <div class="stat-card">
            <span class="stat-card-label">Stock Total & Clientes</span>
            <span class="stat-card-val">{{ stats()?.total_stock }}</span>
            <span class="stat-card-sub" [class.stat-warn]="(stats()?.low_stock_variants || 0) > 0">
              {{ stats()?.low_stock_variants || 0 }} variantes stock ≤ 3 · {{ stats()?.customers_count ?? stats()?.total_users }} clientes
            </span>
          </div>

          <!-- Analytics Row: Top Selling Models + Order Status & Top Sizes -->
          <div class="analytics-two-col">
            <section class="panel-card">
              <div class="panel-card-head">
                <div>
                  <h3>Modelos Más Vendidos</h3>
                  <p class="panel-card-sub">Ranking por pares vendidos (excluye pedidos cancelados)</p>
                </div>
                <span class="panel-badge">Top {{ stats()?.top_selling_products?.length || 0 }}</span>
              </div>

              <div *ngIf="!stats()?.top_selling_products?.length" class="panel-empty">
                Todavía no hay ventas registradas. Cuando se generen pedidos, acá verás el ranking de modelos más vendidos.
              </div>

              <div *ngIf="stats()?.top_selling_products?.length" class="top-sellers-list">
                <div *ngFor="let item of stats()?.top_selling_products; let i = index" class="top-seller-row">
                  <span class="rank-badge" [class.rank-top]="i === 0">#{{ i + 1 }}</span>
                  <img [src]="item.image || '/images/jordan-chicago.jpg'" alt="" class="mini-thumb" />
                  <div class="top-seller-info">
                    <div class="top-seller-top">
                      <span class="top-seller-name">{{ item.product_name }}</span>
                      <strong class="top-seller-rev">\${{ item.revenue | number:'1.2-2' }}</strong>
                    </div>
                    <div class="top-seller-meta">
                      <span>{{ item.brand || 'UrbanSole' }}<ng-container *ngIf="item.category"> · {{ item.category }}</ng-container></span>
                      <span>{{ item.units_sold }} {{ item.units_sold === 1 ? 'par' : 'pares' }} · {{ item.orders_count }} {{ item.orders_count === 1 ? 'pedido' : 'pedidos' }}</span>
                    </div>
                    <div class="progress-track">
                      <div class="progress-fill" [style.width.%]="getTopSellerWidth(item.units_sold)"></div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section class="panel-card">
              <div class="panel-card-head">
                <div>
                  <h3>Estado de Pedidos & Talles</h3>
                  <p class="panel-card-sub">Seguimiento rápido de órdenes y talles más demandados</p>
                </div>
                <button type="button" (click)="activeTab.set('orders')" class="panel-link-btn">
                  Ver pedidos →
                </button>
              </div>

              <div class="status-grid">
                <div *ngFor="let st of orderStatuses" class="status-metric-box" [attr.data-status]="st">
                  <span class="status-metric-label">{{ orderLabels[st] }}</span>
                  <strong class="status-metric-val">{{ getOrderStatusCount(st) }}</strong>
                </div>
              </div>

              <div class="sizes-block">
                <h4>Talles más pedidos</h4>
                <div *ngIf="!stats()?.top_sizes?.length" class="panel-empty compact">
                  Sin datos de talles vendidos todavía.
                </div>
                <div *ngIf="stats()?.top_sizes?.length" class="size-chips">
                  <span *ngFor="let s of stats()?.top_sizes" class="size-chip">
                    Talle {{ s.size }} <strong>{{ s.units_sold }}</strong>
                  </span>
                </div>
              </div>
            </section>
          </div>

          <!-- Category Breakdown + Sales per Category -->
          <section class="cat-breakdown-card">
            <div class="panel-card-head">
              <div>
                <h3>Distribución y Ventas por Categoría</h3>
                <p class="panel-card-sub">Modelos publicados, unidades vendidas e ingresos por segmento</p>
              </div>
            </div>
            <div class="cat-bars">
              <div *ngFor="let cat of categories" class="cat-bar-item">
                <div class="cat-bar-head">
                  <span class="cat-bar-name">{{ cat }}</span>
                  <strong>{{ stats()?.by_category?.[cat] || 0 }} modelos</strong>
                </div>
                <div class="cat-bar-sales">
                  <span>{{ stats()?.sales_by_category?.[cat]?.units_sold || 0 }} pares vendidos</span>
                  <span class="cat-bar-rev">\${{ (stats()?.sales_by_category?.[cat]?.revenue || 0) | number:'1.2-2' }}</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Operational Row: Recent Orders + Low Stock Alerts -->
          <div class="analytics-two-col">
            <section class="panel-card">
              <div class="panel-card-head">
                <div>
                  <h3>Últimos Pedidos</h3>
                  <p class="panel-card-sub">Actividad más reciente de la tienda</p>
                </div>
                <button type="button" (click)="activeTab.set('orders')" class="panel-link-btn">
                  Gestionar →
                </button>
              </div>

              <div *ngIf="!stats()?.recent_orders?.length" class="panel-empty">
                No hay pedidos recientes registrados.
              </div>

              <div *ngIf="stats()?.recent_orders?.length" class="compact-list">
                <div *ngFor="let o of stats()?.recent_orders" class="compact-row">
                  <div class="compact-row-main">
                    <div class="compact-row-title">
                      <strong>Pedido #{{ o.user_order_number || o.id }}</strong>
                      <span class="order-status-pill" [attr.data-status]="o.status">
                        {{ orderLabels[asOrderStatus(o.status)] || o.status }}
                      </span>
                    </div>
                    <span class="compact-row-sub">
                      {{ o.customer_name }}<ng-container *ngIf="o.user_deleted"> (Cuenta eliminada)</ng-container> · {{ o.created_at | date:'dd/MM HH:mm' }}
                    </span>
                    <span *ngIf="o.items_summary?.length" class="compact-row-sub">
                      {{ o.items_summary?.join(' · ') }}
                    </span>
                  </div>
                  <strong class="compact-row-val">\${{ o.total | number:'1.2-2' }}</strong>
                </div>
              </div>
            </section>

            <section class="panel-card">
              <div class="panel-card-head">
                <div>
                  <h3>Alertas de Stock Bajo</h3>
                  <p class="panel-card-sub">Variantes activas con 3 unidades o menos</p>
                </div>
                <button type="button" (click)="activeTab.set('products')" class="panel-link-btn">
                  Ir al catálogo →
                </button>
              </div>

              <div *ngIf="restockFeedback()" class="user-feedback-banner" role="status">
                {{ restockFeedback() }}
              </div>

              <div *ngIf="!stats()?.low_stock_items?.length" class="panel-empty healthy">
                ✓ Todo el inventario activo tiene stock saludable (más de 3 unidades por variante).
              </div>

              <div *ngIf="stats()?.low_stock_items?.length" class="compact-list">
                <div *ngFor="let item of stats()?.low_stock_items" class="compact-row">
                  <div class="low-stock-left">
                    <img [src]="item.image || '/images/jordan-chicago.jpg'" alt="" class="mini-thumb" />
                    <div class="compact-row-main">
                      <span class="compact-row-title"><strong>{{ item.product_name }}</strong></span>
                      <span class="compact-row-sub">Talle {{ item.size }} · {{ item.color }}<ng-container *ngIf="item.category"> · {{ item.category }}</ng-container></span>
                    </div>
                  </div>
                  <div class="row-actions">
                    <span class="stock-alert-pill" [class.out]="item.stock === 0">
                      {{ item.stock === 0 ? 'Sin stock' : 'Quedan ' + item.stock }}
                    </span>
                    <button
                      type="button"
                      (click)="quickRestock(item.variant_id, 5)"
                      [disabled]="restockingVariantId() === item.variant_id"
                      class="action-btn reactivate"
                      title="Sumar 5 pares al stock"
                    >
                      +5
                    </button>
                    <button
                      type="button"
                      (click)="quickRestock(item.variant_id, 10)"
                      [disabled]="restockingVariantId() === item.variant_id"
                      class="action-btn reactivate"
                      title="Sumar 10 pares al stock"
                    >
                      +10
                    </button>
                  </div>
                </div>
              </div>
            </section>
          </div>
        </div>

        <!-- TAB 2: CRUD DE PRODUCTOS -->
        <div *ngIf="activeTab() === 'products'" class="users-section">
          <div class="users-toolbar">
            <div class="users-search-box">
              <label for="adminProductSearch" class="sr-only">Buscar zapatilla por nombre o marca</label>
              <input
                id="adminProductSearch"
                type="search"
                [(ngModel)]="productSearch"
                placeholder="Buscar zapatilla por nombre o marca..."
                class="input users-search-input"
              />
            </div>

            <div class="users-filter-pills">
              <button type="button" (click)="productCategoryFilter.set('all')" class="filter-pill" [class.active]="productCategoryFilter() === 'all'">Todas</button>
              <button *ngFor="let c of categories" type="button" (click)="productCategoryFilter.set(c)" class="filter-pill" [class.active]="productCategoryFilter() === c">{{ c }}</button>
              <button type="button" (click)="productStatusFilter.set(productStatusFilter() === 'active' ? 'all' : 'active')" class="filter-pill" [class.active]="productStatusFilter() === 'active'">
                Solo activas ({{ filteredProducts().length }})
              </button>
            </div>
          </div>

          <div class="crud-box">
            <div class="table-wrap">
              <table class="crud-table">
                <thead>
                  <tr>
                    <th>Zapatilla</th>
                    <th>Categoría</th>
                    <th>Modelo 3D (.glb)</th>
                    <th>Precio</th>
                    <th>Acciones CRUD</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngIf="filteredProducts().length === 0">
                    <td colspan="5" class="empty-table-cell">No se encontraron zapatillas con ese filtro.</td>
                  </tr>
                  <tr *ngFor="let p of filteredProducts()" [class.banned-row]="!p.active">
                    <td>
                      <div class="prod-cell">
                        <img [src]="p.images?.[0] || '/images/jordan-chicago.jpg'" alt="" class="prod-thumb" />
                        <div>
                          <p class="prod-name">
                            {{ p.name }}
                            <span *ngIf="!p.active" class="status-pill banned">Inactiva</span>
                          </p>
                          <p class="prod-brand">{{ p.brand }}</p>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="cat-pill">{{ p.category }}</span>
                    </td>
                    <td>
                      <code class="glb-code">{{ p.model_3d_url || 'Sin modelo' }}</code>
                    </td>
                    <td>
                      <strong>\${{ p.price | number:'1.2-2' }}</strong>
                    </td>
                    <td>
                      <div class="row-actions">
                        <a [routerLink]="['/product', p.id]" [queryParams]="{ view: '3d' }" class="action-link">Ver 3D</a>
                        <button type="button" (click)="openEditModal(p)" class="action-btn edit">Editar</button>
                        <button type="button" (click)="deleteProduct(p.id)" class="action-btn delete">Eliminar</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 3: PEDIDOS DE CLIENTES -->
        <div *ngIf="activeTab() === 'orders'" class="orders-box">
          <div class="users-toolbar">
            <div class="users-search-box">
              <label for="adminOrderSearch" class="sr-only">Buscar pedido por número, cliente o producto</label>
              <input
                id="adminOrderSearch"
                type="search"
                [(ngModel)]="orderSearch"
                placeholder="Buscar por N°, cliente, correo o modelo..."
                class="input users-search-input"
              />
            </div>

            <div class="users-filter-pills">
              <button type="button" (click)="orderStatusFilter.set('all')" class="filter-pill" [class.active]="orderStatusFilter() === 'all'">
                Todos ({{ orders().length }})
              </button>
              <button *ngFor="let st of orderStatuses" type="button" (click)="orderStatusFilter.set(st)" class="filter-pill" [class.active]="orderStatusFilter() === st">
                {{ orderLabels[st] }} ({{ countOrdersByStatus(st) }})
              </button>
            </div>
          </div>

          <div *ngIf="filteredOrders().length === 0" class="orders-empty">
            <p>{{ orders().length === 0 ? 'Todavía no hay pedidos registrados en la tienda.' : 'No hay pedidos que coincidan con ese filtro.' }}</p>
          </div>
          <div *ngFor="let order of filteredOrders()" class="order-card">
            <div class="order-top">
              <div>
                <span class="order-id">Pedido #{{ order.user_order_number || order.id }}</span>
                <span class="order-status-pill" [attr.data-status]="order.status">
                  {{ orderLabels[order.status] }}
                </span>
                <span *ngIf="order.payment_status" class="cat-pill">
                  {{ paymentStatusLabels[order.payment_status] }}
                </span>
                <p class="order-meta">
                  {{ order.user?.name || order.customer_name || order.recipient }}<span *ngIf="!order.user" class="self-tag">Cuenta eliminada</span> · {{ order.user?.email || order.customer_email || 'Cliente' }} · {{ order.created_at | date:'dd/MM/yyyy HH:mm' }}
                </p>
                <p class="order-shipping">
                  {{ deliveryMethodLabels[order.delivery_method || 'standard'] }} · {{ paymentMethodLabels[order.payment_method || 'transfer'] }}
                  <ng-container *ngIf="order.payment_reference"> · Ref: {{ order.payment_reference }}</ng-container>
                  · {{ order.recipient }} ({{ order.phone }}) · {{ order.address }}, {{ order.city }} ({{ order.postal_code }})
                </p>
              </div>
              <div class="order-total-box">
                <span class="order-total-label">Total</span>
                <strong class="order-total-val">\${{ order.total | number:'1.2-2' }}</strong>
              </div>
            </div>

            <div class="order-items-list">
              <div *ngFor="let item of order.items" class="order-item-row">
                <span><strong>{{ item.quantity }}x</strong> {{ item.product_name }} (Talle {{ item.size }} · {{ item.color }})</span>
                <strong>\${{ (+item.unit_price * item.quantity) | number:'1.2-2' }}</strong>
              </div>
            </div>

            <div class="order-actions">
              <button
                type="button"
                (click)="downloadOrderReceipt(order.id, order.user_order_number)"
                [disabled]="downloadingReceiptId() === order.id"
                class="action-btn"
              >
                {{ downloadingReceiptId() === order.id ? 'Generando PDF...' : 'Recibo PDF' }}
              </button>
              <button
                *ngIf="order.has_payment_proof"
                type="button"
                (click)="viewOrderPaymentProof(order.id)"
                class="action-btn"
              >
                Ver comprobante
              </button>
              <button
                *ngIf="order.status !== 'cancelled' && order.payment_status !== 'paid'"
                type="button"
                (click)="verifyPayment(order, 'approve')"
                [disabled]="updatingOrderId() === order.id"
                class="action-btn reactivate"
              >
                Aprobar pago
              </button>
              <button
                *ngIf="order.has_payment_proof && order.status !== 'cancelled' && order.payment_status !== 'paid'"
                type="button"
                (click)="verifyPayment(order, 'reject')"
                [disabled]="updatingOrderId() === order.id"
                class="action-btn delete"
              >
                Rechazar comprobante
              </button>
              <button
                *ngFor="let nextStatus of getOrderTransitions(order.status)"
                type="button"
                (click)="changeOrderStatus(order, nextStatus)"
                [disabled]="updatingOrderId() === order.id"
                class="action-btn"
                [class.delete]="nextStatus === 'cancelled'"
              >
                Marcar como {{ orderLabels[nextStatus] }}
              </button>
            </div>
          </div>
        </div>

        <!-- TAB 4: GESTIÓN DE USUARIOS -->
        <div *ngIf="activeTab() === 'users'" class="users-section">
          <div class="users-toolbar">
            <div class="users-search-box">
              <label for="adminUserSearch" class="sr-only">Buscar usuario por nombre o email</label>
              <input
                id="adminUserSearch"
                type="search"
                [(ngModel)]="userSearch"
                (keyup.enter)="loadUsers(1)"
                placeholder="Buscar por nombre o correo..."
                class="input users-search-input"
              />
              <button type="button" (click)="loadUsers(1)" class="btn-secondary">Buscar</button>
            </div>

            <div class="users-filter-pills">
              <button type="button" (click)="setUserFilter('all')" class="filter-pill" [class.active]="userFilter() === 'all'">Todos</button>
              <button type="button" (click)="setUserFilter('admin')" class="filter-pill" [class.active]="userFilter() === 'admin'">Admins</button>
              <button type="button" (click)="setUserFilter('customer')" class="filter-pill" [class.active]="userFilter() === 'customer'">Clientes</button>
              <button type="button" (click)="setUserFilter('banned')" class="filter-pill" [class.active]="userFilter() === 'banned'">Suspendidos</button>
            </div>
          </div>

          <div *ngIf="userActionFeedback()" class="user-feedback-banner" role="status">
            {{ userActionFeedback() }}
          </div>
          <div *ngIf="userActionError()" class="form-error-box" role="alert">
            {{ userActionError() }}
          </div>

          <div class="crud-box">
            <div class="table-wrap">
              <table class="crud-table">
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Pedidos</th>
                    <th>Total Compras</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngIf="users().length === 0">
                    <td colspan="6" class="empty-table-cell">No se encontraron usuarios con ese filtro.</td>
                  </tr>
                  <tr *ngFor="let u of users()" [class.banned-row]="!!u.banned_at">
                    <td>
                      <div class="user-cell">
                        <p class="prod-name">
                          {{ u.name }}
                          <span *ngIf="u.id === authService.currentUser()?.id" class="self-tag">Vos</span>
                        </p>
                        <p class="prod-brand">{{ u.email }}</p>
                        <p *ngIf="u.banned_at && u.ban_reason" class="ban-reason-text">Motivo: {{ u.ban_reason }}</p>
                      </div>
                    </td>
                    <td>
                      <span class="role-pill" [class.admin]="u.role === 'admin'">
                        {{ u.role === 'admin' ? 'Administrador' : 'Cliente' }}
                      </span>
                    </td>
                    <td>
                      <span class="status-pill" [class.banned]="!!u.banned_at">
                        {{ u.banned_at ? 'Suspendido' : 'Activo' }}
                      </span>
                    </td>
                    <td>
                      <strong>{{ u.orders_count ?? 0 }}</strong>
                    </td>
                    <td>
                      <strong>\${{ asNumber(u.total_spent) | number:'1.2-2' }}</strong>
                    </td>
                    <td>
                      <div class="row-actions wrap-actions">
                        <button type="button" (click)="openUserOrders(u)" class="action-btn">
                          Ver pedidos
                        </button>
                        <button
                          type="button"
                          (click)="toggleRole(u)"
                          [disabled]="updatingUserId() === u.id || u.id === authService.currentUser()?.id"
                          class="action-btn"
                        >
                          {{ u.role === 'admin' ? 'Quitar Admin' : 'Hacer Admin' }}
                        </button>
                        <button
                          *ngIf="!u.banned_at"
                          type="button"
                          (click)="openBanModal(u)"
                          [disabled]="updatingUserId() === u.id || u.id === authService.currentUser()?.id"
                          class="action-btn delete"
                        >
                          Banear
                        </button>
                        <button
                          *ngIf="u.banned_at"
                          type="button"
                          (click)="unbanUser(u)"
                          [disabled]="updatingUserId() === u.id"
                          class="action-btn reactivate"
                        >
                          Reactivar
                        </button>
                        <button
                          type="button"
                          (click)="openDeleteUserModal(u)"
                          [disabled]="updatingUserId() === u.id || u.id === authService.currentUser()?.id"
                          class="action-btn delete"
                        >
                          Eliminar
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 5: CENTRO DE NOTIFICACIONES -->
        <div *ngIf="activeTab() === 'notifications'" class="n8n-card">
          <div class="n8n-top-bar">
            <div>
              <span class="admin-tag">Notificaciones · Correo Automático (SMTP)</span>
              <h3>Centro de Notificaciones por Correo</h3>
              <p class="n8n-desc">
                Envío automático y manual de correos electrónicos (bienvenida, recibos de compra con PDF, estados de pedido y alertas de inventario) mediante Gmail SMTP.
              </p>
            </div>
          </div>

          <div class="n8n-email-config">
            <label for="notifyEmailInput">Email destinatario:</label>
            <input
              id="notifyEmailInput"
              type="email"
              [(ngModel)]="notifyEmail"
              class="input email-input"
              placeholder="cliente@correo.com"
            />
          </div>

          <div class="field">
            <label>Seleccioná el tipo de notificación a enviar:</label>
            <div class="notify-options-grid">
              <button
                *ngFor="let opt of webhookOptions"
                type="button"
                (click)="selectedWebhookEvent.set(opt.event)"
                class="notify-option-card"
                [class.selected]="selectedWebhookEvent() === opt.event"
              >
                <span class="notify-opt-title">{{ opt.label }}</span>
                <span class="notify-opt-desc">{{ opt.description }}</span>
              </button>
            </div>
          </div>

          <div class="n8n-send-bar">
            <button
              type="button"
              (click)="triggerWebhook(selectedWebhookEvent())"
              [disabled]="dispatchingWebhook() || !notifyEmail.trim()"
              class="btn-primary"
            >
              {{ dispatchingWebhook() ? 'Enviando notificación...' : 'Enviar notificación' }}
            </button>
            <span class="section-sub">
              Seleccionado: <strong>{{ selectedWebhookOption().label }}</strong> → {{ notifyEmail || 'Ingresá un correo' }}
            </span>
          </div>

          <div *ngIf="webhookResponse()" class="webhook-result-box">
            <div class="webhook-status-bar">
              <span
                class="delivery-badge"
                [class.delivered]="webhookResponse().delivered"
                [class.queued]="!webhookResponse().delivered"
              >
                {{ webhookResponse().delivered ? '✓ Enviado correctamente' : '⚡ Registrado sin envío SMTP' }}
              </span>
              <span class="webhook-msg">{{ webhookResponse().message }}</span>
            </div>

            <div *ngIf="webhookResponse().payload?.email_notification" class="email-preview-card">
              <div class="email-preview-meta">
                <p><strong>Para:</strong> {{ webhookResponse().payload.email_notification.to }}</p>
                <p><strong>Asunto:</strong> {{ webhookResponse().payload.email_notification.subject }}</p>
                <p><strong>Resumen:</strong> {{ webhookResponse().payload.email_notification.summary }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL COMPLETO: CREAR / EDITAR ZAPATILLA DESDE CERO -->
      <div
        *ngIf="modalOpen()"
        class="modal-backdrop"
        (mousedown)="onBackdropMouseDown($event)"
        (click)="onBackdropClick($event, 'product')"
      >
        <div class="crud-modal creator-modal" role="dialog" aria-modal="true">
          <div class="modal-header">
            <div>
              <span class="admin-tag">{{ editingId() ? 'Edición de Calzado #' + editingId() : 'Creador de Calzado Personalizado' }}</span>
              <h2>{{ editingId() ? 'Editar Zapatilla' : 'Crear Nueva Zapatilla' }}</h2>
            </div>
            <button type="button" (click)="closeModal()" class="close-x" aria-label="Cerrar">&times;</button>
          </div>

          <form [formGroup]="productForm" (ngSubmit)="saveProduct()" class="modal-grid">
            <div class="form-col">
              <!-- SECCIÓN 1: DATOS PRINCIPALES -->
              <div class="creator-section">
                <div class="creator-section-head">
                  <span class="section-step">1</span>
                  <h3>Identidad, Categoría y Precio</h3>
                </div>

                <div class="field">
                  <label for="prodNameInput">Nombre de la Zapatilla *</label>
                  <input
                    id="prodNameInput"
                    type="text"
                    formControlName="name"
                    class="input"
                    placeholder="Ej: Nike Dunk Low Panda, Adidas Forum Low, etc."
                  />
                </div>

                <div class="field-row">
                  <div class="field">
                    <label for="prodBrandInput">Marca *</label>
                    <input
                      id="prodBrandInput"
                      type="text"
                      formControlName="brand"
                      list="brandSuggestionsList"
                      class="input"
                      placeholder="Ej: Nike, Adidas, Jordan..."
                    />
                    <datalist id="brandSuggestionsList">
                      <option *ngFor="let b of brandSuggestions" [value]="b"></option>
                    </datalist>
                    <div class="quick-chips">
                      <button
                        *ngFor="let b of brandSuggestions"
                        type="button"
                        (click)="productForm.patchValue({ brand: b })"
                        class="mini-chip"
                        [class.active]="productForm.get('brand')?.value === b"
                      >
                        {{ b }}
                      </button>
                    </div>
                  </div>

                  <div class="field">
                    <label for="prodCatSelect">Categoría *</label>
                    <select id="prodCatSelect" formControlName="category" class="input">
                      <option value="Hombre">Hombre</option>
                      <option value="Mujer">Mujer</option>
                      <option value="Niño">Niño</option>
                    </select>

                    <div class="toggle-checks">
                      <label class="check-pill">
                        <input type="checkbox" formControlName="active" />
                        <span>Publicada en tienda</span>
                      </label>
                      <label class="check-pill">
                        <input type="checkbox" formControlName="featured" />
                        <span>Destacada</span>
                      </label>
                    </div>
                  </div>
                </div>

                <div class="field-row">
                  <div class="field">
                    <label for="prodPriceInput">Precio de Venta ($) *</label>
                    <input id="prodPriceInput" type="number" step="0.01" formControlName="price" class="input" />
                  </div>
                  <div class="field">
                    <label for="prodOrigPriceInput">Precio Anterior / Oferta ($)</label>
                    <input id="prodOrigPriceInput" type="number" step="0.01" formControlName="original_price" class="input" placeholder="Opcional" />
                  </div>
                </div>

                <div class="field">
                  <label for="prodDescInput">Descripción (mínimo 10 caracteres) *</label>
                  <textarea
                    id="prodDescInput"
                    rows="2"
                    formControlName="description"
                    class="input"
                    placeholder="Describí materiales, calce, estilo y detalles de la zapatilla..."
                  ></textarea>
                </div>
              </div>

              <!-- SECCIÓN 2: FOTOS / IMÁGENES -->
              <div class="creator-section">
                <div class="creator-section-head">
                  <span class="section-step">2</span>
                  <div>
                    <h3>Fotos de la Zapatilla ({{ productImages().length }})</h3>
                    <p class="section-sub">Subí fotos desde tu PC, explorá la galería o pegá una URL</p>
                  </div>
                </div>

                <div class="mode-pills">
                  <button type="button" (click)="imageSourceTab.set('upload')" class="mode-pill" [class.active]="imageSourceTab() === 'upload'">
                    📁 Subir desde mi PC
                  </button>
                  <button type="button" (click)="imageSourceTab.set('gallery')" class="mode-pill" [class.active]="imageSourceTab() === 'gallery'">
                    🖼️ Explorar Galería ({{ filteredLibraryImages().length }})
                  </button>
                  <button type="button" (click)="imageSourceTab.set('url')" class="mode-pill" [class.active]="imageSourceTab() === 'url'">
                    🔗 Pegar URL
                  </button>
                </div>

                <!-- Subir imagen desde PC -->
                <div *ngIf="imageSourceTab() === 'upload'" class="upload-dropzone">
                  <input
                    #imageFileInput
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,.avif,.gif"
                    multiple
                    (change)="onImageFilesSelected($event)"
                    class="hidden-file-input"
                  />
                  <div class="dropzone-content">
                    <p class="dropzone-title">
                      {{ uploadingImage() ? 'Subiendo imagen al servidor...' : 'Seleccioná una o varias fotos desde tu computadora' }}
                    </p>
                    <p class="dropzone-hint">Formatos: JPG, PNG, WEBP, AVIF (hasta 15 MB por foto)</p>
                    <button
                      type="button"
                      (click)="imageFileInput.click()"
                      [disabled]="uploadingImage()"
                      class="btn-primary btn-sm"
                    >
                      {{ uploadingImage() ? 'Subiendo...' : '📂 Examinar fotos en mi PC' }}
                    </button>
                  </div>
                </div>

                <!-- Explorar galería de fotos -->
                <div *ngIf="imageSourceTab() === 'gallery'" class="explorer-box">
                  <input
                    type="search"
                    [(ngModel)]="imageGallerySearch"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Buscar foto en la galería..."
                    class="input input-sm"
                  />
                  <div class="image-gallery-grid">
                    <button
                      *ngFor="let img of filteredLibraryImages()"
                      type="button"
                      (click)="addImageToProduct(img.url)"
                      class="gallery-thumb-btn"
                      [class.selected]="productImages().includes(img.url)"
                      [title]="img.label + ' (' + img.source + ')'"
                    >
                      <img [src]="img.url" [alt]="img.label" class="gallery-thumb-img" />
                      <span class="gallery-thumb-name">{{ img.label }}</span>
                    </button>
                  </div>
                </div>

                <!-- Pegar URL de imagen -->
                <div *ngIf="imageSourceTab() === 'url'" class="inline-add-row">
                  <input
                    type="url"
                    [(ngModel)]="customImageUrlInput"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="https://... o /images/mi-zapatilla.jpg"
                    class="input"
                  />
                  <button type="button" (click)="addCustomImageUrl()" class="btn-secondary">
                    + Agregar foto
                  </button>
                </div>

                <!-- Lista de fotos seleccionadas para la zapatilla -->
                <div *ngIf="productImages().length > 0" class="selected-photos-strip">
                  <div *ngFor="let imgUrl of productImages(); let idx = index" class="selected-photo-item" [class.is-cover]="idx === 0">
                    <img [src]="imgUrl" alt="Foto zapatilla" class="selected-photo-img" />
                    <div class="selected-photo-actions">
                      <span *ngIf="idx === 0" class="cover-badge">★ Portada</span>
                      <button *ngIf="idx > 0" type="button" (click)="setAsCoverImage(idx)" class="photo-mini-btn">
                        Hacer portada
                      </button>
                      <button type="button" (click)="removeProductImage(idx)" class="photo-mini-btn danger" aria-label="Quitar foto">
                        &times;
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- SECCIÓN 3: MODELO 3D (.GLB) -->
              <div class="creator-section">
                <div class="creator-section-head">
                  <span class="section-step">3</span>
                  <div>
                    <h3>Modelo 3D Interactivo (.GLB / .GLTF)</h3>
                    <p class="section-sub">Subí un archivo .glb desde tu PC, indagá la biblioteca 3D o creala solo con fotos 2D</p>
                  </div>
                </div>

                <div class="mode-pills">
                  <button type="button" (click)="setModelSourceTab('upload')" class="mode-pill" [class.active]="modelSourceTab() === 'upload'">
                    📁 Subir .GLB desde mi PC
                  </button>
                  <button type="button" (click)="setModelSourceTab('library')" class="mode-pill" [class.active]="modelSourceTab() === 'library'">
                    👟 Explorar Biblioteca 3D ({{ filteredModels3d().length }})
                  </button>
                  <button type="button" (click)="setModelSourceTab('url')" class="mode-pill" [class.active]="modelSourceTab() === 'url'">
                    🔗 URL .GLB
                  </button>
                  <button type="button" (click)="setModelSourceTab('none')" class="mode-pill" [class.active]="modelSourceTab() === 'none'">
                    🚫 Sin modelo 3D
                  </button>
                </div>

                <!-- Subir archivo .GLB desde PC -->
                <div *ngIf="modelSourceTab() === 'upload'" class="upload-dropzone">
                  <input
                    #modelFileInput
                    type="file"
                    accept=".glb,.gltf"
                    (change)="onModelFileSelected($event)"
                    class="hidden-file-input"
                  />
                  <div class="dropzone-content">
                    <p class="dropzone-title">
                      {{ uploadingModel() ? 'Subiendo modelo 3D (.glb)...' : 'Cargá tu propio archivo 3D (.glb o .gltf) desde tu computadora' }}
                    </p>
                    <p class="dropzone-hint">Se previsualiza al instante en el visor 3D 360° de la derecha (hasta 50 MB)</p>
                    <button
                      type="button"
                      (click)="modelFileInput.click()"
                      [disabled]="uploadingModel()"
                      class="btn-primary btn-sm"
                    >
                      {{ uploadingModel() ? 'Subiendo 3D...' : '📂 Examinar archivo .GLB en mi PC' }}
                    </button>
                  </div>
                </div>

                <!-- Explorar biblioteca 3D -->
                <div *ngIf="modelSourceTab() === 'library'" class="explorer-box">
                  <input
                    type="search"
                    [(ngModel)]="modelLibrarySearch"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Buscar modelo 3D por nombre, color o categoría..."
                    class="input input-sm"
                  />
                  <div class="model-library-grid">
                    <div
                      *ngFor="let m of filteredModels3d()"
                      (click)="selectLibraryModel(m)"
                      class="model-library-card"
                      [class.selected]="productForm.get('model_3d_url')?.value === m.url"
                    >
                      <img [src]="m.image || '/images/jordan-chicago.jpg'" alt="" class="model-card-thumb" />
                      <div class="model-card-info">
                        <span class="model-card-cat">{{ m.category }}</span>
                        <p class="model-card-title">{{ m.label }}</p>
                        <code class="glb-code">{{ m.url }}</code>
                      </div>
                      <button
                        *ngIf="m.image"
                        type="button"
                        (click)="useSuggestedModelPhoto(m.image, $event)"
                        class="use-photo-btn"
                        title="Agregar también la foto de este modelo"
                      >
                        + Usar foto
                      </button>
                    </div>
                  </div>
                </div>

                <!-- URL .GLB manual -->
                <div *ngIf="modelSourceTab() === 'url'" class="field">
                  <label for="customGlbInput">URL o ruta del archivo .GLB / .GLTF</label>
                  <input
                    id="customGlbInput"
                    type="text"
                    formControlName="model_3d_url"
                    class="input"
                    placeholder="https://.../mi-modelo.glb o /models/jordan-chicago.glb"
                  />
                </div>

                <!-- Sin modelo 3D -->
                <div *ngIf="modelSourceTab() === 'none'" class="no-3d-banner">
                  Esta zapatilla se publicará únicamente con sus fotos 2D (sin visor 3D). Podés agregarle un archivo .glb más adelante cuando quieras.
                </div>
              </div>

              <!-- SECCIÓN 4: TALLES, COLORES Y STOCK (VARIANTES) -->
              <div class="creator-section">
                <div class="creator-section-head space-between">
                  <div class="creator-section-head">
                    <span class="section-step">4</span>
                    <div>
                      <h3>Talles, Colores y Stock ({{ productVariants().length }} variantes · {{ totalVariantsStock() }} pares)</h3>
                      <p class="section-sub">Generá una curva completa de talles o agregá talles y colores personalizados</p>
                    </div>
                  </div>
                  <button
                    *ngIf="productVariants().length > 0"
                    type="button"
                    (click)="clearAllVariants()"
                    class="action-btn delete"
                  >
                    🗑️ Limpiar talles
                  </button>
                </div>

                <div class="variant-generator-bar">
                  <input
                    type="text"
                    [(ngModel)]="batchColorInput"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Color (ej: Blanco/Negro)"
                    class="input input-sm"
                  />
                  <input
                    type="number"
                    min="0"
                    [(ngModel)]="batchStockInput"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Stock"
                    class="input input-sm stock-num-input"
                  />
                  <button type="button" (click)="generateCategorySizes()" class="btn-secondary btn-sm">
                    ⚡ Generar talles de {{ productForm.get('category')?.value }}
                  </button>
                </div>

                <div class="variant-manual-bar">
                  <input
                    type="text"
                    [(ngModel)]="newVariantSize"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Talle (ej: 42)"
                    class="input input-sm size-num-input"
                  />
                  <input
                    type="text"
                    [(ngModel)]="newVariantColor"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Color (ej: Rojo/Blanco)"
                    class="input input-sm"
                  />
                  <input
                    type="number"
                    min="0"
                    [(ngModel)]="newVariantStock"
                    [ngModelOptions]="{ standalone: true }"
                    placeholder="Stock"
                    class="input input-sm stock-num-input"
                  />
                  <button type="button" (click)="addManualVariant()" class="btn-secondary btn-sm">
                    + Agregar variante
                  </button>
                </div>

                <div *ngIf="productVariants().length === 0" class="panel-empty">
                  Sin talles cargados. Escribí un talle o tocá "Generar talles".
                </div>

                <div *ngIf="productVariants().length > 0" class="variants-list-box">
                  <div *ngFor="let v of productVariants(); let vi = index" class="variant-item-row">
                    <span class="variant-pill">Talle <strong>{{ v.size }}</strong></span>
                    <input
                      type="text"
                      [(ngModel)]="v.color"
                      [ngModelOptions]="{ standalone: true }"
                      class="input input-xs"
                      aria-label="Color de variante"
                    />
                    <div class="variant-stock-wrap">
                      <span>Stock:</span>
                      <input
                        type="number"
                        min="0"
                        [(ngModel)]="v.stock"
                        [ngModelOptions]="{ standalone: true }"
                        class="input input-xs stock-num-input"
                        aria-label="Stock de variante"
                      />
                    </div>
                    <button type="button" (click)="removeVariantRow(vi)" class="photo-mini-btn danger" aria-label="Eliminar variante">
                      &times;
                    </button>
                  </div>
                </div>
              </div>

              <div *ngIf="uploadFeedback()" class="user-feedback-banner">{{ uploadFeedback() }}</div>
              <div *ngIf="formError()" class="form-error-box">{{ formError() }}</div>

              <div class="modal-actions">
                <button type="submit" [disabled]="saving()" class="btn-primary">
                  {{ saving() ? 'Guardando...' : (editingId() ? 'Guardar Cambios' : 'Crear Zapatilla en Catálogo') }}
                </button>
                <button type="button" (click)="closeModal()" class="btn-secondary">Cancelar</button>
              </div>
            </div>

            <!-- Columna Derecha: Vista Previa 3D + Tarjeta de Catálogo en Tiempo Real -->
            <div class="preview-col">
              <div class="live-card-preview">
                <span class="preview-label">Vista Previa de Portada (2D):</span>
                <div class="preview-photo-card">
                  <img [src]="productImages()[0] || '/images/jordan-chicago.jpg'" alt="Foto portada" class="preview-photo-thumb" />
                  <div>
                    <strong class="prod-name">{{ productForm.get('name')?.value || 'Nombre de tu zapatilla' }}</strong>
                    <span class="prod-brand">{{ productForm.get('brand')?.value || 'Marca' }} · {{ productForm.get('category')?.value }}</span>
                    <strong class="top-seller-rev">\${{ (productForm.get('price')?.value || 0) | number:'1.2-2' }}</strong>
                  </div>
                </div>
              </div>

              <p class="preview-label">Vista Previa 3D Interactiva:</p>
              <app-model-viewer-3d
                [src]="productForm.get('model_3d_url')?.value"
                [alt]="productForm.get('name')?.value || 'Preview 3D'"
                minHeight="320px"
                [showHint]="false"
              ></app-model-viewer-3d>
              <div class="current-glb-box">
                <span class="preview-label">Archivo 3D asignado:</span>
                <code class="glb-code">{{ productForm.get('model_3d_url')?.value || 'Sin modelo 3D (solo fotos 2D)' }}</code>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL: PEDIDOS DEL USUARIO -->
      <div
        *ngIf="selectedUser()"
        class="modal-backdrop"
        (mousedown)="onBackdropMouseDown($event)"
        (click)="onBackdropClick($event, 'userOrders')"
      >
        <div class="crud-modal user-orders-modal" role="dialog" aria-modal="true">
          <div class="modal-header">
            <div>
              <h2>Pedidos de {{ selectedUser()?.name }}</h2>
              <p class="modal-sub">{{ selectedUser()?.email }} · {{ selectedUser()?.role === 'admin' ? 'Administrador' : 'Cliente' }} · {{ selectedUser()?.banned_at ? 'Suspendido' : 'Activo' }}</p>
            </div>
            <button type="button" (click)="closeUserOrdersModal()" class="close-x" aria-label="Cerrar">&times;</button>
          </div>

          <div *ngIf="loadingUserOrders()" class="panel-empty">Cargando pedidos del usuario...</div>

          <div *ngIf="!loadingUserOrders() && selectedUserOrders().length === 0" class="panel-empty">
            Este usuario todavía no realizó ningún pedido.
          </div>

          <div *ngIf="!loadingUserOrders() && selectedUserOrders().length > 0" class="orders-box">
            <div *ngFor="let order of selectedUserOrders()" class="order-card modal-order-card">
              <div class="order-top">
                <div>
                  <span class="order-id">Pedido #{{ order.user_order_number || order.id }}</span>
                  <span class="order-status-pill" [attr.data-status]="order.status">
                    {{ orderLabels[order.status] }}
                  </span>
                  <p class="order-meta">{{ order.created_at | date:'dd/MM/yyyy HH:mm' }}</p>
                  <p class="order-shipping">{{ order.recipient }} · {{ order.address }}, {{ order.city }} ({{ order.postal_code }})</p>
                </div>
                <div class="order-total-box">
                  <span class="order-total-label">Total</span>
                  <strong class="order-total-val">\${{ order.total | number:'1.2-2' }}</strong>
                </div>
              </div>

              <div class="order-items-list">
                <div *ngFor="let item of order.items" class="order-item-row">
                  <span><strong>{{ item.quantity }}x</strong> {{ item.product_name }} (Talle {{ item.size }} · {{ item.color }})</span>
                  <strong>\${{ (+item.unit_price * item.quantity) | number:'1.2-2' }}</strong>
                </div>
              </div>

              <div class="order-actions">
                <button
                  type="button"
                  (click)="downloadOrderReceipt(order.id, order.user_order_number)"
                  [disabled]="downloadingReceiptId() === order.id"
                  class="action-btn"
                >
                  {{ downloadingReceiptId() === order.id ? 'Generando PDF...' : 'Recibo PDF' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL: SUSPENDER / BANEAR USUARIO -->
      <div
        *ngIf="banTargetUser()"
        class="modal-backdrop"
        (mousedown)="onBackdropMouseDown($event)"
        (click)="onBackdropClick($event, 'ban')"
      >
        <div class="crud-modal ban-modal" role="dialog" aria-modal="true">
          <div class="modal-header">
            <h2>Suspender cuenta de {{ banTargetUser()?.name }}</h2>
            <button type="button" (click)="closeBanModal()" class="close-x" aria-label="Cerrar">&times;</button>
          </div>
          <p class="ban-desc">
            Al suspender a <strong>{{ banTargetUser()?.email }}</strong>, se cerrarán sus sesiones activas y no podrá ingresar hasta que lo reactives.
          </p>
          <div class="field">
            <label for="banReasonInput">Motivo de suspensión (opcional)</label>
            <input
              id="banReasonInput"
              type="text"
              [(ngModel)]="banReason"
              class="input"
              placeholder="Ej: Actividad fraudulenta o incumplimiento de términos"
            />
          </div>
          <div class="modal-actions">
            <button type="button" (click)="confirmBan()" [disabled]="updatingUserId() === banTargetUser()?.id" class="action-btn delete ban-confirm-btn">
              Confirmar suspensión
            </button>
            <button type="button" (click)="closeBanModal()" class="btn-secondary">Cancelar</button>
          </div>
        </div>
      </div>

      <!-- MODAL: ELIMINAR USUARIO (CONSERVA HISTORIAL DE PEDIDOS) -->
      <div
        *ngIf="deleteTargetUser()"
        class="modal-backdrop"
        (mousedown)="onBackdropMouseDown($event)"
        (click)="onBackdropClick($event, 'deleteUser')"
      >
        <div class="crud-modal ban-modal" role="dialog" aria-modal="true">
          <div class="modal-header">
            <h2>Eliminar cuenta de {{ deleteTargetUser()?.name }}</h2>
            <button type="button" (click)="closeDeleteUserModal()" class="close-x" aria-label="Cerrar">&times;</button>
          </div>
          <p class="ban-desc">
            Estás por eliminar permanentemente la cuenta <strong>{{ deleteTargetUser()?.email }}</strong>. Esta acción no se puede deshacer y cerrará todas sus sesiones activas.
          </p>
          <div class="user-feedback-banner">
            <strong>Historial protegido:</strong>
            <ng-container *ngIf="(deleteTargetUser()?.orders_count ?? 0) > 0; else noOrdersNote">
              Sus <strong>{{ deleteTargetUser()?.orders_count }} pedido(s)</strong> (por \${{ asNumber(deleteTargetUser()?.total_spent) | number:'1.2-2' }}) seguirán figurando en Pedidos y en todas las estadísticas de ventas.
            </ng-container>
            <ng-template #noOrdersNote>
              Si el usuario tuviera compras registradas, su historial de pedidos y ventas se conserva intacto en el panel.
            </ng-template>
          </div>
          <div class="modal-actions">
            <button type="button" (click)="confirmDeleteUser()" [disabled]="updatingUserId() === deleteTargetUser()?.id" class="action-btn delete ban-confirm-btn">
              {{ updatingUserId() === deleteTargetUser()?.id ? 'Eliminando...' : 'Eliminar cuenta' }}
            </button>
            <button type="button" (click)="closeDeleteUserModal()" class="btn-secondary">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .admin-page{min-height:100vh;padding-top:84px;background:var(--bg-main)}
    .py-10{padding:2.5rem 0 4rem}
    .admin-header,.panel-card-head,.order-top,.users-toolbar,.modal-header,.cat-bar-head,.cat-bar-sales,.top-seller-top,.top-seller-meta,.order-item-row,.selected-photo-actions{display:flex;justify-content:space-between;align-items:center;gap:.75rem}
    .admin-header{align-items:flex-end;flex-wrap:wrap;margin-bottom:2rem}
    .panel-card-head,.order-top{align-items:flex-start}
    .order-top,.users-toolbar{flex-wrap:wrap}
    .admin-tag,.model-card-cat{font-size:.72rem;text-transform:uppercase;letter-spacing:.12em;color:#4ade80;font-weight:800}
    .admin-title{font-size:2rem;font-weight:900;color:#fff;margin-top:.25rem}
    .admin-top-actions,.n8n-actions,.modal-actions,.order-actions,.users-filter-pills,.mode-pills,.toggle-checks,.size-chips,.quick-chips{display:flex;gap:.5rem;flex-wrap:wrap}
    .admin-tabs{display:flex;gap:.4rem;background:rgba(255,255,255,.04);padding:.3rem;border-radius:.85rem;width:fit-content;margin-bottom:2rem;border:1px solid rgba(255,255,255,.08);flex-wrap:wrap}
    .admin-tab{background:0 0;border:none;color:rgba(255,255,255,.6);font-size:.85rem;font-weight:700;padding:.55rem 1.1rem;border-radius:.65rem;cursor:pointer}
    .admin-tab.active,.filter-pill.active,.mini-chip.active,.mini-chip:hover,.use-photo-btn:hover{background:#fff;color:#000;border-color:#fff}
    .dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem}
    .stat-card,.panel-card,.cat-breakdown-card,.crud-box,.n8n-card,.orders-empty,.order-card,.crud-modal{background:#141418;border:1px solid rgba(255,255,255,.09);border-radius:1.15rem}
    .stat-card,.panel-card,.cat-breakdown-card,.order-card{padding:1.5rem;display:flex;flex-direction:column;gap:.85rem}
    .stat-card{gap:.35rem}
    .stat-card-accent{border-color:rgba(74,222,128,.28);background:linear-gradient(180deg,rgba(74,222,128,.07) 0%,#141418 100%)}
    .stat-card-label,.preview-label,.field label{font-size:.78rem;color:rgba(255,255,255,.6);font-weight:600}
    .stat-card-val{font-size:2.15rem;font-weight:900;color:#fff;line-height:1.15}
    .stat-card-sub,.top-seller-rev,.cat-bar-rev,.order-total-val,.compact-row-val,.cover-badge{font-size:.78rem;color:#4ade80;font-weight:700}
    .order-total-val{font-size:1.35rem;font-weight:900}
    .stat-card-sub.stat-warn{color:#facc15}
    .analytics-two-col{grid-column:1/-1;display:grid;grid-template-columns:1fr;gap:1.25rem}
    @media (min-width:900px){.analytics-two-col{grid-template-columns:1.15fr .85fr}}
    .panel-card-head h3,.creator-section-head h3{font-size:.96rem;font-weight:800;color:#fff;margin:0}
    .panel-card-sub,.section-sub,.dropzone-hint,.compact-row-sub,.order-shipping,.modal-sub{font-size:.74rem;color:rgba(255,255,255,.5);margin:.15rem 0 0}
    .panel-badge,.delivery-badge,.order-status-pill,.role-pill,.status-pill,.stock-alert-pill,.cat-pill{font-size:.72rem;font-weight:800;padding:.24rem .65rem;border-radius:9999px;display:inline-block}
    .panel-badge,.delivery-badge.delivered,.order-status-pill[data-status="confirmed"],.order-status-pill[data-status="delivered"],.status-pill{background:rgba(74,222,128,.16);color:#4ade80;border:1px solid rgba(74,222,128,.32)}
    .order-status-pill,.role-pill.admin{background:rgba(56,189,248,.16);color:#38bdf8;border:1px solid rgba(56,189,248,.32)}
    .order-status-pill[data-status="cancelled"],.status-pill.banned,.stock-alert-pill.out{background:rgba(248,113,113,.18);color:#f87171;border:1px solid rgba(248,113,113,.35)}
    .delivery-badge.queued,.stock-alert-pill{background:rgba(250,204,21,.16);color:#facc15;border:1px solid rgba(250,204,21,.35)}
    .cat-pill,.role-pill{background:rgba(255,255,255,.08);color:#e4e4e7}
    .panel-link-btn,.action-link,.action-btn,.filter-pill,.mini-chip,.mode-pill,.photo-mini-btn,.use-photo-btn{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.13);color:#fff;font-size:.74rem;font-weight:700;padding:.34rem .7rem;border-radius:.5rem;cursor:pointer;text-decoration:none}
    .filter-pill,.mini-chip{border-radius:9999px}
    .mini-chip,.photo-mini-btn,.use-photo-btn{font-size:.67rem;padding:.2rem .48rem}
    .action-btn.delete,.photo-mini-btn.danger{color:#f87171;border-color:rgba(248,113,113,.3)}
    .action-btn.reactivate{color:#4ade80;border-color:rgba(74,222,128,.35)}
    .action-btn:disabled{opacity:.45;cursor:not-allowed}
    .panel-empty,.no-3d-banner{background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.14);border-radius:.8rem;padding:1rem;font-size:.8rem;color:rgba(255,255,255,.58);text-align:center}
    .panel-empty.healthy{border-color:rgba(74,222,128,.3);color:#4ade80;background:rgba(74,222,128,.06)}
    .top-sellers-list,.compact-list,.orders-box,.users-section,.form-col,.preview-col,.field,.user-cell,.top-seller-info,.compact-row-main,.model-card-info,.explorer-box,.variants-list-box,.model-library-grid,.webhook-result-box{display:flex;flex-direction:column;gap:.65rem}
    .top-seller-row,.compact-row,.cat-bar-item,.status-metric-box,.n8n-endpoint-box,.webhook-status-bar,.preview-photo-card,.model-library-card,.variant-item-row,.current-glb-box{background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.07);border-radius:.8rem;padding:.7rem .85rem}
    .top-seller-row,.compact-row,.preview-photo-card,.model-library-card,.variant-item-row,.low-stock-left,.prod-cell,.row-actions,.creator-section-head{display:flex;align-items:center;gap:.7rem}
    .rank-badge,.section-step{width:26px;height:26px;border-radius:9999px;background:rgba(255,255,255,.08);color:#fff;font-size:.74rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
    .rank-badge.rank-top,.section-step,.mode-pill.active{background:#4ade80;color:#052e16;border-color:#4ade80}
    .mini-thumb,.prod-thumb,.model-card-thumb,.preview-photo-thumb{width:42px;height:42px;border-radius:.5rem;object-fit:cover;flex-shrink:0}
    .top-seller-info,.model-card-info,.inline-add-row .input,.users-search-input,.variant-item-row .input{flex:1;min-width:0}
    .top-seller-name,.prod-name,.model-card-title,.dropzone-title,.cat-bar-name{font-size:.84rem;font-weight:700;color:#fff;margin:0}
    .prod-brand,.top-seller-meta,.cat-bar-sales{font-size:.74rem;color:rgba(255,255,255,.5)}
    .progress-track{height:5px;background:rgba(255,255,255,.08);border-radius:9999px;overflow:hidden}
    .progress-fill{height:100%;background:linear-gradient(90deg,#38bdf8,#4ade80)}
    .status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:.55rem}
    .status-metric-label,.endpoint-label,.order-total-label{font-size:.7rem;color:rgba(255,255,255,.5);text-transform:uppercase}
    .status-metric-val{font-size:1.3rem;font-weight:900;color:#fff;display:block}
    .sizes-block{border-top:1px solid rgba(255,255,255,.07);padding-top:.6rem}
    .sizes-block h4{font-size:.8rem;margin:0 0 .5rem;color:#e4e4e7}
    .size-chip{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e4e4e7;font-size:.74rem;padding:.3rem .65rem;border-radius:9999px}
    .size-chip strong,.variant-pill strong,.endpoint-sub{color:#38bdf8}
    .cat-breakdown-card{grid-column:1/-1}
    .cat-bars{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem}
    .crud-box{overflow:hidden}
    .table-wrap{overflow-x:auto}
    .crud-table{width:100%;border-collapse:collapse;font-size:.85rem}
    .crud-table th,.crud-table td{padding:.9rem 1.15rem;text-align:left;border-bottom:1px solid rgba(255,255,255,.06)}
    .crud-table th{color:rgba(255,255,255,.45);font-size:.73rem;text-transform:uppercase}
    .glb-code{font-size:.73rem;color:#38bdf8;background:rgba(56,189,248,.1);padding:.18rem .45rem;border-radius:.38rem}
    .n8n-card{padding:1.75rem}
    .n8n-top-bar{display:flex;justify-content:space-between;gap:1.25rem;flex-wrap:wrap;margin-bottom:1.25rem}
    .n8n-desc,.ban-desc{color:rgba(255,255,255,.7);font-size:.85rem;line-height:1.5;max-width:650px;margin:.4rem 0 0}
    .n8n-email-config{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem;max-width:420px}
    .notify-options-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.65rem;margin-top:.3rem}
    .notify-option-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);border-radius:.8rem;padding:.85rem 1rem;text-align:left;cursor:pointer;display:flex;flex-direction:column;gap:.25rem;transition:border-color .15s,background .15s}
    .notify-option-card:hover{border-color:rgba(255,255,255,.32);background:rgba(255,255,255,.08)}
    .notify-option-card.selected{border-color:#4ade80;background:rgba(74,222,128,.12);box-shadow:0 0 0 1px rgba(74,222,128,.35)}
    .notify-opt-title{font-size:.84rem;font-weight:800;color:#fff}
    .notify-opt-desc{font-size:.73rem;color:rgba(255,255,255,.58);line-height:1.35}
    .n8n-send-bar{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1.15rem}
    .email-preview-card{background:#18181f;border-left:4px solid #4ade80;padding:.9rem 1.15rem;border-radius:.7rem;font-size:.83rem}
    .webhook-json{background:#09090b;border:1px solid rgba(74,222,128,.3);color:#4ade80;padding:.9rem;border-radius:.8rem;font-size:.78rem;overflow-x:auto;margin:0}
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);z-index:300;display:flex;align-items:center;justify-content:center;padding:1rem}
    .crud-modal{max-width:880px;width:100%;padding:1.6rem;max-height:92vh;overflow-y:auto;border-color:rgba(255,255,255,.16)}
    .creator-modal{max-width:1120px}
    .user-orders-modal{max-width:760px}
    .ban-modal{max-width:480px;display:flex;flex-direction:column;gap:1rem}
    .close-x{background:0 0;border:none;color:#fff;font-size:1.6rem;cursor:pointer}
    .modal-grid{display:grid;grid-template-columns:1fr;gap:1.35rem;margin-top:1rem}
    @media (min-width:820px){.modal-grid{grid-template-columns:1.22fr .78fr}}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
    .input{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);color:#fff;padding:.55rem .8rem;border-radius:.55rem;font-size:.84rem;width:100%}
    .input option{background:#141418;color:#fff}
    .input-sm{padding:.42rem .65rem;font-size:.78rem}
    .input-xs{padding:.28rem .48rem;font-size:.75rem}
    .btn-sm{padding:.42rem .8rem;font-size:.78rem}
    .form-error-box{color:#fca5a5;font-size:.8rem;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);padding:.65rem .85rem;border-radius:.6rem}
    .user-feedback-banner{background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.35);color:#86efac;padding:.65rem .9rem;border-radius:.65rem;font-size:.82rem;font-weight:600}
    .order-id{font-size:1rem;font-weight:800;color:#fff;margin-right:.5rem}
    .order-meta{font-size:.8rem;color:rgba(255,255,255,.75);margin:.3rem 0 .15rem}
    .order-total-box{text-align:right}
    .order-items-list{display:flex;flex-direction:column;gap:.4rem;background:rgba(255,255,255,.03);border-radius:.7rem;padding:.75rem .95rem}
    .users-search-box,.inline-add-row,.variant-generator-bar,.variant-manual-bar,.variant-stock-wrap{display:flex;gap:.45rem;align-items:center}
    .users-search-box{flex:1;min-width:240px;max-width:440px}
    .variant-generator-bar,.variant-manual-bar{flex-wrap:wrap}
    .variant-generator-bar .input,.variant-manual-bar .input{flex:1;min-width:105px}
    .empty-table-cell{text-align:center;color:rgba(255,255,255,.5);padding:2rem}
    .banned-row{opacity:.78;background:rgba(248,113,113,.04)}
    .self-tag{font-size:.66rem;font-weight:800;color:#38bdf8;background:rgba(56,189,248,.15);padding:.1rem .42rem;border-radius:9999px;margin-left:.35rem}
    .ban-reason-text{font-size:.72rem;color:#fca5a5;margin:.1rem 0 0}
    .wrap-actions{flex-wrap:wrap}
    .creator-section{background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.08);border-radius:.95rem;padding:1rem;display:flex;flex-direction:column;gap:.75rem}
    .check-pill{display:inline-flex;align-items:center;gap:.38rem;font-size:.73rem;color:#e4e4e7;cursor:pointer;background:rgba(255,255,255,.05);padding:.28rem .55rem;border-radius:.45rem;border:1px solid rgba(255,255,255,.1)}
    .upload-dropzone{border:1px dashed rgba(74,222,128,.45);background:rgba(74,222,128,.05);border-radius:.8rem;padding:.95rem;text-align:center}
    .hidden-file-input{display:none}
    .dropzone-content{display:flex;flex-direction:column;align-items:center;gap:.35rem}
    .image-gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:.45rem;max-height:200px;overflow-y:auto}
    .gallery-thumb-btn{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:.6rem;padding:.3rem;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.2rem;color:#e4e4e7}
    .gallery-thumb-btn.selected,.gallery-thumb-btn:hover,.selected-photo-item.is-cover{border-color:#4ade80;background:rgba(74,222,128,.09)}
    .gallery-thumb-img,.selected-photo-img{width:100%;height:64px;object-fit:cover;border-radius:.4rem}
    .gallery-thumb-name{font-size:.64rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .selected-photos-strip{display:flex;gap:.55rem;flex-wrap:wrap}
    .selected-photo-item{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);border-radius:.65rem;padding:.38rem;display:flex;flex-direction:column;gap:.3rem;width:110px}
    .model-library-grid,.variants-list-box{max-height:210px;overflow-y:auto}
    .model-library-card{cursor:pointer}
    .model-library-card.selected,.model-library-card:hover{border-color:#38bdf8;background:rgba(56,189,248,.1)}
    .stock-num-input{max-width:80px!important;flex:0 0 80px!important}
    .size-num-input{max-width:110px!important;flex:0 0 110px!important}
    .space-between{justify-content:space-between;flex-wrap:wrap}
    .variant-pill{font-size:.74rem;color:#e4e4e7;min-width:62px}
    .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}
  `]
})
export class AdminComponent implements OnInit {
  private readonly productService = inject(ProductService);
  private readonly adminService = inject(AdminService);
  readonly authService = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  activeTab = signal<'dashboard' | 'products' | 'orders' | 'users' | 'notifications'>('dashboard');
  stats = signal<AdminStats | null>(null);
  products = signal<Product[]>([]);
  orders = signal<Order[]>([]);
  productSearch = '';
  productCategoryFilter = signal<string>('all');
  productStatusFilter = signal<'all' | 'active'>('all');
  orderSearch = '';
  orderStatusFilter = signal<'all' | OrderStatus>('all');
  updatingOrderId = signal<number | null>(null);
  downloadingReceiptId = signal<number | null>(null);
  restockingVariantId = signal<number | null>(null);
  restockFeedback = signal<string | null>(null);
  users = signal<User[]>([]);
  usersTotal = signal<number>(0);
  userSearch = '';
  userFilter = signal<'all' | 'admin' | 'customer' | 'banned'>('all');
  updatingUserId = signal<number | null>(null);
  userActionFeedback = signal<string | null>(null);
  userActionError = signal<string | null>(null);
  selectedUser = signal<User | null>(null);
  selectedUserOrders = signal<Order[]>([]);
  loadingUserOrders = signal<boolean>(false);
  banTargetUser = signal<User | null>(null);
  banReason = '';
  deleteTargetUser = signal<User | null>(null);
  readonly orderLabels = ORDER_LABELS;
  readonly paymentMethodLabels = PAYMENT_METHOD_LABELS;
  readonly paymentStatusLabels = PAYMENT_STATUS_LABELS;
  readonly deliveryMethodLabels = DELIVERY_METHOD_LABELS;
  readonly orderStatuses: OrderStatus[] = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
  readonly categories = ['Hombre', 'Mujer', 'Niño'];
  readonly brandSuggestions = ['Nike', 'Jordan', 'Adidas', 'New Balance', 'Vans', 'Puma', 'Converse', 'UrbanSole'];
  private backdropMouseDown = false;
  models3d = signal<Model3dOption[]>([]);
  libraryImages = signal<MediaImageOption[]>([]);
  imageSourceTab = signal<'upload' | 'gallery' | 'url'>('upload');
  modelSourceTab = signal<'upload' | 'library' | 'url' | 'none'>('upload');
  imageGallerySearch = '';
  modelLibrarySearch = '';
  customImageUrlInput = '';
  productImages = signal<string[]>(['/images/jordan-chicago.jpg']);
  productVariants = signal<{ id?: number; size: string; color: string; stock: number }[]>([]);
  batchColorInput = 'Clásico (Blanco/Negro)';
  batchStockInput = 8;
  newVariantSize = '42';
  newVariantColor = 'Clásico (Blanco/Negro)';
  newVariantStock = 6;
  uploadingImage = signal<boolean>(false);
  uploadingModel = signal<boolean>(false);
  uploadFeedback = signal<string | null>(null);
  webhookResponse = signal<any>(null);
  dispatchingWebhook = signal<boolean>(false);
  selectedWebhookEvent = signal<string>('user.registered');
  readonly webhookOptions = [
    { event: 'user.registered', label: 'Bienvenida de Registro', description: 'Correo de confirmación al crear una cuenta nueva.' },
    { event: 'order.created', label: 'Recibo de Compra (con PDF)', description: 'Resumen del pedido con comprobante PDF adjunto.' },
    { event: 'product.created', label: 'Nuevo Lanzamiento', description: 'Aviso de nueva zapatilla publicada en la tienda.' },
    { event: 'stock.low', label: 'Alerta de Stock Crítico', description: 'Notificación de variantes con 3 pares o menos.' },
    { event: 'catalog.3d.synced', label: 'Sincronización de Catálogo', description: 'Evento de sincronización general del inventario.' }
  ];
  notifyEmail = 'admin@urbansole.com';

  modalOpen = signal<boolean>(false);
  editingId = signal<number | null>(null);
  saving = signal<boolean>(false);
  formError = signal<string | null>(null);

  productForm: FormGroup = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(3)]],
    brand: ['Nike', [Validators.required]],
    category: ['Hombre', [Validators.required]],
    price: [150, [Validators.required, Validators.min(1)]],
    original_price: [null],
    model_3d_url: [''],
    description: ['', [Validators.required, Validators.minLength(10)]],
    featured: [true],
    active: [true]
  });

  filteredProducts(): Product[] {
    const q = this.productSearch.trim().toLowerCase();
    const cat = this.productCategoryFilter();
    const onlyActive = this.productStatusFilter() === 'active';
    return this.products().filter(p => {
      if (onlyActive && !p.active) return false;
      if (cat !== 'all' && p.category !== cat) return false;
      if (q && !p.name.toLowerCase().includes(q) && !p.brand.toLowerCase().includes(q)) return false;
      return true;
    });
  }

  filteredOrders(): Order[] {
    const q = this.orderSearch.trim().toLowerCase().replace(/^#/, '');
    const st = this.orderStatusFilter();
    return this.orders().filter(o => {
      if (st !== 'all' && o.status !== st) return false;
      if (!q) return true;
      const haystack = [
        String(o.id),
        String(o.user_order_number || ''),
        o.recipient,
        o.customer_name || '',
        o.customer_email || '',
        o.user?.name || '',
        o.user?.email || '',
        ...(o.items || []).map(i => i.product_name)
      ].join(' ').toLowerCase();
      return haystack.includes(q);
    });
  }

  countOrdersByStatus(status: OrderStatus): number {
    return this.orders().filter(o => o.status === status).length;
  }

  quickRestock(variantId: number, amount: number): void {
    this.restockingVariantId.set(variantId);
    this.restockFeedback.set(null);

    this.adminService.restockVariant(variantId, amount).subscribe({
      next: res => {
        this.restockingVariantId.set(null);
        this.restockFeedback.set(res.message);
        this.loadAll();
      },
      error: () => {
        this.restockingVariantId.set(null);
      }
    });
  }

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.productService.getAdminStats().subscribe({
      next: s => this.stats.set(s)
    });
    this.productService.getProducts({ per_page: 50, include_inactive: true }).subscribe({
      next: res => this.products.set(res.data || [])
    });
    this.adminService.orders().subscribe({
      next: res => this.orders.set(res.data || [])
    });
    this.loadUsers();
    this.productService.getModels3d().subscribe({
      next: res => this.models3d.set(res.models || [])
    });
    this.adminService.mediaLibrary().subscribe({
      next: res => this.libraryImages.set(res.images || [])
    });
  }

  loadUsers(page = 1): void {
    const f = this.userFilter();
    this.adminService.users({
      search: this.userSearch.trim() || undefined,
      role: f === 'admin' || f === 'customer' ? f : undefined,
      status: f === 'banned' ? 'banned' : undefined,
      page
    }).subscribe({
      next: res => {
        this.users.set(res.data || []);
        this.usersTotal.set(res.total ?? (res.data?.length || 0));
      }
    });
  }

  setUserFilter(filter: 'all' | 'admin' | 'customer' | 'banned'): void {
    this.userFilter.set(filter);
    this.loadUsers(1);
  }

  toggleRole(user: User): void {
    const nextRole: 'admin' | 'customer' = user.role === 'admin' ? 'customer' : 'admin';
    this.updatingUserId.set(user.id);
    this.userActionFeedback.set(null);
    this.userActionError.set(null);

    this.adminService.updateUserRole(user.id, nextRole).subscribe({
      next: res => {
        this.updatingUserId.set(null);
        this.userActionFeedback.set(res.message);
        this.loadUsers();
      },
      error: err => {
        this.updatingUserId.set(null);
        this.userActionError.set(err.error?.message || 'No se pudo actualizar el rol del usuario.');
      }
    });
  }

  openBanModal(user: User): void {
    this.banReason = '';
    this.userActionFeedback.set(null);
    this.userActionError.set(null);
    this.banTargetUser.set(user);
  }

  closeBanModal(): void {
    this.banTargetUser.set(null);
    this.banReason = '';
  }

  confirmBan(): void {
    const target = this.banTargetUser();
    if (!target) return;

    this.updatingUserId.set(target.id);
    this.adminService.toggleUserBan(target.id, true, this.banReason.trim() || undefined).subscribe({
      next: res => {
        this.updatingUserId.set(null);
        this.banTargetUser.set(null);
        this.userActionFeedback.set(res.message);
        this.loadUsers();
      },
      error: err => {
        this.updatingUserId.set(null);
        this.banTargetUser.set(null);
        this.userActionError.set(err.error?.message || 'No se pudo suspender al usuario.');
      }
    });
  }

  unbanUser(user: User): void {
    this.updatingUserId.set(user.id);
    this.userActionFeedback.set(null);
    this.userActionError.set(null);

    this.adminService.toggleUserBan(user.id, false).subscribe({
      next: res => {
        this.updatingUserId.set(null);
        this.userActionFeedback.set(res.message);
        this.loadUsers();
      },
      error: err => {
        this.updatingUserId.set(null);
        this.userActionError.set(err.error?.message || 'No se pudo reactivar al usuario.');
      }
    });
  }

  openDeleteUserModal(user: User): void {
    this.userActionFeedback.set(null);
    this.userActionError.set(null);
    this.deleteTargetUser.set(user);
  }

  closeDeleteUserModal(): void {
    this.deleteTargetUser.set(null);
  }

  confirmDeleteUser(): void {
    const target = this.deleteTargetUser();
    if (!target) return;

    this.updatingUserId.set(target.id);
    this.adminService.deleteUser(target.id).subscribe({
      next: res => {
        this.updatingUserId.set(null);
        this.deleteTargetUser.set(null);
        this.userActionFeedback.set(res.message);
        this.loadAll();
      },
      error: err => {
        this.updatingUserId.set(null);
        this.deleteTargetUser.set(null);
        this.userActionError.set(err.error?.message || 'No se pudo eliminar la cuenta del usuario.');
      }
    });
  }

  openUserOrders(user: User): void {
    this.selectedUser.set(user);
    this.selectedUserOrders.set([]);
    this.loadingUserOrders.set(true);

    this.adminService.userOrders(user.id).subscribe({
      next: res => {
        this.selectedUser.set(res.user);
        this.selectedUserOrders.set(res.orders.data || []);
        this.loadingUserOrders.set(false);
      },
      error: () => {
        this.loadingUserOrders.set(false);
      }
    });
  }

  closeUserOrdersModal(): void {
    this.selectedUser.set(null);
    this.selectedUserOrders.set([]);
  }

  downloadOrderReceipt(orderId: number, displayNumber?: number): void {
    this.downloadingReceiptId.set(orderId);
    const num = displayNumber || orderId;
    this.adminService.orderReceipt(orderId).subscribe({
      next: blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `recibo-urbansole-pedido-${num}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        this.downloadingReceiptId.set(null);
      },
      error: () => {
        this.downloadingReceiptId.set(null);
      }
    });
  }

  viewOrderPaymentProof(orderId: number): void {
    this.adminService.orderPaymentProof(orderId).subscribe({
      next: blob => {
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      }
    });
  }

  verifyPayment(order: Order, action: 'approve' | 'reject'): void {
    this.updatingOrderId.set(order.id);
    this.adminService.verifyOrderPayment(order.id, action).subscribe({
      next: () => {
        this.updatingOrderId.set(null);
        this.loadAll();
      },
      error: () => {
        this.updatingOrderId.set(null);
      }
    });
  }

  filteredLibraryImages(): MediaImageOption[] {
    const q = this.imageGallerySearch.trim().toLowerCase();
    const all = this.libraryImages();
    return q ? all.filter(i => i.label.toLowerCase().includes(q) || i.url.toLowerCase().includes(q)) : all;
  }

  filteredModels3d(): Model3dOption[] {
    const q = this.modelLibrarySearch.trim().toLowerCase();
    const all = this.models3d();
    return q
      ? all.filter(m => m.label.toLowerCase().includes(q) || m.category.toLowerCase().includes(q) || m.url.toLowerCase().includes(q))
      : all;
  }

  onImageFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    if (!files.length) return;

    this.uploadingImage.set(true);
    this.uploadFeedback.set(null);
    this.formError.set(null);

    let completed = 0;
    files.forEach(file => {
      this.adminService.uploadAsset(file, 'image').subscribe({
        next: res => {
          this.addImageToProduct(res.url, true);
          this.libraryImages.update(prev => [{ url: res.url, label: res.name, source: 'Subida desde tu PC' }, ...prev]);
          completed++;
          if (completed === files.length) {
            this.uploadingImage.set(false);
            this.uploadFeedback.set(`✓ ${files.length === 1 ? 'Foto subida' : files.length + ' fotos subidas'} desde tu PC y asignada a la zapatilla.`);
            input.value = '';
          }
        },
        error: err => {
          completed++;
          this.uploadingImage.set(false);
          this.formError.set(err.error?.message || `No se pudo subir la imagen ${file.name}.`);
          input.value = '';
        }
      });
    });
  }

  onModelFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.uploadingModel.set(true);
    this.uploadFeedback.set(null);
    this.formError.set(null);

    this.adminService.uploadAsset(file, 'model').subscribe({
      next: res => {
        this.uploadingModel.set(false);
        this.productForm.patchValue({ model_3d_url: res.url });
        const cat = (this.productForm.get('category')?.value || 'Hombre') as 'Hombre' | 'Mujer' | 'Niño';
        this.models3d.update(prev => [
          { url: res.url, image: this.productImages()[0] || '/images/jordan-chicago.jpg', label: `Subido desde PC: ${res.name}`, category: cat },
          ...prev
        ]);
        this.uploadFeedback.set(`✓ Modelo 3D (${res.name}) subido desde tu PC y cargado en el visor 360°.`);
        input.value = '';
      },
      error: err => {
        this.uploadingModel.set(false);
        this.formError.set(err.error?.message || 'No se pudo subir el archivo 3D (.glb).');
        input.value = '';
      }
    });
  }

  addImageToProduct(url: string, makeCover = false): void {
    const clean = url.trim();
    if (!clean) return;
    this.productImages.update(prev => {
      const without = prev.filter(item => item !== clean);
      return makeCover ? [clean, ...without] : [...without, clean];
    });
  }

  addCustomImageUrl(): void {
    const clean = this.customImageUrlInput.trim();
    if (!clean) return;
    this.addImageToProduct(clean, this.productImages().length === 0);
    this.customImageUrlInput = '';
  }

  setAsCoverImage(index: number): void {
    this.productImages.update(prev => {
      if (index <= 0 || index >= prev.length) return prev;
      const picked = prev[index];
      return [picked, ...prev.filter((_, i) => i !== index)];
    });
  }

  removeProductImage(index: number): void {
    this.productImages.update(prev => prev.filter((_, i) => i !== index));
  }

  setModelSourceTab(tab: 'upload' | 'library' | 'url' | 'none'): void {
    this.modelSourceTab.set(tab);
    if (tab === 'none') {
      this.productForm.patchValue({ model_3d_url: '' });
    }
  }

  selectLibraryModel(m: Model3dOption): void {
    this.productForm.patchValue({ model_3d_url: m.url });
    if (this.productImages().length === 0 && m.image) {
      this.productImages.set([m.image]);
    }
  }

  useSuggestedModelPhoto(imageUrl: string, event: Event): void {
    event.stopPropagation();
    this.addImageToProduct(imageUrl, true);
  }

  totalVariantsStock(): number {
    return this.productVariants().reduce((acc, v) => acc + Math.max(0, Number(v.stock) || 0), 0);
  }

  generateCategorySizes(): void {
    const cat = this.productForm.get('category')?.value || 'Hombre';
    const sizes = cat === 'Mujer'
      ? ['35', '36', '37', '38', '39', '40', '41']
      : (cat === 'Niño' ? ['26', '27', '28', '29', '30', '31', '32', '33', '34', '35'] : ['38', '39', '40', '41', '42', '43', '44', '45']);
    const color = this.batchColorInput.trim() || 'Clásico';
    const stock = Math.max(0, Number(this.batchStockInput) || 8);

    this.productVariants.update(prev => {
      const next = [...prev];
      sizes.forEach(sz => {
        const exists = next.some(v => v.size === sz && v.color.toLowerCase() === color.toLowerCase());
        if (!exists) {
          next.push({ size: sz, color, stock });
        }
      });
      return next;
    });
  }

  addManualVariant(): void {
    const size = this.newVariantSize.trim();
    const color = this.newVariantColor.trim();
    const stock = Math.max(0, Number(this.newVariantStock) || 0);
    if (!size || !color) return;

    this.productVariants.update(prev => {
      const idx = prev.findIndex(v => v.size === size && v.color.toLowerCase() === color.toLowerCase());
      if (idx >= 0) {
        const copy = [...prev];
        copy[idx] = { ...copy[idx], stock };
        return copy;
      }
      return [...prev, { size, color, stock }];
    });
  }

  removeVariantRow(index: number): void {
    this.productVariants.update(prev => prev.filter((_, i) => i !== index));
  }

  clearAllVariants(): void {
    this.productVariants.set([]);
  }

  onBackdropMouseDown(event: MouseEvent): void {
    this.backdropMouseDown = event.target === event.currentTarget;
  }

  onBackdropClick(event: MouseEvent, modal: 'product' | 'userOrders' | 'ban' | 'deleteUser'): void {
    const shouldClose = this.backdropMouseDown && event.target === event.currentTarget;
    this.backdropMouseDown = false;
    if (!shouldClose) return;

    if (modal === 'product') {
      this.closeModal();
    } else if (modal === 'userOrders') {
      this.closeUserOrdersModal();
    } else if (modal === 'ban') {
      this.closeBanModal();
    } else {
      this.closeDeleteUserModal();
    }
  }

  asNumber(value: number | string | undefined | null): number {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? n : 0;
  }

  asOrderStatus(status: string): OrderStatus {
    return status as OrderStatus;
  }

  getOrderStatusCount(status: OrderStatus): number {
    const match = this.stats()?.orders_by_status?.find(row => row.status === status);
    return match ? Number(match.count) : 0;
  }

  getTopSellerWidth(unitsSold: number): number {
    const list = this.stats()?.top_selling_products || [];
    const maxUnits = Math.max(...list.map(i => i.units_sold), 1);
    return Math.max(12, Math.round((unitsSold / maxUnits) * 100));
  }

  getOrderTransitions(status: OrderStatus): OrderStatus[] {
    const map: Record<OrderStatus, OrderStatus[]> = {
      pending: ['confirmed', 'cancelled'],
      confirmed: ['shipped', 'cancelled'],
      shipped: ['delivered'],
      delivered: [],
      cancelled: []
    };
    return map[status] || [];
  }

  changeOrderStatus(order: Order, status: OrderStatus): void {
    this.updatingOrderId.set(order.id);
    this.adminService.status(order.id, status).subscribe({
      next: () => {
        this.updatingOrderId.set(null);
        this.loadAll();
      },
      error: () => {
        this.updatingOrderId.set(null);
      }
    });
  }

  openCreateModal(): void {
    this.editingId.set(null);
    this.formError.set(null);
    this.uploadFeedback.set(null);
    this.imageSourceTab.set('upload');
    this.modelSourceTab.set('upload');
    this.productImages.set([]);
    this.batchColorInput = 'Clásico (Blanco/Negro)';
    this.batchStockInput = 8;
    this.productVariants.set([
      { size: '39', color: 'Clásico (Blanco/Negro)', stock: 8 },
      { size: '40', color: 'Clásico (Blanco/Negro)', stock: 8 },
      { size: '41', color: 'Clásico (Blanco/Negro)', stock: 8 },
      { size: '42', color: 'Clásico (Blanco/Negro)', stock: 8 },
      { size: '43', color: 'Clásico (Blanco/Negro)', stock: 8 },
      { size: '44', color: 'Clásico (Blanco/Negro)', stock: 8 }
    ]);
    this.productForm.reset({
      name: '',
      brand: 'Nike',
      category: 'Hombre',
      price: 160,
      original_price: null,
      model_3d_url: '',
      description: 'Zapatilla urbana de diseño exclusivo y materiales premium.',
      featured: true,
      active: true
    });
    this.modalOpen.set(true);
  }

  openEditModal(p: Product): void {
    this.editingId.set(p.id);
    this.formError.set(null);
    this.uploadFeedback.set(null);
    this.imageSourceTab.set('upload');
    this.modelSourceTab.set(p.model_3d_url ? 'library' : 'none');
    this.productImages.set(p.images?.length ? [...p.images] : ['/images/jordan-chicago.jpg']);
    this.productVariants.set(
      (p.variants || []).map(v => ({
        id: v.id,
        size: v.size,
        color: v.color,
        stock: Number(v.stock)
      }))
    );
    this.productForm.patchValue({
      name: p.name,
      brand: p.brand,
      category: p.category,
      price: p.price,
      original_price: p.original_price || null,
      model_3d_url: p.model_3d_url || '',
      description: p.description,
      featured: p.featured,
      active: p.active
    });
    this.modalOpen.set(true);
  }

  closeModal(): void {
    this.modalOpen.set(false);
  }

  saveProduct(): void {
    if (this.productForm.invalid) {
      this.productForm.markAllAsTouched();
      this.formError.set('Por favor completá nombre, marca, categoría, precio y descripción (mínimo 10 caracteres).');
      return;
    }

    const images = this.productImages().filter(u => u.trim() !== '');
    if (images.length === 0) {
      this.formError.set('Subí al menos una foto desde tu PC o seleccioná una de la galería.');
      return;
    }

    const variants = this.productVariants()
      .map(v => ({
        ...(v.id ? { id: v.id } : {}),
        size: String(v.size).trim(),
        color: String(v.color).trim(),
        stock: Math.max(0, Math.floor(Number(v.stock) || 0))
      }))
      .filter(v => v.size !== '' && v.color !== '');

    if (variants.length === 0) {
      this.formError.set('Agregá al menos un talle y color con stock para la zapatilla.');
      return;
    }

    this.saving.set(true);
    this.formError.set(null);
    const rawForm = this.productForm.value;
    const modelUrl = this.modelSourceTab() === 'none'
      ? null
      : (rawForm.model_3d_url?.trim() || null);

    const payload: any = {
      ...rawForm,
      model_3d_url: modelUrl,
      images,
      variants
    };
    const id = this.editingId();

    const req$ = id
      ? this.productService.updateProduct(id, payload)
      : this.productService.createProduct(payload);

    req$.subscribe({
      next: () => {
        this.saving.set(false);
        this.modalOpen.set(false);
        this.loadAll();
      },
      error: err => {
        this.saving.set(false);
        const firstValidationErr = err.error?.errors ? Object.values(err.error.errors).flat()[0] : null;
        this.formError.set((firstValidationErr as string) || err.error?.message || 'Error al guardar la zapatilla.');
      }
    });
  }

  deleteProduct(id: number): void {
    this.productService.deleteProduct(id).subscribe({
      next: () => this.loadAll()
    });
  }

  selectedWebhookOption() {
    return this.webhookOptions.find(o => o.event === this.selectedWebhookEvent()) || this.webhookOptions[0];
  }

  triggerWebhook(event: string): void {
    this.dispatchingWebhook.set(true);
    this.productService.sendNotification(event, this.notifyEmail).subscribe({
      next: res => {
        this.dispatchingWebhook.set(false);
        this.webhookResponse.set(res);
      },
      error: () => {
        this.dispatchingWebhook.set(false);
      }
    });
  }
}
