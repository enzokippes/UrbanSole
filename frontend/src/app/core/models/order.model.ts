export type OrderStatus = 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled';
export interface OrderItem { id: number; product_name: string; size: string; color: string; quantity: number; unit_price: number | string; }
export interface Order { id: number; status: OrderStatus; recipient: string; phone: string; address: string; city: string; postal_code: string; total: number | string; created_at: string; items: OrderItem[]; user?: { name: string; email: string }; }
export interface Page<T> { data: T[]; current_page: number; last_page: number; total: number; }
export interface Checkout { checkout_key: string; recipient: string; phone: string; address: string; city: string; postal_code: string; items: { product_id: number; size: string; color: string; quantity: number }[]; }
export const ORDER_LABELS: Record<OrderStatus, string> = { pending: 'Pendiente', confirmed: 'Confirmado', shipped: 'Enviado', delivered: 'Entregado', cancelled: 'Cancelado' };
