export interface ProductVariant {
  id: number;
  product_id: number;
  size: string;
  color: string;
  stock: number;
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  brand: string;
  description: string;
  price: number;
  original_price?: number | null;
  category: 'Hombre' | 'Mujer' | 'Niño';
  model_3d_url?: string | null;
  color_models?: Record<string, string> | null;
  images?: string[];
  color_images?: Record<string, string>;
  colors?: string[];
  tags?: string[];
  featured: boolean;
  active: boolean;
  available_sizes?: string[];
  available_colors?: string[];
  variants?: ProductVariant[];
  created_at?: string;
  updated_at?: string;
}

export interface PaginatedProducts {
  data: Product[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number;
  to?: number;
}

export interface ProductFilters {
  search?: string;
  category?: string;
  featured?: boolean;
  sizes?: string[];
  colors?: string[];
  min_price?: string | number;
  max_price?: string | number;
  sort?: string;
  page?: number;
  per_page?: number;
  include_inactive?: boolean;
}

export interface Model3dOption {
  url: string;
  label: string;
  category: 'Hombre' | 'Mujer' | 'Niño';
}

export interface AdminStats {
  total_users: number;
  total_products: number;
  active_products: number;
  models_3d_count: number;
  total_stock: number;
  by_category: Record<string, number>;
  message: string;
}
