import { X, Minus, Plus, ShoppingBag, ArrowRight } from 'lucide-react';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import { Link, useNavigate } from 'react-router-dom';

export default function CartSidebar() {
  const { items, subtotal, count, loading, isOpen, setIsOpen, updateQuantity, removeItem } = useCart();
  const { user } = useAuth();
  const navigate = useNavigate();

  const shipping = subtotal > 100 ? 0 : 9.99;
  const total = subtotal + shipping;

  const handleCheckout = () => {
    setIsOpen(false);
    navigate('/checkout');
  };

  return (
    <>
      {/* Backdrop */}
      <div
        className={`fixed inset-0 bg-black/70 backdrop-blur-sm z-50 transition-opacity duration-300 ${
          isOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
        onClick={() => setIsOpen(false)}
      />

      {/* Sidebar */}
      <div className={`fixed right-0 top-0 h-full w-full max-w-sm bg-[#111] border-l border-white/10 z-50 flex flex-col transition-transform duration-300 ease-out ${
        isOpen ? 'translate-x-0' : 'translate-x-full'
      }`}>
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-white/10">
          <div className="flex items-center gap-2">
            <ShoppingBag className="w-5 h-5 text-white" />
            <h2 className="text-white font-bold text-lg">Carrito</h2>
            {count > 0 && (
              <span className="bg-white text-black text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">
                {count}
              </span>
            )}
          </div>
          <button
            id="close-cart-btn"
            onClick={() => setIsOpen(false)}
            className="text-white/50 hover:text-white transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Items */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {loading ? (
            <div className="flex items-center justify-center h-32">
              <div className="w-6 h-6 border-2 border-white/20 border-t-white rounded-full animate-spin" />
            </div>
          ) : items.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-40 text-center">
              <ShoppingBag className="w-12 h-12 text-white/20 mb-3" />
              <p className="text-white/40 font-medium">Tu carrito está vacío</p>
              <p className="text-white/25 text-sm mt-1">Agregá zapatillas para comenzar</p>
              <button
                onClick={() => { setIsOpen(false); navigate('/catalog'); }}
                className="mt-4 text-sm text-white/60 hover:text-white underline underline-offset-2 transition-colors"
              >
                Ver catálogo
              </button>
            </div>
          ) : (
            items.map((item) => (
              <CartItem
                key={item.id}
                item={item}
                onUpdateQuantity={updateQuantity}
                onRemove={removeItem}
              />
            ))
          )}
        </div>

        {/* Footer */}
        {items.length > 0 && (
          <div className="p-6 border-t border-white/10 space-y-4">
            {/* Subtotals */}
            <div className="space-y-2">
              <div className="flex justify-between text-sm text-white/50">
                <span>Subtotal</span>
                <span>${subtotal.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-sm text-white/50">
                <span>Envío</span>
                <span>{shipping === 0 ? 'Gratis' : `$${shipping.toFixed(2)}`}</span>
              </div>
              {subtotal <= 100 && (
                <p className="text-xs text-white/30">
                  Envío gratis en pedidos superiores a $100
                </p>
              )}
              <div className="flex justify-between text-white font-bold text-lg pt-2 border-t border-white/10">
                <span>Total</span>
                <span>${total.toFixed(2)}</span>
              </div>
            </div>

            {/* CTA */}
            {user ? (
              <button
                id="checkout-btn"
                onClick={handleCheckout}
                className="btn-press w-full bg-white text-black font-bold py-3.5 rounded-xl flex items-center justify-center gap-2 hover:bg-white/90 transition-colors"
              >
                Ir al checkout
                <ArrowRight className="w-4 h-4" />
              </button>
            ) : (
              <div className="space-y-2">
                <p className="text-white/40 text-xs text-center">
                  Iniciá sesión para completar tu compra
                </p>
                <Link
                  to="/login"
                  onClick={() => setIsOpen(false)}
                  className="block w-full bg-white text-black font-bold py-3.5 rounded-xl text-center hover:bg-white/90 transition-colors"
                >
                  Ingresar
                </Link>
              </div>
            )}
          </div>
        )}
      </div>
    </>
  );
}

function CartItem({ item, onUpdateQuantity, onRemove }) {
  const image = item.product?.images?.[0];

  return (
    <div className="flex gap-3 p-3 bg-white/3 rounded-xl border border-white/8">
      {/* Product Image */}
      <div className="w-16 h-16 rounded-lg bg-white/5 overflow-hidden flex-shrink-0">
        {image ? (
          <img src={image} alt={item.product?.name} className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full bg-white/10" />
        )}
      </div>

      {/* Info */}
      <div className="flex-1 min-w-0">
        <p className="text-white text-sm font-semibold truncate">{item.product?.name}</p>
        <p className="text-white/40 text-xs mt-0.5">
          Talle {item.variant?.size} · {item.variant?.color}
        </p>
        <p className="text-white font-bold text-sm mt-1">
          ${(item.product?.price * item.quantity).toFixed(2)}
        </p>
      </div>

      {/* Quantity + Remove */}
      <div className="flex flex-col items-end justify-between">
        <button
          onClick={() => onRemove(item.id)}
          className="text-white/30 hover:text-white/70 transition-colors"
        >
          <X className="w-3.5 h-3.5" />
        </button>
        <div className="flex items-center gap-2 bg-white/8 rounded-lg p-1">
          <button
            onClick={() => onUpdateQuantity(item.id, item.quantity - 1)}
            disabled={item.quantity <= 1}
            className="w-5 h-5 flex items-center justify-center text-white/60 hover:text-white disabled:opacity-30"
          >
            <Minus className="w-3 h-3" />
          </button>
          <span className="text-white text-xs font-medium w-4 text-center">{item.quantity}</span>
          <button
            onClick={() => onUpdateQuantity(item.id, item.quantity + 1)}
            disabled={item.quantity >= 10}
            className="w-5 h-5 flex items-center justify-center text-white/60 hover:text-white disabled:opacity-30"
          >
            <Plus className="w-3 h-3" />
          </button>
        </div>
      </div>
    </div>
  );
}
