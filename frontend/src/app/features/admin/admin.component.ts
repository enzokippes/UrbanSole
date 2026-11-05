import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ProductService } from '../../core/services/product.service';
import { AdminStats, Model3dOption, Product } from '../../core/models/product.model';
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
            <span class="admin-tag">Programación IV · UTN Concordia</span>
            <h1 class="admin-title">Panel de Administración & Gestión 3D</h1>
          </div>

          <div class="admin-top-actions">
            <a href="http://localhost:8000/db-viewer" target="_blank" rel="noopener" class="btn-secondary">
              Ver Base de Datos SQL ↗
            </a>
            <a href="http://localhost:8000/api/docs" target="_blank" rel="noopener" class="btn-secondary">
              Documentación Swagger API ↗
            </a>
            <button type="button" (click)="openCreateModal()" class="btn-primary">
              + Nueva Zapatilla 3D
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
            Catálogo CRUD ({{ products().length }})
          </button>
          <button
            type="button"
            (click)="activeTab.set('n8n')"
            class="admin-tab"
            [class.active]="activeTab() === 'n8n'"
          >
            Automatización n8n & Email
          </button>
        </div>

        <!-- TAB 1: DASHBOARD -->
        <div *ngIf="activeTab() === 'dashboard' && stats()" class="dashboard-grid">
          <div class="stat-card">
            <span class="stat-card-label">Total Zapatillas</span>
            <span class="stat-card-val">{{ stats()?.total_products }}</span>
            <span class="stat-card-sub">{{ stats()?.active_products }} activas en tienda</span>
          </div>
          <div class="stat-card">
            <span class="stat-card-label">Modelos 3D (.GLB)</span>
            <span class="stat-card-val">{{ stats()?.models_3d_count }}</span>
            <span class="stat-card-sub">100% fieles al calzado</span>
          </div>
          <div class="stat-card">
            <span class="stat-card-label">Stock Total Variantes</span>
            <span class="stat-card-val">{{ stats()?.total_stock }}</span>
            <span class="stat-card-sub">Pares en inventario</span>
          </div>
          <div class="stat-card">
            <span class="stat-card-label">Usuarios Registrados</span>
            <span class="stat-card-val">{{ stats()?.total_users }}</span>
            <span class="stat-card-sub">Protegidos con Sanctum</span>
          </div>

          <div class="cat-breakdown-card">
            <h3>Distribución por Categoría (Hombre, Mujer y Niño)</h3>
            <div class="cat-bars">
              <div class="cat-bar-item">
                <span>Hombre</span>
                <strong>{{ stats()?.by_category?.['Hombre'] || 0 }} modelos</strong>
              </div>
              <div class="cat-bar-item">
                <span>Mujer</span>
                <strong>{{ stats()?.by_category?.['Mujer'] || 0 }} modelos</strong>
              </div>
              <div class="cat-bar-item">
                <span>Niño</span>
                <strong>{{ stats()?.by_category?.['Niño'] || 0 }} modelos</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: CRUD DE PRODUCTOS -->
        <div *ngIf="activeTab() === 'products'" class="crud-box">
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
                <tr *ngFor="let p of products()">
                  <td>
                    <div class="prod-cell">
                      <img [src]="p.images?.[0] || '/images/jordan-chicago.jpg'" alt="" class="prod-thumb" />
                      <div>
                        <p class="prod-name">{{ p.name }}</p>
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

        <!-- TAB 3: N8N WEBHOOK & EMAIL AUTOMATION (SEMANA 15) -->
        <div *ngIf="activeTab() === 'n8n'" class="n8n-card">
          <div class="n8n-top-bar">
            <div>
              <span class="admin-tag">Semana 15 · Webhooks + Email SMTP/Gmail</span>
              <h3>Automatización Basada en Eventos con n8n</h3>
              <p class="n8n-desc">
                El backend Laravel 11 dispara automáticamente peticiones <code>HTTP POST</code> hacia n8n cuando se registra un cliente o se modifica el catálogo 3D, generando y enviando notificaciones por <strong>Email</strong>.
              </p>
            </div>
            <div class="n8n-endpoint-box">
              <span class="endpoint-label">Endpoint Webhook n8n:</span>
              <code>http://localhost:5678/webhook/urbansole</code>
              <span class="endpoint-sub">Workflow importable: <code>n8n/urbansole-workflow.json</code></span>
            </div>
          </div>

          <div class="n8n-email-config">
            <label for="notifyEmailInput">Email destinatario para la notificación de n8n:</label>
            <input
              id="notifyEmailInput"
              type="email"
              [(ngModel)]="notifyEmail"
              class="input email-input"
              placeholder="admin@urbansole.com"
            />
          </div>

          <div class="n8n-actions">
            <button type="button" (click)="triggerWebhook('user.registered')" [disabled]="dispatchingWebhook()" class="btn-primary">
              📩 Simular Registro Cliente (Email Bienvenida)
            </button>
            <button type="button" (click)="triggerWebhook('product.created')" [disabled]="dispatchingWebhook()" class="btn-secondary">
              👟 Nuevo Lanzamiento 3D (Email Catálogo)
            </button>
            <button type="button" (click)="triggerWebhook('stock.low')" [disabled]="dispatchingWebhook()" class="btn-secondary">
              ⚠️ Alerta de Stock Crítico (Email Admin)
            </button>
            <button type="button" (click)="triggerWebhook('catalog.3d.synced')" [disabled]="dispatchingWebhook()" class="btn-secondary">
              🔄 Sincronizar Catálogo 3D Completo
            </button>
          </div>

          <div *ngIf="webhookResponse()" class="webhook-result-box">
            <div class="webhook-status-bar">
              <span
                class="delivery-badge"
                [class.delivered]="webhookResponse().delivered"
                [class.queued]="!webhookResponse().delivered"
              >
                {{ webhookResponse().delivered ? '✓ Entregado en vivo a n8n (HTTP ' + webhookResponse().http_status + ')' : '⚡ Evento Generado (Modo Resiliente · Iniciá npx n8n para envío en vivo)' }}
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

            <pre class="webhook-json">{{ webhookResponse() | json }}</pre>
          </div>
        </div>
      </div>

      <!-- MODAL REACTIVO CREAR / EDITAR PRODUCTO CON VISTA PREVIA 3D -->
      <div *ngIf="modalOpen()" class="modal-backdrop" (click)="closeModal()">
        <div class="crud-modal" (click)="$event.stopPropagation()">
          <div class="modal-header">
            <h2>{{ editingId() ? 'Editar Zapatilla 3D' : 'Nueva Zapatilla con Modelo 3D' }}</h2>
            <button type="button" (click)="closeModal()" class="close-x">&times;</button>
          </div>

          <form [formGroup]="productForm" (ngSubmit)="saveProduct()" class="modal-grid">
            <div class="form-col">
              <div class="field">
                <label>Nombre del Calzado *</label>
                <input type="text" formControlName="name" class="input" placeholder="Ej: Air Jordan 1 High Chicago" />
              </div>

              <div class="field-row">
                <div class="field">
                  <label>Marca *</label>
                  <input type="text" formControlName="brand" class="input" />
                </div>
                <div class="field">
                  <label>Categoría *</label>
                  <select formControlName="category" class="input">
                    <option value="Hombre">Hombre</option>
                    <option value="Mujer">Mujer</option>
                    <option value="Niño">Niño</option>
                  </select>
                </div>
              </div>

              <div class="field-row">
                <div class="field">
                  <label>Precio ($) *</label>
                  <input type="number" formControlName="price" class="input" />
                </div>
                <div class="field">
                  <label>Precio Anterior ($)</label>
                  <input type="number" formControlName="original_price" class="input" />
                </div>
              </div>

              <div class="field">
                <label>Modelo 3D (.glb) Fiel al Calzado *</label>
                <select formControlName="model_3d_url" class="input">
                  <option *ngFor="let m of models3d()" [value]="m.url">
                    [{{ m.category }}] {{ m.label }}
                  </option>
                </select>
              </div>

              <div class="field">
                <label>Descripción (mínimo 10 caracteres) *</label>
                <textarea rows="3" formControlName="description" class="input"></textarea>
              </div>

              <div *ngIf="formError()" class="form-error-box">{{ formError() }}</div>

              <div class="modal-actions">
                <button type="submit" [disabled]="saving()" class="btn-primary">
                  {{ saving() ? 'Guardando...' : (editingId() ? 'Guardar Cambios (PUT)' : 'Crear Producto (POST)') }}
                </button>
                <button type="button" (click)="closeModal()" class="btn-secondary">Cancelar</button>
              </div>
            </div>

            <!-- Live 3D Preview inside Admin Form -->
            <div class="preview-col">
              <p class="preview-label">Vista Previa 3D en Tiempo Real:</p>
              <app-model-viewer-3d
                [src]="productForm.get('model_3d_url')?.value"
                [alt]="productForm.get('name')?.value || 'Preview 3D'"
                minHeight="320px"
                [showHint]="false"
              ></app-model-viewer-3d>
            </div>
          </form>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .admin-page { min-height: 100vh; padding-top: 84px; background: var(--bg-main); }
    .py-10 { padding-top: 2.5rem; padding-bottom: 4rem; }
    .admin-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .admin-tag { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.15em; color: #4ade80; font-weight: 700; }
    .admin-title { font-size: 2rem; font-weight: 900; color: #fff; margin-top: 0.25rem; }
    .admin-top-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .admin-tabs { display: flex; gap: 0.4rem; background: rgba(255,255,255,0.04); padding: 0.3rem; border-radius: 0.85rem; width: fit-content; margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.08); }
    .admin-tab { background: transparent; border: none; color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 700; padding: 0.55rem 1.1rem; border-radius: 0.65rem; cursor: pointer; }
    .admin-tab.active { background: #fff; color: #000; }
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; }
    .stat-card { background: #141418; border: 1px solid rgba(255,255,255,0.09); border-radius: 1.15rem; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.35rem; }
    .stat-card-label { font-size: 0.78rem; color: rgba(255,255,255,0.5); }
    .stat-card-val { font-size: 2.25rem; font-weight: 900; color: #fff; }
    .stat-card-sub { font-size: 0.74rem; color: #4ade80; }
    .cat-breakdown-card { grid-column: 1 / -1; background: #141418; border: 1px solid rgba(255,255,255,0.09); border-radius: 1.15rem; padding: 1.5rem; }
    .cat-breakdown-card h3 { font-size: 1rem; margin-bottom: 1rem; }
    .cat-bars { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
    .cat-bar-item { background: rgba(255,255,255,0.04); padding: 1rem; border-radius: 0.85rem; display: flex; justify-content: space-between; }
    .crud-box { background: #141418; border: 1px solid rgba(255,255,255,0.09); border-radius: 1.25rem; overflow: hidden; }
    .table-wrap { overflow-x: auto; }
    .crud-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .crud-table th, .crud-table td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .crud-table th { color: rgba(255,255,255,0.45); font-size: 0.75rem; text-transform: uppercase; }
    .prod-cell { display: flex; align-items: center; gap: 0.85rem; }
    .prod-thumb { width: 44px; height: 44px; border-radius: 0.6rem; object-fit: cover; }
    .prod-name { font-weight: 700; color: #fff; }
    .prod-brand { font-size: 0.75rem; color: rgba(255,255,255,0.45); }
    .cat-pill { background: rgba(255,255,255,0.08); padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
    .glb-code { font-size: 0.75rem; color: #38bdf8; background: rgba(56,189,248,0.1); padding: 0.2rem 0.5rem; border-radius: 0.4rem; }
    .row-actions { display: flex; gap: 0.5rem; align-items: center; }
    .action-link, .action-btn { font-size: 0.75rem; font-weight: 700; padding: 0.35rem 0.7rem; border-radius: 0.5rem; cursor: pointer; text-decoration: none; border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.05); color: #fff; }
    .action-btn.delete { color: #f87171; border-color: rgba(248,113,113,0.25); }
    .n8n-card { background: #141418; border: 1px solid rgba(255,255,255,0.09); border-radius: 1.25rem; padding: 2rem; }
    .n8n-top-bar { display: flex; justify-content: space-between; align-items: flex-start; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .n8n-desc { color: rgba(255,255,255,0.65); margin: 0.5rem 0 0; max-width: 650px; line-height: 1.6; }
    .n8n-endpoint-box { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.85rem; padding: 0.9rem 1.15rem; display: flex; flex-direction: column; gap: 0.3rem; }
    .endpoint-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.45); }
    .endpoint-sub { font-size: 0.75rem; color: #38bdf8; }
    .n8n-email-config { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1.25rem; max-width: 420px; }
    .n8n-email-config label { font-size: 0.8rem; color: rgba(255,255,255,0.75); font-weight: 600; }
    .email-input { width: 100%; }
    .n8n-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .webhook-result-box { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .webhook-status-bar { display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; background: rgba(255,255,255,0.03); padding: 0.85rem 1rem; border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.08); }
    .delivery-badge { font-size: 0.75rem; font-weight: 800; padding: 0.3rem 0.75rem; border-radius: 9999px; }
    .delivery-badge.delivered { background: rgba(74,222,128,0.18); color: #4ade80; border: 1px solid rgba(74,222,128,0.4); }
    .delivery-badge.queued { background: rgba(250,204,21,0.15); color: #facc15; border: 1px solid rgba(250,204,21,0.35); }
    .webhook-msg { font-size: 0.82rem; color: rgba(255,255,255,0.8); }
    .email-preview-card { background: #18181f; border-left: 4px solid #4ade80; padding: 1rem 1.25rem; border-radius: 0.75rem; }
    .email-preview-meta p { margin: 0.25rem 0; font-size: 0.85rem; color: #e4e4e7; }
    .webhook-json { background: #09090b; border: 1px solid rgba(74,222,128,0.3); color: #4ade80; padding: 1rem; border-radius: 0.85rem; font-size: 0.8rem; overflow-x: auto; margin: 0; }
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 300; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .crud-modal { background: #141418; border: 1px solid rgba(255,255,255,0.16); border-radius: 1.5rem; max-width: 880px; width: 100%; padding: 1.75rem; max-height: 92vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    .close-x { background: none; border: none; color: #fff; font-size: 1.6rem; cursor: pointer; }
    .modal-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
    @media (min-width: 768px) { .modal-grid { grid-template-columns: 1.15fr 0.85fr; } }
    .form-col { display: flex; flex-direction: column; gap: 0.85rem; }
    .field { display: flex; flex-direction: column; gap: 0.35rem; }
    .field label { font-size: 0.78rem; color: rgba(255,255,255,0.65); font-weight: 600; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .input { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.14); color: #fff; padding: 0.6rem 0.85rem; border-radius: 0.6rem; font-size: 0.875rem; }
    .input option { background: #141418; color: #fff; }
    .preview-col { display: flex; flex-direction: column; gap: 0.5rem; }
    .preview-label { font-size: 0.78rem; color: rgba(255,255,255,0.6); font-weight: 600; }
    .modal-actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; }
    .form-error-box { color: #fca5a5; font-size: 0.8rem; background: rgba(239,68,68,0.15); padding: 0.6rem; border-radius: 0.5rem; }
  `]
})
export class AdminComponent implements OnInit {
  private readonly productService = inject(ProductService);
  private readonly fb = inject(FormBuilder);

  activeTab = signal<'dashboard' | 'products' | 'n8n'>('dashboard');
  stats = signal<AdminStats | null>(null);
  products = signal<Product[]>([]);
  models3d = signal<Model3dOption[]>([]);
  webhookResponse = signal<any>(null);
  dispatchingWebhook = signal<boolean>(false);
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
    model_3d_url: ['/models/jordan-chicago.glb', [Validators.required]],
    description: ['', [Validators.required, Validators.minLength(10)]],
    featured: [true],
    active: [true]
  });

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
    this.productService.getModels3d().subscribe({
      next: res => this.models3d.set(res.models || [])
    });
  }

  openCreateModal(): void {
    this.editingId.set(null);
    this.formError.set(null);
    this.productForm.reset({
      name: '',
      brand: 'Nike',
      category: 'Hombre',
      price: 160,
      original_price: null,
      model_3d_url: '/models/jordan-chicago.glb',
      description: 'Zapatilla urbana con diseño y texturas 3D fieles al modelo original.',
      featured: true,
      active: true
    });
    this.modalOpen.set(true);
  }

  openEditModal(p: Product): void {
    this.editingId.set(p.id);
    this.formError.set(null);
    this.productForm.patchValue({
      name: p.name,
      brand: p.brand,
      category: p.category,
      price: p.price,
      original_price: p.original_price || null,
      model_3d_url: p.model_3d_url || '/models/jordan-chicago.glb',
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
      this.formError.set('Por favor completá todos los campos obligatorios correctamente.');
      return;
    }

    this.saving.set(true);
    this.formError.set(null);
    const payload = this.productForm.value;
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
        this.formError.set(err.error?.message || 'Error al guardar el producto.');
      }
    });
  }

  deleteProduct(id: number): void {
    this.productService.deleteProduct(id).subscribe({
      next: () => this.loadAll()
    });
  }

  triggerWebhook(event: string): void {
    this.dispatchingWebhook.set(true);
    this.productService.triggerN8nWebhook(event, this.notifyEmail).subscribe({
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
