export interface User {
  id: number;
  name: string;
  email: string;
  role: 'customer' | 'admin';
  banned_at?: string | null;
  ban_reason?: string | null;
  orders_count?: number;
  total_spent?: number | string | null;
  created_at?: string;
}

export interface AuthResponse {
  user: User;
  token: string;
  message?: string;
}
