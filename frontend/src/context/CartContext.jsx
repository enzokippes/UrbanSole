import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { cartApi } from '../api';
import { useAuth } from './AuthContext';

const CartContext = createContext(null);

export function CartProvider({ children }) {
  const { user } = useAuth();
  const [items, setItems] = useState([]);
  const [subtotal, setSubtotal] = useState(0);
  const [count, setCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);

  const fetchCart = useCallback(async () => {
    if (!user) {
      setItems([]);
      setSubtotal(0);
      setCount(0);
      return;
    }
    try {
      setLoading(true);
      const res = await cartApi.get();
      setItems(res.data.items || []);
      setSubtotal(res.data.subtotal || 0);
      setCount(res.data.count || 0);
    } catch (err) {
      console.error('Error fetching cart:', err);
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    fetchCart();
  }, [fetchCart]);

  const addToCart = async (productId, variantId, quantity = 1) => {
    if (!user) return { error: 'login_required' };
    try {
      await cartApi.add({
        product_id: productId,
        product_variant_id: variantId,
        quantity,
      });
      await fetchCart();
      setIsOpen(true);
      return { success: true };
    } catch (err) {
      return { error: err.response?.data?.message || 'Error adding to cart' };
    }
  };

  const updateQuantity = async (itemId, quantity) => {
    try {
      await cartApi.update(itemId, quantity);
      await fetchCart();
    } catch (err) {
      console.error(err);
    }
  };

  const removeItem = async (itemId) => {
    try {
      await cartApi.remove(itemId);
      await fetchCart();
    } catch (err) {
      console.error(err);
    }
  };

  const clearCart = async () => {
    try {
      await cartApi.clear();
      setItems([]);
      setSubtotal(0);
      setCount(0);
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <CartContext.Provider value={{
      items, subtotal, count, loading,
      isOpen, setIsOpen,
      addToCart, updateQuantity, removeItem, clearCart, fetchCart
    }}>
      {children}
    </CartContext.Provider>
  );
}

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart must be used within CartProvider');
  return ctx;
};
