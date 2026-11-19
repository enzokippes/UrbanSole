import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { WishlistService } from '../../core/services/wishlist.service';
import { ProductCardComponent } from '../catalog/components/product-card/product-card.component';

@Component({
  selector: 'app-wishlist',
  standalone: true,
  imports: [CommonModule, RouterLink, ProductCardComponent],
  template: `
    <main class="wishlist-page">
      <div class="container">
        <div class="wishlist-head">
          <div>
            <span class="wishlist-eyebrow">Tu selección personal</span>
            <h1>Mis Favoritos</h1>
            <p class="wishlist-sub">
              {{ wishlist.count() }} {{ wishlist.count() === 1 ? 'modelo guardado' : 'modelos guardados' }} en tu lista.
            </p>
          </div>
          <div class="head-actions">
            <button
              *ngIf="wishlist.count() > 0"
              type="button"
              (click)="wishlist.clear()"
              class="btn-secondary danger-outline"
            >
              Limpiar lista
            </button>
            <a routerLink="/catalog" class="btn-primary">Seguir explorando</a>
          </div>
        </div>

        <div *ngIf="wishlist.count() === 0" class="empty-wishlist">
          <div class="heart-circle">♥</div>
          <h2>Tu lista de favoritos está vacía</h2>
          <p>Tocá el corazón en cualquier zapatilla del catálogo para guardarla acá y compararla después.</p>
          <a routerLink="/catalog" class="btn-primary">Explorar catálogo 3D</a>
        </div>

        <div *ngIf="wishlist.count() > 0" class="wishlist-grid">
          <app-product-card *ngFor="let p of wishlist.items()" [product]="p"></app-product-card>
        </div>
      </div>
    </main>
  `,
  styles: [`
    .wishlist-page { min-height: 100vh; padding: 108px 0 4rem; background: var(--bg-main); }
    .wishlist-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .wishlist-eyebrow { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em; color: #fb7185; }
    h1 { font-size: 2rem; font-weight: 900; color: #fff; margin: .25rem 0; }
    .wishlist-sub { font-size: .92rem; color: rgba(255,255,255,.6); margin: 0; }
    .head-actions { display: flex; gap: .65rem; flex-wrap: wrap; }
    .danger-outline { border-color: rgba(244,63,94,.38); color: #fda4af; }
    .empty-wishlist {
      background: #141418; border: 1px solid rgba(255,255,255,.09); border-radius: 1.25rem;
      padding: 3.5rem 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: .8rem;
    }
    .heart-circle {
      width: 60px; height: 60px; border-radius: 9999px;
      background: rgba(244,63,94,.14); border: 1px solid rgba(244,63,94,.35); color: #fb7185;
      font-size: 1.6rem; display: grid; place-items: center;
    }
    .empty-wishlist h2 { color: #fff; margin: 0; font-size: 1.3rem; }
    .empty-wishlist p { color: rgba(255,255,255,.6); max-width: 440px; margin: 0 0 .4rem; font-size: .92rem; }
    .wishlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(245px, 1fr)); gap: 1.4rem; }
  `]
})
export class WishlistComponent {
  readonly wishlist = inject(WishlistService);
}
