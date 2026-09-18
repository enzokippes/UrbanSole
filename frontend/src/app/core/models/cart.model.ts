import { Product } from './product.model';

export interface CartItem {
  id?: number;
  product_id: number;
  product: Product;
  size: string;
  color: string;
  quantity: number;
  price: number;
}
