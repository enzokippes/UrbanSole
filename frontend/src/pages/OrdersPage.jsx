import { useState, useEffect } from 'react';
import { Package, Loader2, ArrowRight } from 'lucide-react';
import { ordersApi } from '../api';
import { Link } from 'react-router-dom';

const STATUS_LABELS = {
  pending: 'Pendiente',
  confirmed: 'Confirmado',
  shipped: 'Enviado',
  delivered: 'Entregado',
  cancelled: 'Cancelado',
};

const STATUS_COLORS = {
  pending: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20',
  confirmed: 'text-blue-400 bg-blue-400/10 border-blue-400/20',
  shipped: 'text-purple-400 bg-purple-400/10 border-purple-400/20',
  delivered: 'text-green-400 bg-green-400/10 border-green-400/20',
  cancelled: 'text-red-400 bg-red-400/10 border-red-400/20',
};

export default function OrdersPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    ordersApi.getAll()
      .then(res => setOrders(res.data))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen pt-20 flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-white/30 animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-20">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 className="text-4xl font-black text-white tracking-tight mb-10">Mis Pedidos</h1>

        {orders.length === 0 ? (
          <div className="text-center py-24">
            <Package className="w-16 h-16 text-white/20 mx-auto mb-4" />
            <p className="text-white/40 text-lg mb-6">No tenés pedidos todavía</p>
            <Link
              to="/catalog"
              className="inline-flex items-center gap-2 bg-white text-black font-bold px-6 py-3 rounded-full"
            >
              Ir al catálogo <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        ) : (
          <div className="space-y-4">
            {orders.map(order => (
              <div key={order.id} className="bg-[#111] rounded-2xl border border-white/10 p-6">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <p className="text-white font-bold text-lg">Pedido #{order.id}</p>
                    <p className="text-white/40 text-xs mt-0.5">
                      {new Date(order.created_at).toLocaleDateString('es-AR', {
                        year: 'numeric', month: 'long', day: 'numeric',
                      })}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className={`text-xs font-semibold px-3 py-1.5 rounded-full border ${STATUS_COLORS[order.status]}`}>
                      {STATUS_LABELS[order.status]}
                    </span>
                    <span className="text-white font-bold">${order.total}</span>
                  </div>
                </div>

                {/* Items */}
                {order.items && (
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    {order.items.map(item => (
                      <div key={item.id} className="text-xs text-white/50 bg-white/3 rounded-lg px-3 py-2">
                        <p className="text-white font-medium truncate">{item.product_name}</p>
                        <p>T{item.size} · {item.color} · ×{item.quantity}</p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
