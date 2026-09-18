import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, ChevronDown } from 'lucide-react';
import { productsApi } from '../api';
import ProductCard from '../components/ProductCard';
import ModelViewer3D from '../components/ModelViewer3D';

// Hero GLB - using real Air Jordan 1 3D model
const HERO_GLB = '/models/jordan.glb';

export default function HomePage() {
  const [featuredProducts, setFeaturedProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    productsApi.getAll({ featured: true, per_page: 8 })
      .then(res => setFeaturedProducts(res.data.data || []))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="min-h-screen">
      {/* ─── HERO ─────────────────────────────────────────────────── */}
      <section className="relative min-h-screen flex items-center overflow-hidden">
        {/* Background gradient */}
        <div className="absolute inset-0 bg-gradient-to-br from-[#0a0a0a] via-[#0d0d0d] to-[#111]" />
        <div className="absolute inset-0">
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-white/[0.02] rounded-full blur-3xl" />
          <div className="absolute top-1/4 right-0 w-[400px] h-[400px] bg-white/[0.03] rounded-full blur-3xl" />
        </div>

        {/* Grid pattern */}
        <div className="absolute inset-0 opacity-[0.015]"
          style={{
            backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
            backgroundSize: '60px 60px',
          }}
        />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center min-h-[calc(100vh-4rem)]">
            {/* Left - Text */}
            <div className="space-y-8">
              <div>
                <p className="text-white/40 text-sm font-medium uppercase tracking-[0.3em] mb-4 animate-fade-up">
                  Nueva Colección 2024
                </p>
                <h1 className="text-7xl sm:text-8xl lg:text-9xl font-black leading-none tracking-tighter animate-fade-up delay-100">
                  <span className="gradient-text">WEAR</span>
                  <br />
                  <span className="text-white">YOUR</span>
                  <br />
                  <span className="text-white/20 text-stroke">SOUL</span>
                </h1>
              </div>

              <p className="text-white/50 text-lg max-w-md leading-relaxed animate-fade-up delay-200">
                Las zapatillas que definen quién sos. Explorá cada par con modelos 3D interactivos y texturas reales.
              </p>

              <div className="flex flex-wrap gap-4 animate-fade-up delay-300">
                <Link
                  to="/catalog"
                  id="hero-cta-primary"
                  className="btn-press group flex items-center gap-2 bg-white text-black font-bold px-8 py-4 rounded-full hover:bg-white/90 transition-all duration-200"
                >
                  Ver Catálogo
                  <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
                </Link>
                <Link
                  to="/catalog?featured=true"
                  id="hero-cta-secondary"
                  className="flex items-center gap-2 border border-white/20 text-white/70 font-medium px-8 py-4 rounded-full hover:border-white/40 hover:text-white transition-all duration-200"
                >
                  Destacados
                </Link>
              </div>

              {/* Stats */}
              <div className="flex gap-8 pt-4 animate-fade-up delay-400">
                {[
                  { num: '8', label: 'Modelos 3D' },
                  { num: '360°', label: 'Rotación Total' },
                  { num: '100%', label: 'PBR Shaders' },
                ].map(({ num, label }) => (
                  <div key={label}>
                    <p className="text-white font-black text-2xl">{num}</p>
                    <p className="text-white/40 text-xs uppercase tracking-wide">{label}</p>
                  </div>
                ))}
              </div>
            </div>

            {/* Right - 3D Model */}
            <div className="relative flex items-center justify-center animate-fade-up delay-200">
              {/* Rotating ring */}
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="w-80 h-80 border border-white/5 rounded-full animate-spin"
                  style={{ animationDuration: '20s' }} />
                <div className="absolute w-96 h-96 border border-white/3 rounded-full animate-spin"
                  style={{ animationDuration: '30s', animationDirection: 'reverse' }} />
              </div>

              {/* Model Viewer */}
              <div className="relative w-full max-w-lg h-[500px] animate-float">
                <ModelViewer3D
                  src={HERO_GLB}
                  alt="Air Jordan 1 3D"
                  autoRotate={true}
                  cameraControls={true}
                  minHeight="500px"
                  className="rounded-2xl"
                />
              </div>

              {/* Floating badges */}
              <div className="absolute top-8 right-0 glass rounded-2xl p-3 animate-fade-up delay-300">
                <p className="text-white text-xs font-medium">Air Jordan 1 High</p>
                <p className="text-white/40 text-xs">Desde $180</p>
              </div>
              <div className="absolute bottom-8 left-0 glass rounded-2xl p-3 animate-fade-up delay-400">
                <p className="text-white/40 text-xs mb-1">Visualización</p>
                <p className="text-white text-xs font-bold">360° 3D Real</p>
              </div>
            </div>
          </div>

          {/* Scroll indicator */}
          <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 animate-bounce">
            <ChevronDown className="w-5 h-5 text-white/30" />
          </div>
        </div>
      </section>

      {/* ─── CATEGORIES ──────────────────────────────────────────── */}
      <section className="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {[
            { label: 'Hombre', path: '/catalog?category=Hombre', desc: 'Air Jordan 1 & Velocity Pro', num: '4' },
            { label: 'Mujer', path: '/catalog?category=Mujer', desc: 'Velocity Sport & Smoke Grey', num: '3' },
            { label: 'Niño', path: '/catalog?category=Niño', desc: 'Air Jordan Heritage Kids', num: '1' },
          ].map((cat) => (
            <Link
              key={cat.label}
              to={cat.path}
              id={`category-${cat.label.toLowerCase()}`}
              className="group relative bg-[#141414] hover:bg-[#1a1a1a] rounded-2xl p-8 border border-white/5 hover:border-white/15 transition-all duration-300 overflow-hidden"
            >
              <div className="absolute inset-0 bg-gradient-to-br from-white/0 to-white/[0.02] opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
              <p className="text-white/30 text-sm uppercase tracking-wider mb-2">{cat.num} modelos</p>
              <h2 className="text-white text-3xl font-black mb-1">{cat.label}</h2>
              <p className="text-white/40 text-sm">{cat.desc}</p>
              <ArrowRight className="mt-4 w-5 h-5 text-white/30 group-hover:text-white group-hover:translate-x-1 transition-all duration-200" />
            </Link>
          ))}
        </div>
      </section>

      {/* ─── FEATURED PRODUCTS ───────────────────────────────────── */}
      <section className="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-end justify-between mb-12">
          <div>
            <p className="text-white/40 text-xs uppercase tracking-[0.3em] mb-2">Selección especial</p>
            <h2 className="text-4xl sm:text-5xl font-black text-white tracking-tight">
              Destacados
            </h2>
          </div>
          <Link
            to="/catalog?featured=true"
            className="hidden sm:flex items-center gap-1.5 text-white/50 hover:text-white text-sm font-medium transition-colors group"
          >
            Ver todos
            <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </Link>
        </div>

        {loading ? (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="space-y-3">
                <div className="aspect-square bg-white/5 rounded-2xl animate-pulse" />
                <div className="h-3 bg-white/5 rounded animate-pulse w-3/4" />
                <div className="h-3 bg-white/5 rounded animate-pulse w-1/2" />
              </div>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {featuredProducts.slice(0, 8).map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}

        <div className="text-center mt-12">
          <Link
            to="/catalog"
            className="btn-press inline-flex items-center gap-2 border border-white/20 text-white font-semibold px-8 py-3.5 rounded-full hover:border-white/40 hover:bg-white/5 transition-all duration-200"
          >
            Ver catálogo completo
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
      </section>

      {/* ─── BRAND STRIP ─────────────────────────────────────────── */}
      <section className="py-16 border-t border-white/5">
        <div className="max-w-7xl mx-auto px-4">
          <p className="text-center text-white/20 text-xs uppercase tracking-[0.4em] mb-8">Solo para pruebas internas</p>
          <div className="flex items-center justify-center gap-12 flex-wrap">
            {['Air Force 1', 'Air Jordan', 'Air Max', 'Dunk Low', 'Blazer', 'Revolution'].map((name) => (
              <span key={name} className="text-white/15 font-black text-lg tracking-tight hover:text-white/30 transition-colors cursor-default">
                {name}
              </span>
            ))}
          </div>
        </div>
      </section>

      {/* ─── FEATURE CALLOUT ─────────────────────────────────────── */}
      <section className="py-24 bg-[#111]">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <p className="text-white/40 text-xs uppercase tracking-[0.3em] mb-4">Tecnología</p>
          <h2 className="text-5xl sm:text-6xl font-black text-white tracking-tight mb-6">
            Visualización 3D
            <br />
            <span className="text-white/30">interactiva</span>
          </h2>
          <p className="text-white/50 max-w-xl mx-auto text-lg leading-relaxed mb-10">
            Girá, rotá y explorá cada zapatilla desde todos los ángulos antes de comprar.
            Powered by Google model-viewer.
          </p>
          <Link
            to="/catalog"
            className="btn-press inline-flex items-center gap-2 bg-white text-black font-bold px-8 py-4 rounded-full hover:bg-white/90 transition-colors"
          >
            Explorar ahora
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-12 border-t border-white/5">
        <div className="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <div className="w-6 h-6 bg-white rounded-sm flex items-center justify-center">
              <span className="text-black font-black text-xs">US</span>
            </div>
            <span className="text-white/50 font-bold text-sm">URBANSOLE</span>
          </div>
          <p className="text-white/20 text-xs text-center">
            Proyecto de prueba/demo. Las marcas Nike son de sus respectivos dueños.
          </p>
          <div className="flex gap-6">
            {['Catálogo', 'Hombre', 'Mujer'].map(link => (
              <Link key={link} to="/catalog" className="text-white/30 hover:text-white/60 text-xs transition-colors">
                {link}
              </Link>
            ))}
          </div>
        </div>
      </footer>
    </div>
  );
}
