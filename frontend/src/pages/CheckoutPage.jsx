import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Check, Loader2, MapPin, Phone, User, Mail, CreditCard } from 'lucide-react';
import { ordersApi } from '../api';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';

export default function CheckoutPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const { items, subtotal, clearCart } = useCart();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [step, setStep] = useState('form'); // 'form' | 'success'
  const [order, setOrder] = useState(null);

  const shipping = subtotal > 100 ? 0 : 9.99;
  const total = subtotal + shipping;

  const [form, setForm] = useState({
    name: user?.name || '',
    address: '',
    city: '',
    postal_code: '',
    phone: '',
  });

  const handleChange = (e) => {
    setForm(f => ({ ...f, [e.target.name]: e.target.value }));
    setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (items.length === 0) {
      setError('Tu carrito está vacío');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const res = await ordersApi.create({
        shipping_address: form,
      });
      setOrder(res.data);
      await clearCart();
      setStep('success');
    } catch (err) {
      setError(err.response?.data?.message || 'Error al procesar el pedido');
    } finally {
      setLoading(false);
    }
  };

  if (step === 'success') {
    return (
      <div className="min-h-screen pt-20 flex items-center justify-center px-4">
        <div className="text-center max-w-md">
          <div className="w-20 h-20 bg-green-500/10 border border-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
            <Check className="w-10 h-10 text-green-400" />
          </div>
          <h1 className="text-4xl font-black text-white mb-3">¡Pedido confirmado!</h1>
          <p className="text-white/50 mb-2">
            Tu pedido #{order?.id} fue recibido con éxito.
          </p>
          <p className="text-white/30 text-sm mb-8">
            Recibirás una confirmación por email a <span className="text-white/50">{user?.email}</span>
          </p>

          {/* Order summary */}
          <div className="bg-[#111] rounded-2xl border border-white/10 p-6 text-left mb-8 space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-white/50">Subtotal</span>
              <span className="text-white">${order?.subtotal}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-white/50">Envío</span>
              <span className="text-white">{+order?.shipping === 0 ? 'Gratis' : `$${order?.shipping}`}</span>
            </div>
            <div className="flex justify-between text-base font-bold border-t border-white/10 pt-2 mt-2">
              <span className="text-white">Total</span>
              <span className="text-white">${order?.total}</span>
            </div>
          </div>

          <div className="flex gap-3">
            <button
              onClick={() => navigate('/catalog')}
              className="flex-1 btn-press bg-white text-black font-bold py-3.5 rounded-xl hover:bg-white/90"
            >
              Seguir comprando
            </button>
            <button
              onClick={() => navigate('/')}
              className="flex-1 border border-white/20 text-white font-medium py-3.5 rounded-xl hover:bg-white/5"
            >
              Inicio
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-20">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <button
          onClick={() => navigate(-1)}
          className="flex items-center gap-2 text-white/50 hover:text-white text-sm mb-8 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          Volver
        </button>

        <h1 className="text-4xl font-black text-white mb-10">Checkout</h1>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Form */}
          <form onSubmit={handleSubmit} className="lg:col-span-2 space-y-6">
            <div className="bg-[#111] rounded-2xl border border-white/10 p-6">
              <h2 className="text-white font-bold text-lg mb-5 flex items-center gap-2">
                <MapPin className="w-5 h-5 text-white/50" />
                Dirección de envío
              </h2>

              <div className="space-y-4">
                <FormField label="Nombre completo" id="checkout-name">
                  <input
                    id="checkout-name"
                    type="text"
                    name="name"
                    value={form.name}
                    onChange={handleChange}
                    required
                    placeholder="Tu nombre completo"
                    className="checkout-input"
                  />
                </FormField>

                <FormField label="Dirección" id="checkout-address">
                  <input
                    id="checkout-address"
                    type="text"
                    name="address"
                    value={form.address}
                    onChange={handleChange}
                    required
                    placeholder="Calle y número"
                    className="checkout-input"
                  />
                </FormField>

                <div className="grid grid-cols-2 gap-4">
                  <FormField label="Ciudad" id="checkout-city">
                    <input
                      id="checkout-city"
                      type="text"
                      name="city"
                      value={form.city}
                      onChange={handleChange}
                      required
                      placeholder="Ciudad"
                      className="checkout-input"
                    />
                  </FormField>
                  <FormField label="Código postal" id="checkout-postal">
                    <input
                      id="checkout-postal"
                      type="text"
                      name="postal_code"
                      value={form.postal_code}
                      onChange={handleChange}
                      required
                      placeholder="C1000"
                      className="checkout-input"
                    />
                  </FormField>
                </div>

                <FormField label="Teléfono" id="checkout-phone">
                  <input
                    id="checkout-phone"
                    type="tel"
                    name="phone"
                    value={form.phone}
                    onChange={handleChange}
                    required
                    placeholder="+54 11 0000-0000"
                    className="checkout-input"
                  />
                </FormField>
              </div>
            </div>

            {/* Payment (simulated) */}
            <div className="bg-[#111] rounded-2xl border border-white/10 p-6">
              <h2 className="text-white font-bold text-lg mb-2 flex items-center gap-2">
                <CreditCard className="w-5 h-5 text-white/50" />
                Pago simulado
              </h2>
              <p className="text-white/40 text-sm mb-4">
                Este es un checkout de prueba. No se procesarán cargos reales.
              </p>
              <div className="bg-white/5 rounded-xl px-4 py-3 border border-dashed border-white/15 text-center">
                <p className="text-white/50 text-sm">✓ Pago simulado activado</p>
              </div>
            </div>

            {error && (
              <div className="bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
                <p className="text-red-400 text-sm">{error}</p>
              </div>
            )}

            <button
              id="confirm-order-btn"
              type="submit"
              disabled={loading || items.length === 0}
              className="btn-press w-full bg-white text-black font-bold py-4 rounded-2xl flex items-center justify-center gap-2 hover:bg-white/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-base"
            >
              {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : <Check className="w-5 h-5" />}
              {loading ? 'Procesando...' : `Confirmar pedido · $${total.toFixed(2)}`}
            </button>
          </form>

          {/* Order summary */}
          <div>
            <div className="bg-[#111] rounded-2xl border border-white/10 p-6 sticky top-24">
              <h2 className="text-white font-bold text-lg mb-5">Resumen</h2>

              <div className="space-y-4 mb-5">
                {items.map(item => (
                  <div key={item.id} className="flex gap-3">
                    <div className="w-12 h-12 bg-white/5 rounded-lg overflow-hidden flex-shrink-0">
                      {item.product?.images?.[0] && (
                        <img src={item.product.images[0]} alt="" className="w-full h-full object-cover" />
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-white text-xs font-semibold truncate">{item.product?.name}</p>
                      <p className="text-white/40 text-xs">T{item.variant?.size} · {item.variant?.color}</p>
                      <p className="text-white text-xs font-bold mt-0.5">
                        ${(item.product?.price * item.quantity).toFixed(2)}
                        {item.quantity > 1 && <span className="text-white/40 font-normal"> ×{item.quantity}</span>}
                      </p>
                    </div>
                  </div>
                ))}
              </div>

              <div className="border-t border-white/10 pt-4 space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-white/50">Subtotal</span>
                  <span className="text-white">${subtotal.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-white/50">Envío</span>
                  <span className="text-white">{shipping === 0 ? 'Gratis' : `$${shipping.toFixed(2)}`}</span>
                </div>
                <div className="flex justify-between font-bold text-base border-t border-white/10 pt-2">
                  <span className="text-white">Total</span>
                  <span className="text-white">${total.toFixed(2)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <style>{`
        .checkout-input {
          width: 100%;
          background: rgba(255,255,255,0.05);
          border: 1px solid rgba(255,255,255,0.1);
          color: white;
          border-radius: 12px;
          padding: 12px 16px;
          font-size: 14px;
          outline: none;
          transition: border-color 0.2s;
        }
        .checkout-input::placeholder { color: rgba(255,255,255,0.2); }
        .checkout-input:focus { border-color: rgba(255,255,255,0.3); }
      `}</style>
    </div>
  );
}

function FormField({ label, id, children }) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="text-white/50 text-xs font-medium">{label}</label>
      {children}
    </div>
  );
}
