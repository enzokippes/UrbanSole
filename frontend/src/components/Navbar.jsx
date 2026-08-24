import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { ShoppingBag, Search, User, Menu, X, ChevronDown, Sun, Moon } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useTheme } from '../context/ThemeContext';

export default function Navbar() {
  const { user, logout, isAdmin } = useAuth();
  const { count, setIsOpen } = useCart();
  const { theme, isDark, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const location = useLocation();
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [userMenuOpen, setUserMenuOpen] = useState(false);

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    setMobileOpen(false);
  }, [location]);

  const handleSearch = (e) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/catalog?search=${encodeURIComponent(searchQuery.trim())}`);
      setSearchQuery('');
    }
  };

  const handleLogout = async () => {
    await logout();
    setUserMenuOpen(false);
    navigate('/');
  };

  const categories = [
    { label: 'Hombre', path: '/catalog?category=Hombre' },
    { label: 'Mujer', path: '/catalog?category=Mujer' },
    { label: 'Niño', path: '/catalog?category=Niño' },
  ];

  return (
    <>
      <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        scrolled ? 'glass border-b border-white/10' : 'bg-transparent'
      }`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            {/* Logo */}
            <Link to="/" className="flex items-center gap-2 group">
              <div className="w-8 h-8 bg-white rounded-sm flex items-center justify-center">
                <span className="text-black font-black text-xs tracking-tighter">US</span>
              </div>
              <span className="text-white font-black text-lg tracking-tight">
                URBAN<span className="text-white/50">SOLE</span>
              </span>
            </Link>

            {/* Desktop Navigation */}
            <div className="hidden md:flex items-center gap-8">
              {categories.map((cat) => (
                <Link
                  key={cat.label}
                  to={cat.path}
                  className="text-white/70 hover:text-white text-sm font-medium tracking-wide uppercase transition-colors duration-200"
                >
                  {cat.label}
                </Link>
              ))}
              <Link
                to="/catalog"
                className="text-white/70 hover:text-white text-sm font-medium tracking-wide uppercase transition-colors duration-200"
              >
                Catálogo
              </Link>
            </div>

            {/* Right Actions */}
            <div className="flex items-center gap-3 sm:gap-4">
              {/* Search */}
              <form onSubmit={handleSearch} className="hidden sm:flex items-center">
                <div className="relative">
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Buscar..."
                    className="bg-white/8 border border-white/10 text-white placeholder-white/30 text-sm rounded-full py-1.5 pl-4 pr-9 w-36 focus:w-48 focus:outline-none focus:border-white/30 transition-all duration-300"
                    id="navbar-search"
                  />
                  <button type="submit" className="absolute right-2.5 top-1/2 -translate-y-1/2">
                    <Search className="w-3.5 h-3.5 text-white/40" />
                  </button>
                </div>
              </form>

              {/* Theme Toggle Button */}
              <button
                id="theme-toggle-btn"
                onClick={toggleTheme}
                className="p-2 rounded-full text-white/70 hover:text-white hover:bg-white/10 transition-all duration-200"
                title={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
                aria-label="Alternar tema"
              >
                {isDark ? (
                  <Sun className="w-4 h-4 text-amber-400 hover:rotate-45 transition-transform duration-300" />
                ) : (
                  <Moon className="w-4 h-4 text-indigo-600 hover:-rotate-12 transition-transform duration-300" />
                )}
              </button>

              {/* Cart */}
              <button
                id="cart-toggle-btn"
                onClick={() => setIsOpen(true)}
                className="relative p-2 text-white/70 hover:text-white transition-colors"
              >
                <ShoppingBag className="w-5 h-5" />
                {count > 0 && (
                  <span className="absolute -top-1 -right-1 bg-white text-black text-xs font-bold w-4 h-4 rounded-full flex items-center justify-center leading-none">
                    {count > 9 ? '9+' : count}
                  </span>
                )}
              </button>

              {/* User */}
              {user ? (
                <div className="relative">
                  <button
                    id="user-menu-btn"
                    onClick={() => setUserMenuOpen(!userMenuOpen)}
                    className="flex items-center gap-1.5 text-white/70 hover:text-white transition-colors"
                  >
                    <div className="w-7 h-7 bg-white/10 rounded-full flex items-center justify-center border border-white/20">
                      <span className="text-xs font-bold">{user.name?.[0]?.toUpperCase()}</span>
                    </div>
                    <ChevronDown className={`w-3.5 h-3.5 transition-transform ${userMenuOpen ? 'rotate-180' : ''}`} />
                  </button>
                  {userMenuOpen && (
                    <div className="absolute right-0 top-full mt-2 w-48 glass rounded-xl overflow-hidden shadow-2xl border border-white/10">
                      <div className="px-4 py-3 border-b border-white/10">
                        <p className="text-sm font-medium text-white">{user.name}</p>
                        <p className="text-xs text-white/40 truncate">{user.email}</p>
                      </div>
                      {isAdmin && (
                        <Link
                          to="/admin"
                          onClick={() => setUserMenuOpen(false)}
                          className="block px-4 py-2.5 text-sm text-white/70 hover:text-white hover:bg-white/5 transition-colors"
                        >
                          Panel Admin
                        </Link>
                      )}
                      <Link
                        to="/orders"
                        onClick={() => setUserMenuOpen(false)}
                        className="block px-4 py-2.5 text-sm text-white/70 hover:text-white hover:bg-white/5 transition-colors"
                      >
                        Mis Pedidos
                      </Link>
                      <button
                        onClick={handleLogout}
                        className="w-full text-left px-4 py-2.5 text-sm text-red-400 hover:text-red-300 hover:bg-white/5 transition-colors"
                      >
                        Cerrar sesión
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                <Link
                  to="/login"
                  id="login-btn"
                  className="flex items-center gap-1.5 text-white/70 hover:text-white text-sm font-medium transition-colors"
                >
                  <User className="w-4 h-4" />
                  <span className="hidden sm:inline">Ingresar</span>
                </Link>
              )}

              {/* Mobile menu toggle */}
              <button
                className="md:hidden text-white/70 hover:text-white"
                onClick={() => setMobileOpen(!mobileOpen)}
              >
                {mobileOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
              </button>
            </div>
          </div>
        </div>

        {/* Mobile menu */}
        {mobileOpen && (
          <div className="md:hidden glass border-t border-white/10 px-4 py-4 space-y-3">
            <form onSubmit={handleSearch} className="flex gap-2">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Buscar zapatillas..."
                className="flex-1 bg-white/8 border border-white/10 text-white placeholder-white/30 text-sm rounded-lg py-2 px-4 focus:outline-none focus:border-white/30"
              />
              <button type="submit" className="px-3 py-2 bg-white text-black rounded-lg">
                <Search className="w-4 h-4" />
              </button>
            </form>
            {categories.map((cat) => (
              <Link
                key={cat.label}
                to={cat.path}
                className="block text-white/70 hover:text-white text-sm font-medium uppercase py-1"
              >
                {cat.label}
              </Link>
            ))}
            <Link to="/catalog" className="block text-white/70 hover:text-white text-sm font-medium uppercase py-1">
              Catálogo
            </Link>
            <div className="pt-2 border-t border-white/10 flex items-center justify-between">
              <span className="text-white/50 text-xs font-medium">Tema visual</span>
              <button
                onClick={toggleTheme}
                className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/5 text-white/80 text-xs"
              >
                {isDark ? <Sun className="w-3.5 h-3.5 text-amber-400" /> : <Moon className="w-3.5 h-3.5 text-indigo-500" />}
                <span>{isDark ? 'Modo Claro' : 'Modo Oscuro'}</span>
              </button>
            </div>
          </div>
        )}
      </nav>

      {/* Click outside to close user menu */}
      {userMenuOpen && (
        <div className="fixed inset-0 z-40" onClick={() => setUserMenuOpen(false)} />
      )}
    </>
  );
}
