import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { CheckoutComponent } from './checkout.component';
import { CartService } from '../../core/services/cart.service';
import { OrderService } from '../../core/services/order.service';
import { AuthService } from '../../core/services/auth.service';
describe('Checkout', () => {
  function setup() {
    sessionStorage.clear();
    const cart = { items: () => [{ product_id: 1, size: '40', color: 'Negro', quantity: 2, price: 1 }], clearCart: vi.fn(), toggleCart: vi.fn() };
    const orders = { create: vi.fn() }; const router = { navigate: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: CartService, useValue: cart }, { provide: OrderService, useValue: orders }, { provide: AuthService, useValue: { currentUser: () => ({ name: 'Cliente' }) } }, { provide: Router, useValue: router }] });
    return { cart, orders, router, component: TestBed.runInInjectionContext(() => new CheckoutComponent()) };
  }
  it('conserva el carrito y la clave cuando falla y reintenta', () => {
    const { cart, orders, component } = setup();
    orders.create.mockReturnValue(throwError(() => ({ error: { errors: { items: ['Sin stock'] } } })));
    component.submit(); const first = orders.create.mock.calls[0][0]; component.submit();
    expect(cart.clearCart).not.toHaveBeenCalled(); expect(component.error()).toBe('Sin stock');
    expect(orders.create.mock.calls[1][0].checkout_key).toBe(first.checkout_key);
    expect(first.items[0]).not.toHaveProperty('price');
  });
  it('limpia el carrito solo después de guardar el pedido', () => {
    const { cart, orders, router, component } = setup(); orders.create.mockReturnValue(of({ id: 12 }));
    component.submit(); expect(cart.clearCart).toHaveBeenCalledOnce(); expect(router.navigate).toHaveBeenCalledWith(['/orders', 12]);
  });
});
