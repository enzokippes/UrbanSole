export interface User {
  id: number;
  name: string;
  email: string;
  role: 'customer' | 'admin';
  created_at?: string;
}

export interface AuthResponse {
  user: User;
  token: string;
  message?: string;
}
