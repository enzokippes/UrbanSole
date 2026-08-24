import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ShoppingBag } from 'lucide-react';

export default function ProductCard({ product, onAddToCart }) {
  const [selectedColor, setSelectedColor] = useState(product.colors?.[0] || '');
  const image = (product.color_images && selectedColor && product.color_images[selectedColor])
    || product.images?.[0];

  const discount = product.original_price
    ? Math.round(((product.original_price - product.price) / product.original_price) * 100)
    : null;

  return (
    <div className="group relative card-hover">
      <Link to={`/product/${product.slug}`} className="block" id={`product-card-${product.id}`}>
        {/* Image container */}
        <div className="relative aspect-square bg-[#141414] rounded-2xl overflow-hidden mb-4 border border-white/5">
          {/* Discount badge */}
          {discount && (
            <div className="absolute top-3 left-3 z-10 bg-white text-black text-xs font-bold px-2 py-0.5 rounded-full">
              -{discount}%
            </div>
          )}

          {/* Featured badge */}
          {product.featured && !discount && (
            <div className="absolute top-3 left-3 z-10 bg-white/10 backdrop-blur border border-white/20 text-white text-xs font-medium px-2 py-0.5 rounded-full">
              Destacado
            </div>
          )}

          {/* Shoe image */}
          {image ? (
            <img
              src={image}
              alt={product.name}
              className="w-full h-full object-cover transition-all duration-300 group-hover:scale-105"
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <svg className="w-20 h-20 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={0.5}
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
          )}

          {/* Hover overlay - View 3D */}
          <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center pointer-events-none">
            <span className="bg-white text-black text-xs font-bold px-4 py-2 rounded-full transform translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
              Ver en 3D
            </span>
          </div>
        </div>

        {/* Product info */}
        <div className="space-y-1.5">
          <div className="flex items-start justify-between gap-2">
            <div>
              <p className="text-white/40 text-xs font-medium uppercase tracking-wider">{product.brand}</p>
              <h3 className="text-white font-semibold text-sm leading-tight line-clamp-1 mt-0.5 group-hover:text-white transition-colors">
                {product.name}
              </h3>
            </div>
          </div>

          {/* Category */}
          <p className="text-white/30 text-xs">{product.category}</p>

          {/* Interactive Color Chips */}
          {product.colors && product.colors.length > 0 && (
            <div className="flex flex-wrap items-center gap-1.5 pt-1" onClick={(e) => e.preventDefault()}>
              {product.colors.map(color => {
                const colorImg = product.color_images?.[color];
                const isSelected = selectedColor === color;
                return (
                  <button
                    key={color}
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      setSelectedColor(color);
                    }}
                    onMouseEnter={() => setSelectedColor(color)}
                    className={`text-[10px] px-2 py-0.5 rounded-md border transition-all ${
                      isSelected
                        ? 'bg-white text-black border-white font-semibold'
                        : 'border-white/10 text-white/40 hover:border-white/30 hover:text-white/70 bg-white/3'
                    }`}
                    title={color}
                  >
                    {color.split(' ')[0]}
                  </button>
                );
              })}
            </div>
          )}

          {/* Price */}
          <div className="flex items-center gap-2 pt-0.5">
            <span className="text-white font-bold text-base">${product.price}</span>
            {product.original_price && (
              <span className="text-white/30 text-sm line-through">${product.original_price}</span>
            )}
          </div>
        </div>
      </Link>

      {/* Quick add button */}
      {onAddToCart && (
        <button
          onClick={(e) => {
            e.preventDefault();
            onAddToCart(product);
          }}
          className="btn-press absolute bottom-14 right-0 bg-white text-black p-2 rounded-xl opacity-0 group-hover:opacity-100 transition-all duration-300 hover:bg-white/90 shadow-lg"
          title="Agregar al carrito"
        >
          <ShoppingBag className="w-4 h-4" />
        </button>
      )}
    </div>
  );
}
