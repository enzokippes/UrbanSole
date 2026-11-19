export type OrderStatus = 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled';
export type DeliveryMethod = 'standard' | 'pickup';
export type PaymentMethod = 'transfer' | 'card' | 'cash';
export type PaymentStatus = 'pending' | 'proof_submitted' | 'paid';

export interface BankTransferInfo {
  alias: string;
  cbu: string;
  holder: string;
  bank_name: string;
}

export interface OrderItem {
  id: number;
  product_name: string;
  size: string;
  color: string;
  quantity: number;
  unit_price: number | string;
}

export interface Order {
  id: number;
  user_id?: number | null;
  user_order_number?: number;
  customer_name?: string | null;
  customer_email?: string | null;
  status: OrderStatus;
  delivery_method?: DeliveryMethod;
  payment_method?: PaymentMethod;
  payment_status?: PaymentStatus;
  has_payment_proof?: boolean;
  payment_reference?: string | null;
  payment_submitted_at?: string | null;
  recipient: string;
  phone: string;
  address: string;
  city: string;
  postal_code: string;
  total: number | string;
  created_at: string;
  items: OrderItem[];
  user?: { name: string; email: string } | null;
}

export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface Checkout {
  checkout_key: string;
  recipient: string;
  phone: string;
  address: string;
  city: string;
  postal_code: string;
  delivery_method?: DeliveryMethod;
  payment_method?: PaymentMethod;
  card_holder?: string;
  card_number?: string;
  card_expiry?: string;
  card_cvv?: string;
  card_installments?: number;
  items: { product_id: number; size: string; color: string; quantity: number }[];
}

export const ORDER_LABELS: Record<OrderStatus, string> = {
  pending: 'Pendiente',
  confirmed: 'Confirmado',
  shipped: 'Enviado',
  delivered: 'Entregado',
  cancelled: 'Cancelado'
};

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  transfer: 'Transferencia bancaria',
  card: 'Tarjeta (Débito / Crédito)',
  cash: 'Efectivo al recibir / retirar'
};

export const PAYMENT_STATUS_LABELS: Record<PaymentStatus, string> = {
  pending: 'Pendiente de pago',
  proof_submitted: 'Comprobante enviado',
  paid: 'Pago acreditado'
};

export const DELIVERY_METHOD_LABELS: Record<DeliveryMethod, string> = {
  standard: 'Envío a domicilio',
  pickup: 'Retiro en tienda'
};
