import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ShoppingBag, ArrowLeft, Check, Loader2, RotateCcw } from 'lucide-react';
import { productsApi } from '../api';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import ModelViewer3D from '../components/ModelViewer3D';

export default function ProductPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { addToCart, setIsOpen } = useCart();

  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedSize, setSelectedSize] = useState(null);
  const [selectedColor, setSelectedColor] = useState(null);
  const [selectedVariant, setSelectedVariant] = useState(null);
  const [activeImage, setActiveImage] = useState('');
  const [addState, setAddState] = useState('idle'); // idle | loading | success | error
  const [errorMsg, setErrorMsg] = useState('');
  const [activeTab, setActiveTab] = useState('3d'); // '3d' | 'info'

  useEffect(() => {
    setLoading(true);
    productsApi.getOne(slug)
      .then(res => {
        const prod = res.data;
        setProduct(prod);
        // Pre-select first available color and image
        if (prod.colors?.length > 0) {
          const firstColor = prod.colors[0];
          setSelectedColor(firstColor);
          const initialImg = prod.color_images?.[firstColor] || prod.images?.[0] || '';
          setActiveImage(initialImg);
        } else {
          setActiveImage(prod.images?.[0] || '');
        }
      })
      .catch(() => navigate('/catalog'))
      .finally(() => setLoading(false));
  }, [slug]);

  // Sync activeImage when selectedColor changes
  const handleColorSelect = (color) => {
    setSelectedColor(color);
    setSelectedSize(null);
    setSelectedVariant(null);
    if (product?.color_images?.[color]) {
      setActiveImage(product.color_images[color]);
    }
  };

  // Find matching variant when size+color changes
  useEffect(() => {
    if (!product || !selectedSize || !selectedColor) {
      setSelectedVariant(null);
      return;
    }
    const variant = product.variants?.find(
      v => v.size === selectedSize && v.color === selectedColor && v.stock > 0
    );
    setSelectedVariant(variant || null);
  }, [product, selectedSize, selectedColor]);

  // Get sizes available for selected color
  const availableSizesForColor = selectedColor
    ? (product?.variants || [])
        .filter(v => v.color === selectedColor && v.stock > 0)
        .map(v => v.size)
        .sort((a, b) => +a - +b)
    : product?.available_sizes || [];

  const handleAddToCart = async () => {
    if (!user) {
      navigate('/login');
      return;
    }
    if (!selectedSize) {
      setErrorMsg('Seleccioná un talle');
      return;
    }
    if (!selectedVariant) {
      setErrorMsg('Esta combinación no está disponible');
      return;
    }

    setAddState('loading');
    setErrorMsg('');
    const result = await addToCart(product.id, selectedVariant.id, 1);

    if (result?.success) {
      setAddState('success');
      setTimeout(() => setAddState('idle'), 2500);
    } else if (result?.error === 'login_required') {
      navigate('/login');
    } else {
      setAddState('error');
      setErrorMsg(result?.error || 'Error al agregar al carrito');
      setTimeout(() => setAddState('idle'), 2500);
    }
  };

  const discount = product?.original_price
    ? Math.round(((product.original_price - product.price) / product.original_price) * 100)
    : null;

  if (loading) {
    return (
      <div className="min-h-screen pt-20 flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-white/30 animate-spin" />
      </div>
    );
  }

  if (!product) return null;

  return (
    <div className="min-h-screen pt-20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {/* Breadcrumb */}
        <nav className="flex items-center gap-2 text-xs text-white/30 mb-10">
          <Link to="/" className="hover:text-white/60 transition-colors">Inicio</Link>
          <span>/</span>
          <Link to="/catalog" className="hover:text-white/60 transition-colors">Catálogo</Link>
          <span>/</span>
          <Link to={`/catalog?category=${product.category}`} className="hover:text-white/60 transition-colors">
            {product.category}
          </Link>
          <span>/</span>
          <span className="text-white/60">{product.name}</span>
        </nav>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16">
          {/* ─── LEFT: 3D Viewer ──────────────────────── */}
          <div className="space-y-4">
            {/* Tab switcher */}
            <div className="flex gap-1 bg-white/5 rounded-xl p-1 w-fit">
              {[{ key: '3d', label: 'Vista 3D' }, { key: 'info', label: 'Imagen' }].map(tab => (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={`px-4 py-1.5 rounded-lg text-sm font-medium transition-all ${
                    activeTab === tab.key
                      ? 'bg-white text-black'
                      : 'text-white/50 hover:text-white'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            {/* Viewer */}
            <div className="relative bg-[#111] rounded-3xl overflow-hidden aspect-square border border-white/8">
              {activeTab === '3d' ? (
                <>
                  <ModelViewer3D
                    src={product.model_3d_url}
                    alt={product.name}
                    autoRotate
                    cameraControls
                    minHeight="100%"
                  />
                  <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 glass rounded-full px-4 py-2">
                    <RotateCcw className="w-3.5 h-3.5 text-white/50" />
                    <span className="text-white/50 text-xs">Arrastrá para rotar</span>
                  </div>
                </>
              ) : (
                <div className="relative w-full h-full flex items-center justify-center bg-black/40">
                  <img
                    src={activeImage || product.images?.[0] || ''}
                    alt={product.name}
                    className="w-full h-full object-cover transition-all duration-300 animate-fade-in"
                  />
                  {selectedColor && (
                    <div className="absolute bottom-4 left-4 glass rounded-xl px-3 py-1.5 border border-white/10">
                      <p className="text-white/70 text-xs font-medium">{selectedColor}</p>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Thumbnail images / colorways gallery */}
            {product.images?.length > 1 && (
              <div className="flex gap-3 overflow-x-auto pb-1">
                {product.images.map((img, idx) => {
                  const matchingColor = Object.keys(product.color_images || {}).find(
                    c => product.color_images[c] === img
                  );
                  const isSelected = activeImage === img;
                  return (
                    <button
                      key={idx}
                      onClick={() => {
                        setActiveImage(img);
                        if (matchingColor) setSelectedColor(matchingColor);
                      }}
                      className={`relative w-20 h-20 rounded-2xl overflow-hidden border-2 transition-all flex-shrink-0 ${
                        isSelected
                          ? 'border-white ring-2 ring-white/30 scale-105'
                          : 'border-white/10 opacity-60 hover:opacity-100 hover:border-white/30'
                      }`}
                    >
                      <img src={img} alt="" className="w-full h-full object-cover" />
                      {matchingColor && (
                        <div className="absolute inset-x-0 bottom-0 bg-black/75 px-1 py-0.5 text-[9px] text-white truncate text-center">
                          {matchingColor.split(' ')[0]}
                        </div>
                      )}
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* ─── RIGHT: Product Info ───────────────────── */}
          <div className="space-y-8">
            {/* Brand + Name + Price */}
            <div>
              <div className="flex items-center gap-3 mb-2">
                <span className="text-white/40 text-sm font-medium uppercase tracking-wider">
                  {product.brand}
                </span>
                <span className="text-white/20">·</span>
                <span className="text-white/40 text-sm">{product.category}</span>
                {product.featured && (
                  <span className="bg-white/10 text-white text-xs font-medium px-2 py-0.5 rounded-full border border-white/15">
                    Destacado
                  </span>
                )}
              </div>
              <h1 className="text-4xl font-black text-white tracking-tight leading-tight mb-4">
                {product.name}
              </h1>
              <div className="flex items-center gap-4">
                <span className="text-4xl font-black text-white">${product.price}</span>
                {product.original_price && (
                  <>
                    <span className="text-white/30 text-xl line-through">${product.original_price}</span>
                    <span className="bg-white text-black text-sm font-bold px-2 py-0.5 rounded-lg">
                      -{discount}%
                    </span>
                  </>
                )}
              </div>
            </div>

            {/* Color Selector */}
            <div>
              <div className="flex items-center justify-between mb-3">
                <p className="text-white/50 text-sm font-medium">
                  Color: <span className="text-white font-semibold">{selectedColor || 'Seleccioná un color'}</span>
                </p>
                {product.color_images && (
                  <span className="text-white/30 text-xs">Cambia foto automáticamente</span>
                )}
              </div>
              <div className="flex flex-wrap gap-2.5">
                {product.colors?.map(color => {
                  const available = (product.variants || []).some(
                    v => v.color === color && v.stock > 0
                  );
                  const colorImg = product.color_images?.[color];
                  const isSelected = selectedColor === color;

                  return (
                    <button
                      key={color}
                      onClick={() => handleColorSelect(color)}
                      disabled={!available}
                      className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm border transition-all duration-150 ${
                        isSelected
                          ? 'bg-white text-black border-white font-bold shadow-lg shadow-white/10 scale-102'
                          : available
                            ? 'border-white/15 text-white/70 hover:border-white/40 hover:text-white bg-white/3'
                            : 'border-white/5 text-white/20 cursor-not-allowed line-through'
                      }`}
                    >
                      {colorImg && (
                        <span className="w-5 h-5 rounded-full overflow-hidden border border-black/20 flex-shrink-0">
                          <img src={colorImg} alt="" className="w-full h-full object-cover" />
                        </span>
                      )}
                      <span>{color}</span>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Size Selector */}
            <div>
              <div className="flex items-center justify-between mb-3">
                <p className="text-white/50 text-sm font-medium">
                  Talle EU: <span className="text-white font-semibold">{selectedSize || 'Seleccioná'}</span>
                </p>
                <button className="text-white/30 text-xs hover:text-white/60 underline underline-offset-2">
                  Guía de talles
                </button>
              </div>
              <div className="grid grid-cols-5 gap-2">
                {availableSizesForColor.map(size => {
                  const variant = product.variants?.find(
                    v => v.size === size && v.color === selectedColor
                  );
                  const inStock = variant && variant.stock > 0;
                  return (
                    <button
                      key={size}
                      onClick={() => inStock && setSelectedSize(size)}
                      disabled={!inStock}
                      className={`py-2.5 rounded-xl text-sm font-medium border transition-all duration-150 ${
                        selectedSize === size
                          ? 'bg-white text-black border-white font-bold'
                          : inStock
                            ? 'border-white/15 text-white/70 hover:border-white/40 hover:text-white'
                            : 'border-white/5 text-white/20 cursor-not-allowed relative overflow-hidden'
                      }`}
                      title={!inStock ? 'Sin stock' : ''}
                    >
                      {size}
                      {!inStock && (
                        <div className="absolute inset-0 flex items-center justify-center">
                          <div className="absolute w-full h-px bg-white/20 rotate-45" />
                        </div>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Stock indicator */}
            {selectedVariant && (
              <div className="flex items-center gap-2">
                <div className={`w-2 h-2 rounded-full ${
                  selectedVariant.stock <= 3 ? 'bg-yellow-400' : 'bg-green-400'
                }`} />
                <p className="text-white/50 text-sm">
                  {selectedVariant.stock <= 3
                    ? `Solo quedan ${selectedVariant.stock} unidades`
                    : `${selectedVariant.stock} unidades disponibles`}
                </p>
              </div>
            )}

            {/* Error message */}
            {errorMsg && (
              <p className="text-red-400 text-sm">{errorMsg}</p>
            )}

            {/* Add to cart */}
            <button
              id="add-to-cart-btn"
              onClick={handleAddToCart}
              disabled={addState === 'loading'}
              className={`btn-press w-full flex items-center justify-center gap-3 py-4 rounded-2xl font-bold text-base transition-all duration-200 ${
                addState === 'success'
                  ? 'bg-green-500 text-white'
                  : addState === 'error'
                    ? 'bg-red-500 text-white'
                    : 'bg-white text-black hover:bg-white/90'
              } disabled:opacity-60 disabled:cursor-not-allowed`}
            >
              {addState === 'loading' && <Loader2 className="w-5 h-5 animate-spin" />}
              {addState === 'success' && <Check className="w-5 h-5" />}
              {addState === 'idle' || addState === 'error' ? <ShoppingBag className="w-5 h-5" /> : null}
              {addState === 'idle' && 'Agregar al carrito'}
              {addState === 'loading' && 'Agregando...'}
              {addState === 'success' && '¡Agregado!'}
              {addState === 'error' && 'Error'}
            </button>

            {/* Description */}
            <div className="border-t border-white/8 pt-8">
              <h3 className="text-white font-semibold mb-3">Descripción</h3>
              <p className="text-white/50 text-sm leading-relaxed">{product.description}</p>
            </div>

            {/* Tags */}
            {product.tags?.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {product.tags.map(tag => (
                  <span key={tag} className="text-white/30 text-xs border border-white/10 px-2.5 py-1 rounded-full">
                    #{tag}
                  </span>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
