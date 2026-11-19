export interface ProductVariant {
  id: number;
  product_id: number;
  size: string;
  color: string;
  stock: number;
}

export interface ProductReview {
  id: number;
  product_id: number;
  user_id?: number | null;
  author_name: string;
  rating: number;
  comment: string;
  verified_purchase: boolean;
  created_at?: string;
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
  reviews_avg_rating?: number | null;
  reviews_count?: number;
  reviews?: ProductReview[];
  can_review?: boolean;
  review_eligibility?: 'guest' | 'can_review' | 'already_reviewed' | 'pending_delivery' | 'no_purchase';
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
  image?: string;
  label: string;
  category: 'Hombre' | 'Mujer' | 'Niño';
}

export interface TopSellingProduct {
  product_id: number | null;
  product_name: string;
  brand?: string | null;
  category?: string | null;
  image?: string | null;
  units_sold: number;
  revenue: number;
  orders_count: number;
}

export interface TopSizeStat {
  size: string;
  units_sold: number;
}

export interface CategorySalesStat {
  units_sold: number;
  revenue: number;
}

export interface LowStockItem {
  variant_id: number;
  product_id: number;
  product_name: string;
  category?: string | null;
  image?: string | null;
  size: string;
  color: string;
  stock: number;
}

export interface RecentOrderSummary {
  id: number;
  user_order_number?: number;
  customer_name: string;
  customer_email?: string | null;
  user_deleted?: boolean;
  status: string;
  total: number;
  items_count: number;
  items_summary?: string[];
  created_at?: string | null;
}

export interface AdminStats {
  total_users: number;
  customers_count?: number;
  total_products: number;
  active_products: number;
  models_3d_count: number;
  total_stock: number;
  total_orders?: number;
  pending_orders?: number;
  pending_revenue?: number;
  units_sold?: number;
  sales_total?: number | string;
  average_order?: number | string;
  low_stock_variants?: number;
  orders_by_status?: { status: string; count: number }[];
  by_category: Record<string, number>;
  sales_by_category?: Record<string, CategorySalesStat>;
  top_selling_products?: TopSellingProduct[];
  top_sizes?: TopSizeStat[];
  low_stock_items?: LowStockItem[];
  recent_orders?: RecentOrderSummary[];
  message: string;
}
