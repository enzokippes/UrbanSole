import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { SlidersHorizontal, X, ChevronDown, ChevronUp } from 'lucide-react';
import { productsApi } from '../api';
import ProductCard from '../components/ProductCard';

const CATEGORIES = ['Hombre', 'Mujer', 'Niño'];
const SIZES_MEN = ['38', '39', '40', '41', '42', '43', '44', '45'];
const SIZES_WOMEN = ['35', '36', '37', '38', '39', '40', '41'];
const SIZES_KIDS = ['28', '29', '30', '31', '32', '33', '34', '35'];
const ALL_SIZES = [...new Set([...SIZES_KIDS, ...SIZES_WOMEN, ...SIZES_MEN])].sort((a, b) => +a - +b);
const COLORS = ['Blanco', 'Negro', 'Rojo', 'Azul', 'Rosa', 'Beige', 'Verde', 'Gris'];
const SORT_OPTIONS = [
  { value: 'created_at:desc', label: 'Más recientes' },
  { value: 'price:asc', label: 'Precio: menor a mayor' },
  { value: 'price:desc', label: 'Precio: mayor a menor' },
  { value: 'name:asc', label: 'Nombre A-Z' },
];

export default function CatalogPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [products, setProducts] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Filters state
  const [filters, setFilters] = useState({
    category: searchParams.get('category') || '',
    search: searchParams.get('search') || '',
    sizes: [],
    colors: [],
    min_price: '',
    max_price: '',
    sort: 'created_at:desc',
    page: 1,
  });

  useEffect(() => {
    const category = searchParams.get('category') || '';
    const search = searchParams.get('search') || '';
    setFilters(f => ({ ...f, category, search, page: 1 }));
  }, [searchParams]);

  useEffect(() => {
    fetchProducts();
  }, [filters]);

  const fetchProducts = async () => {
    setLoading(true);
    const [sort_by, sort_dir] = filters.sort.split(':');
    try {
      const params = {
        ...(filters.category && { category: filters.category }),
        ...(filters.search && { search: filters.search }),
        ...(filters.sizes.length && { size: filters.sizes.join(',') }),
        ...(filters.colors.length && { color: filters.colors.join(',') }),
        ...(filters.min_price && { min_price: filters.min_price }),
        ...(filters.max_price && { max_price: filters.max_price }),
        sort_by,
        sort_dir,
        page: filters.page,
        per_page: 12,
      };
      const res = await productsApi.getAll(params);
      setProducts(res.data.data || []);
      setMeta(res.data.meta || {});
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const toggleFilter = (key, value) => {
    setFilters(f => {
      const current = f[key];
      const updated = current.includes(value)
        ? current.filter(v => v !== value)
        : [...current, value];
      return { ...f, [key]: updated, page: 1 };
    });
  };

  const setFilter = (key, value) => {
    setFilters(f => ({ ...f, [key]: value, page: 1 }));
  };

  const clearFilters = () => {
    setFilters({
      category: '', search: '', sizes: [], colors: [],
      min_price: '', max_price: '', sort: 'created_at:desc', page: 1,
    });
    setSearchParams({});
  };

  const activeFilterCount = [
    filters.category, ...filters.sizes, ...filters.colors,
    filters.min_price, filters.max_price
  ].filter(Boolean).length;

  return (
    <div className="min-h-screen pt-20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-4xl font-black text-white tracking-tight">
              {filters.search ? `"${filters.search}"` : filters.category || 'Catálogo'}
            </h1>
            {!loading && (
              <p className="text-white/40 text-sm mt-1">
                {meta.total || 0} productos encontrados
              </p>
            )}
          </div>
          <div className="flex items-center gap-3">
            {/* Sort */}
            <select
              value={filters.sort}
              onChange={e => setFilter('sort', e.target.value)}
              className="bg-white/8 border border-white/10 text-white text-sm rounded-xl py-2 px-3 focus:outline-none focus:border-white/30 appearance-none cursor-pointer"
              id="catalog-sort-select"
            >
              {SORT_OPTIONS.map(opt => (
                <option key={opt.value} value={opt.value} className="bg-zinc-900">
                  {opt.label}
                </option>
              ))}
            </select>

            {/* Filter toggle (mobile) */}
            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              id="filter-toggle-btn"
              className="flex items-center gap-2 bg-white/8 border border-white/10 text-white text-sm rounded-xl py-2 px-3 hover:bg-white/12 transition-colors"
            >
              <SlidersHorizontal className="w-4 h-4" />
              Filtros
              {activeFilterCount > 0 && (
                <span className="bg-white text-black text-xs font-bold w-4 h-4 rounded-full flex items-center justify-center">
                  {activeFilterCount}
                </span>
              )}
            </button>
          </div>
        </div>

        <div className="flex gap-8">
          {/* ─── SIDEBAR ─────────────────────────────── */}
          <aside className={`${
            sidebarOpen ? 'block' : 'hidden'
          } lg:block w-full lg:w-64 flex-shrink-0`}>
            <div className="bg-[#111] rounded-2xl border border-white/8 p-6 space-y-6 sticky top-24">
              {/* Clear */}
              {activeFilterCount > 0 && (
                <button
                  onClick={clearFilters}
                  className="flex items-center gap-1.5 text-white/50 hover:text-white text-xs transition-colors"
                >
                  <X className="w-3.5 h-3.5" />
                  Limpiar filtros ({activeFilterCount})
                </button>
              )}

              {/* Category */}
              <FilterSection title="Categoría">
                <div className="space-y-2">
                  {CATEGORIES.map(cat => (
                    <label key={cat} className="flex items-center gap-2.5 cursor-pointer group">
                      <input
                        type="radio"
                        name="category"
                        value={cat}
                        checked={filters.category === cat}
                        onChange={() => setFilter('category', filters.category === cat ? '' : cat)}
                        className="sr-only"
                      />
                      <div className={`w-4 h-4 rounded-full border flex-shrink-0 transition-colors ${
                        filters.category === cat
                          ? 'bg-white border-white'
                          : 'border-white/20 group-hover:border-white/50'
                      }`}>
                        {filters.category === cat && (
                          <div className="w-2 h-2 rounded-full bg-black mx-auto mt-0.5" />
                        )}
                      </div>
                      <span className={`text-sm transition-colors ${
                        filters.category === cat ? 'text-white font-medium' : 'text-white/50 group-hover:text-white/70'
                      }`}>{cat}</span>
                    </label>
                  ))}
                </div>
              </FilterSection>

              {/* Sizes */}
              <FilterSection title="Talle (EU)">
                <div className="grid grid-cols-4 gap-1.5">
                  {ALL_SIZES.map(size => (
                    <button
                      key={size}
                      onClick={() => toggleFilter('sizes', size)}
                      className={`py-1.5 text-xs rounded-lg border transition-all duration-150 ${
                        filters.sizes.includes(size)
                          ? 'bg-white text-black border-white font-bold'
                          : 'border-white/10 text-white/50 hover:border-white/30 hover:text-white/70'
                      }`}
                    >
                      {size}
                    </button>
                  ))}
                </div>
              </FilterSection>

              {/* Colors */}
              <FilterSection title="Color">
                <div className="flex flex-wrap gap-2">
                  {COLORS.map(color => (
                    <button
                      key={color}
                      onClick={() => toggleFilter('colors', color)}
                      className={`px-3 py-1 text-xs rounded-full border transition-all duration-150 ${
                        filters.colors.includes(color)
                          ? 'bg-white text-black border-white font-bold'
                          : 'border-white/10 text-white/50 hover:border-white/30 hover:text-white/70'
                      }`}
                    >
                      {color}
                    </button>
                  ))}
                </div>
              </FilterSection>

              {/* Price Range */}
              <FilterSection title="Precio ($)">
                <div className="flex items-center gap-2">
                  <input
                    type="number"
                    placeholder="Min"
                    value={filters.min_price}
                    onChange={e => setFilter('min_price', e.target.value)}
                    className="w-full bg-white/5 border border-white/10 text-white text-sm rounded-lg py-1.5 px-3 focus:outline-none focus:border-white/30"
                  />
                  <span className="text-white/30 text-sm">-</span>
                  <input
                    type="number"
                    placeholder="Max"
                    value={filters.max_price}
                    onChange={e => setFilter('max_price', e.target.value)}
                    className="w-full bg-white/5 border border-white/10 text-white text-sm rounded-lg py-1.5 px-3 focus:outline-none focus:border-white/30"
                  />
                </div>
              </FilterSection>
            </div>
          </aside>

          {/* ─── PRODUCT GRID ─────────────────────────── */}
          <div className="flex-1">
            {/* Active filters chips */}
            {activeFilterCount > 0 && (
              <div className="flex flex-wrap gap-2 mb-6">
                {filters.category && (
                  <FilterChip label={filters.category} onRemove={() => setFilter('category', '')} />
                )}
                {filters.sizes.map(s => (
                  <FilterChip key={s} label={`Talle ${s}`} onRemove={() => toggleFilter('sizes', s)} />
                ))}
                {filters.colors.map(c => (
                  <FilterChip key={c} label={c} onRemove={() => toggleFilter('colors', c)} />
                ))}
                {filters.min_price && (
                  <FilterChip label={`Desde $${filters.min_price}`} onRemove={() => setFilter('min_price', '')} />
                )}
                {filters.max_price && (
                  <FilterChip label={`Hasta $${filters.max_price}`} onRemove={() => setFilter('max_price', '')} />
                )}
              </div>
            )}

            {loading ? (
              <div className="grid grid-cols-2 lg:grid-cols-3 gap-6">
                {[...Array(9)].map((_, i) => (
                  <div key={i} className="space-y-3">
                    <div className="aspect-square bg-white/5 rounded-2xl animate-pulse" />
                    <div className="h-3 bg-white/5 rounded animate-pulse w-3/4" />
                    <div className="h-3 bg-white/5 rounded animate-pulse w-1/2" />
                  </div>
                ))}
              </div>
            ) : products.length === 0 ? (
              <div className="text-center py-24">
                <p className="text-white/30 text-lg">No se encontraron productos</p>
                <button onClick={clearFilters} className="mt-4 text-white/50 hover:text-white text-sm underline">
                  Limpiar filtros
                </button>
              </div>
            ) : (
              <>
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-6">
                  {products.map(product => (
                    <ProductCard key={product.id} product={product} />
                  ))}
                </div>

                {/* Pagination */}
                {meta.last_page > 1 && (
                  <div className="flex items-center justify-center gap-2 mt-12">
                    {[...Array(meta.last_page)].map((_, i) => (
                      <button
                        key={i + 1}
                        onClick={() => setFilter('page', i + 1)}
                        className={`w-9 h-9 rounded-xl text-sm font-medium transition-colors ${
                          meta.current_page === i + 1
                            ? 'bg-white text-black'
                            : 'bg-white/8 text-white/60 hover:bg-white/15'
                        }`}
                      >
                        {i + 1}
                      </button>
                    ))}
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function FilterSection({ title, children }) {
  const [open, setOpen] = useState(true);
  return (
    <div>
      <button
        onClick={() => setOpen(!open)}
        className="flex items-center justify-between w-full text-white font-semibold text-sm mb-3"
      >
        {title}
        {open ? <ChevronUp className="w-3.5 h-3.5 text-white/40" /> : <ChevronDown className="w-3.5 h-3.5 text-white/40" />}
      </button>
      {open && children}
    </div>
  );
}

function FilterChip({ label, onRemove }) {
  return (
    <span className="flex items-center gap-1.5 bg-white/10 text-white text-xs font-medium px-3 py-1.5 rounded-full border border-white/15">
      {label}
      <button onClick={onRemove} className="text-white/50 hover:text-white">
        <X className="w-3 h-3" />
      </button>
    </span>
  );
}
