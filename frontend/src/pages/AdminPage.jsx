import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  LayoutDashboard, Package, ShoppingCart, Edit2, Trash2,
  Plus, X, Loader2, Check, TrendingUp, Users
} from 'lucide-react';
import { adminApi } from '../api';
import { useAuth } from '../context/AuthContext';

const STATUS_OPTIONS = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

export default function AdminPage() {
  const navigate = useNavigate();
  const { user, isAdmin } = useAuth();
  const [activeTab, setActiveTab] = useState('stats');
  const [stats, setStats] = useState(null);
  const [products, setProducts] = useState([]);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editProduct, setEditProduct] = useState(null);
  const [editLoading, setEditLoading] = useState(false);

  useEffect(() => {
    if (!isAdmin) {
      navigate('/');
      return;
    }
    loadData();
  }, [isAdmin]);

  useEffect(() => {
    if (activeTab === 'products') loadProducts();
    else if (activeTab === 'orders') loadOrders();
    else loadStats();
  }, [activeTab]);

  const loadData = async () => {
    setLoading(true);
    await loadStats();
    setLoading(false);
  };

  const loadStats = async () => {
    try {
      const res = await adminApi.getStats();
      setStats(res.data);
    } catch {}
  };

  const loadProducts = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getProducts({ per_page: 50 });
      setProducts(res.data.data || []);
    } finally {
      setLoading(false);
    }
  };

  const loadOrders = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getOrders();
      setOrders(res.data.data || []);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteProduct = async (id) => {
    if (!confirm('¿Eliminar este producto?')) return;
    await adminApi.deleteProduct(id);
    setProducts(p => p.filter(x => x.id !== id));
  };

  const handleOrderStatus = async (id, status) => {
    try {
      await adminApi.updateOrderStatus(id, status);
      setOrders(o => o.map(x => x.id === id ? { ...x, status } : x));
    } catch {}
  };

  const handleSaveProduct = async (formData) => {
    setEditLoading(true);
    try {
      if (formData.id) {
        const res = await adminApi.updateProduct(formData.id, formData);
        setProducts(p => p.map(x => x.id === formData.id ? res.data : x));
      } else {
        const res = await adminApi.createProduct(formData);
        setProducts(p => [res.data, ...p]);
      }
      setEditProduct(null);
    } catch (err) {
      alert(err.response?.data?.message || 'Error al guardar');
    } finally {
      setEditLoading(false);
    }
  };

  if (!isAdmin) return null;

  return (
    <div className="min-h-screen pt-20 bg-[#0a0a0a]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-black text-white">Panel Admin</h1>
          <p className="text-white/40 mt-1">Gestión de UrbanSole</p>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 bg-white/5 rounded-xl p-1 w-fit mb-8">
          {[
            { key: 'stats', label: 'Dashboard', icon: LayoutDashboard },
            { key: 'products', label: 'Productos', icon: Package },
            { key: 'orders', label: 'Pedidos', icon: ShoppingCart },
          ].map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              id={`admin-tab-${key}`}
              onClick={() => setActiveTab(key)}
              className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all ${
                activeTab === key
                  ? 'bg-white text-black'
                  : 'text-white/50 hover:text-white'
              }`}
            >
              <Icon className="w-4 h-4" />
              {label}
            </button>
          ))}
        </div>

        {/* ─── STATS ─────────────────────────────────────────── */}
        {activeTab === 'stats' && stats && (
          <div className="space-y-6">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
              {[
                { label: 'Productos', value: stats.total_products, icon: Package, sub: `${stats.active_products} activos` },
                { label: 'Pedidos', value: stats.total_orders, icon: ShoppingCart, sub: 'total' },
                { label: 'Usuarios', value: stats.total_users, icon: Users, sub: 'registrados' },
                { label: 'Ingresos', value: `$${Number(stats.revenue).toFixed(0)}`, icon: TrendingUp, sub: 'simulados' },
              ].map(({ label, value, icon: Icon, sub }) => (
                <div key={label} className="bg-[#111] rounded-2xl border border-white/10 p-6">
                  <div className="flex items-center justify-between mb-3">
                    <p className="text-white/50 text-sm">{label}</p>
                    <Icon className="w-4 h-4 text-white/20" />
                  </div>
                  <p className="text-3xl font-black text-white">{value}</p>
                  <p className="text-white/30 text-xs mt-1">{sub}</p>
                </div>
              ))}
            </div>

            {/* Recent orders */}
            {stats.recent_orders?.length > 0 && (
              <div className="bg-[#111] rounded-2xl border border-white/10 p-6">
                <h3 className="text-white font-bold mb-4">Pedidos recientes</h3>
                <div className="space-y-3">
                  {stats.recent_orders.map(order => (
                    <div key={order.id} className="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                      <div>
                        <p className="text-white text-sm font-medium">Pedido #{order.id}</p>
                        <p className="text-white/40 text-xs">{order.user?.name} · {order.user?.email}</p>
                      </div>
                      <div className="text-right">
                        <p className="text-white font-bold">${order.total}</p>
                        <p className="text-white/40 text-xs capitalize">{order.status}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* ─── PRODUCTS ──────────────────────────────────────── */}
        {activeTab === 'products' && (
          <div>
            <div className="flex justify-between items-center mb-6">
              <p className="text-white/50 text-sm">{products.length} productos</p>
              <button
                id="admin-add-product-btn"
                onClick={() => setEditProduct({})}
                className="flex items-center gap-2 bg-white text-black font-bold px-4 py-2.5 rounded-xl text-sm"
              >
                <Plus className="w-4 h-4" />
                Nuevo producto
              </button>
            </div>

            {loading ? (
              <div className="flex justify-center py-20">
                <Loader2 className="w-8 h-8 text-white/30 animate-spin" />
              </div>
            ) : (
              <div className="bg-[#111] rounded-2xl border border-white/10 overflow-hidden">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-white/10">
                      <th className="text-left text-white/40 text-xs font-medium px-6 py-3">Producto</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Categoría</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Precio</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Estado</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {products.map((p, idx) => (
                      <tr key={p.id} className={`border-b border-white/5 hover:bg-white/3 transition-colors ${idx % 2 === 0 ? '' : 'bg-white/[0.01]'}`}>
                        <td className="px-6 py-4">
                          <div className="flex items-center gap-3">
                            {p.images?.[0] && (
                              <div className="w-10 h-10 rounded-lg overflow-hidden bg-white/5 flex-shrink-0">
                                <img src={p.images[0]} alt="" className="w-full h-full object-cover" />
                              </div>
                            )}
                            <div>
                              <p className="text-white text-sm font-semibold">{p.name}</p>
                              <p className="text-white/40 text-xs">{p.brand}</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-4 text-white/60 text-sm">{p.category}</td>
                        <td className="px-4 py-4 text-white font-bold text-sm">${p.price}</td>
                        <td className="px-4 py-4">
                          <span className={`text-xs px-2.5 py-1 rounded-full border ${
                            p.active ? 'text-green-400 bg-green-400/10 border-green-400/20' : 'text-white/30 bg-white/5 border-white/10'
                          }`}>
                            {p.active ? 'Activo' : 'Inactivo'}
                          </span>
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex items-center gap-2">
                            <button
                              onClick={() => setEditProduct(p)}
                              className="p-1.5 text-white/40 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                            >
                              <Edit2 className="w-3.5 h-3.5" />
                            </button>
                            <button
                              onClick={() => handleDeleteProduct(p.id)}
                              className="p-1.5 text-white/40 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                            <Link
                              to={`/product/${p.slug}`}
                              target="_blank"
                              className="p-1.5 text-white/40 hover:text-white hover:bg-white/10 rounded-lg transition-colors text-xs"
                            >
                              Ver
                            </Link>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}

        {/* ─── ORDERS ────────────────────────────────────────── */}
        {activeTab === 'orders' && (
          <div>
            {loading ? (
              <div className="flex justify-center py-20">
                <Loader2 className="w-8 h-8 text-white/30 animate-spin" />
              </div>
            ) : (
              <div className="bg-[#111] rounded-2xl border border-white/10 overflow-hidden">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-white/10">
                      <th className="text-left text-white/40 text-xs font-medium px-6 py-3">#</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Usuario</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Total</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Estado</th>
                      <th className="text-left text-white/40 text-xs font-medium px-4 py-3">Fecha</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orders.map(order => (
                      <tr key={order.id} className="border-b border-white/5 hover:bg-white/3">
                        <td className="px-6 py-4 text-white text-sm font-medium">#{order.id}</td>
                        <td className="px-4 py-4">
                          <p className="text-white text-sm">{order.user?.name}</p>
                          <p className="text-white/40 text-xs">{order.user?.email}</p>
                        </td>
                        <td className="px-4 py-4 text-white font-bold">${order.total}</td>
                        <td className="px-4 py-4">
                          <select
                            value={order.status}
                            onChange={e => handleOrderStatus(order.id, e.target.value)}
                            className="bg-white/8 border border-white/10 text-white text-xs rounded-lg py-1 px-2 focus:outline-none cursor-pointer"
                          >
                            {STATUS_OPTIONS.map(s => (
                              <option key={s} value={s} className="bg-zinc-900">{s}</option>
                            ))}
                          </select>
                        </td>
                        <td className="px-4 py-4 text-white/40 text-xs">
                          {new Date(order.created_at).toLocaleDateString('es-AR')}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {orders.length === 0 && (
                  <div className="text-center py-12 text-white/30">No hay pedidos</div>
                )}
              </div>
            )}
          </div>
        )}
      </div>

      {/* ─── Edit Product Modal ──────────────────────── */}
      {editProduct !== null && (
        <ProductEditModal
          product={editProduct}
          loading={editLoading}
          onSave={handleSaveProduct}
          onClose={() => setEditProduct(null)}
        />
      )}
    </div>
  );
}

function ProductEditModal({ product, loading, onSave, onClose }) {
  const [form, setForm] = useState({
    name: product.name || '',
    brand: product.brand || 'Nike',
    description: product.description || '',
    price: product.price || '',
    original_price: product.original_price || '',
    category: product.category || 'Hombre',
    model_3d_url: product.model_3d_url || '',
    featured: product.featured || false,
    active: product.active !== false,
    id: product.id,
  });

  const handleChange = e => {
    const { name, value, type, checked } = e.target;
    setForm(f => ({ ...f, [name]: type === 'checkbox' ? checked : value }));
  };

  return (
    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-[#111] rounded-3xl border border-white/15 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b border-white/10">
          <h2 className="text-white font-bold text-lg">
            {form.id ? 'Editar producto' : 'Nuevo producto'}
          </h2>
          <button onClick={onClose} className="text-white/40 hover:text-white">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 space-y-4">
          {[
            { name: 'name', label: 'Nombre', type: 'text' },
            { name: 'brand', label: 'Marca', type: 'text' },
            { name: 'price', label: 'Precio', type: 'number' },
            { name: 'original_price', label: 'Precio original (opcional)', type: 'number' },
            { name: 'model_3d_url', label: 'URL modelo 3D (.glb)', type: 'url' },
          ].map(({ name, label, type }) => (
            <div key={name} className="space-y-1.5">
              <label className="text-white/50 text-xs font-medium">{label}</label>
              <input
                type={type}
                name={name}
                value={form[name]}
                onChange={handleChange}
                className="w-full bg-white/5 border border-white/10 text-white rounded-xl py-2.5 px-4 text-sm focus:outline-none focus:border-white/30"
              />
            </div>
          ))}

          <div className="space-y-1.5">
            <label className="text-white/50 text-xs font-medium">Categoría</label>
            <select
              name="category"
              value={form.category}
              onChange={handleChange}
              className="w-full bg-white/5 border border-white/10 text-white rounded-xl py-2.5 px-4 text-sm focus:outline-none"
            >
              {['Hombre', 'Mujer', 'Niño'].map(c => (
                <option key={c} value={c} className="bg-zinc-900">{c}</option>
              ))}
            </select>
          </div>

          <div className="space-y-1.5">
            <label className="text-white/50 text-xs font-medium">Descripción</label>
            <textarea
              name="description"
              value={form.description}
              onChange={handleChange}
              rows={3}
              className="w-full bg-white/5 border border-white/10 text-white rounded-xl py-2.5 px-4 text-sm focus:outline-none resize-none"
            />
          </div>

          <div className="flex items-center gap-6">
            {[
              { name: 'featured', label: 'Destacado' },
              { name: 'active', label: 'Activo' },
            ].map(({ name, label }) => (
              <label key={name} className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  name={name}
                  checked={form[name]}
                  onChange={handleChange}
                  className="sr-only"
                />
                <div className={`w-5 h-5 rounded-md border flex items-center justify-center transition-colors ${
                  form[name] ? 'bg-white border-white' : 'border-white/20'
                }`}>
                  {form[name] && <Check className="w-3 h-3 text-black" />}
                </div>
                <span className="text-white/70 text-sm">{label}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="flex gap-3 p-6 border-t border-white/10">
          <button
            onClick={() => onSave(form)}
            disabled={loading}
            className="flex-1 btn-press bg-white text-black font-bold py-3 rounded-xl flex items-center justify-center gap-2 hover:bg-white/90 disabled:opacity-50"
          >
            {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
            {loading ? 'Guardando...' : 'Guardar'}
          </button>
          <button
            onClick={onClose}
            className="px-6 border border-white/20 text-white/60 font-medium rounded-xl hover:bg-white/5"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  );
}
