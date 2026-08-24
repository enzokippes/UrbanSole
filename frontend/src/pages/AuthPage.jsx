import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Eye, EyeOff, Loader2 } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export default function AuthPage() {
  const navigate = useNavigate();
  const { login, register } = useAuth();
  const [tab, setTab] = useState('login'); // 'login' | 'register'
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showPass, setShowPass] = useState(false);

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const handleChange = (e) => {
    setFormData(f => ({ ...f, [e.target.name]: e.target.value }));
    setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      if (tab === 'login') {
        await login(formData.email, formData.password);
      } else {
        if (formData.password !== formData.password_confirmation) {
          setError('Las contraseñas no coinciden');
          return;
        }
        await register(formData.name, formData.email, formData.password, formData.password_confirmation);
      }
      navigate('/');
    } catch (err) {
      const msg = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : err.response?.data?.message || 'Credenciales incorrectas';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen pt-16 flex items-center justify-center px-4">
      {/* Background */}
      <div className="absolute inset-0 bg-[#0a0a0a]">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-white/[0.015] rounded-full blur-3xl" />
      </div>

      <div className="relative w-full max-w-sm">
        {/* Logo */}
        <div className="text-center mb-10">
          <Link to="/" className="inline-flex items-center gap-2">
            <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center">
              <span className="text-black font-black text-sm">US</span>
            </div>
          </Link>
        </div>

        {/* Card */}
        <div className="bg-[#111] rounded-3xl border border-white/10 overflow-hidden">
          {/* Tabs */}
          <div className="flex border-b border-white/10">
            {[
              { key: 'login', label: 'Ingresar' },
              { key: 'register', label: 'Registrarse' },
            ].map(t => (
              <button
                key={t.key}
                id={`auth-tab-${t.key}`}
                onClick={() => { setTab(t.key); setError(''); }}
                className={`flex-1 py-4 text-sm font-semibold transition-colors ${
                  tab === t.key
                    ? 'text-white border-b-2 border-white -mb-px'
                    : 'text-white/40 hover:text-white/70'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>

          <form onSubmit={handleSubmit} className="p-8 space-y-5">
            <div className="space-y-1">
              <h2 className="text-2xl font-black text-white">
                {tab === 'login' ? 'Bienvenido' : 'Crear cuenta'}
              </h2>
              <p className="text-white/40 text-sm">
                {tab === 'login'
                  ? 'Ingresá a tu cuenta para continuar'
                  : 'Completá tus datos para registrarte'}
              </p>
            </div>

            {/* Name (register only) */}
            {tab === 'register' && (
              <div className="space-y-1.5">
                <label className="text-white/50 text-xs font-medium">Nombre completo</label>
                <input
                  id="register-name"
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  required
                  placeholder="Tu nombre"
                  className="w-full bg-white/5 border border-white/10 text-white placeholder-white/20 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-white/30 transition-colors"
                />
              </div>
            )}

            {/* Email */}
            <div className="space-y-1.5">
              <label className="text-white/50 text-xs font-medium">Email</label>
              <input
                id="auth-email"
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                required
                placeholder="tu@email.com"
                className="w-full bg-white/5 border border-white/10 text-white placeholder-white/20 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-white/30 transition-colors"
              />
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <label className="text-white/50 text-xs font-medium">Contraseña</label>
              <div className="relative">
                <input
                  id="auth-password"
                  type={showPass ? 'text' : 'password'}
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  required
                  placeholder="••••••••"
                  minLength={8}
                  className="w-full bg-white/5 border border-white/10 text-white placeholder-white/20 rounded-xl py-3 px-4 pr-11 text-sm focus:outline-none focus:border-white/30 transition-colors"
                />
                <button
                  type="button"
                  onClick={() => setShowPass(!showPass)}
                  className="absolute right-3.5 top-1/2 -translate-y-1/2 text-white/30 hover:text-white/70"
                >
                  {showPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* Confirm password (register) */}
            {tab === 'register' && (
              <div className="space-y-1.5">
                <label className="text-white/50 text-xs font-medium">Confirmar contraseña</label>
                <input
                  id="register-password-confirm"
                  type="password"
                  name="password_confirmation"
                  value={formData.password_confirmation}
                  onChange={handleChange}
                  required
                  placeholder="••••••••"
                  className="w-full bg-white/5 border border-white/10 text-white placeholder-white/20 rounded-xl py-3 px-4 text-sm focus:outline-none focus:border-white/30 transition-colors"
                />
              </div>
            )}

            {/* Error */}
            {error && (
              <div className="bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3">
                <p className="text-red-400 text-sm">{error}</p>
              </div>
            )}

            {/* Submit */}
            <button
              id="auth-submit-btn"
              type="submit"
              disabled={loading}
              className="btn-press w-full bg-white text-black font-bold py-3.5 rounded-xl flex items-center justify-center gap-2 hover:bg-white/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading && <Loader2 className="w-4 h-4 animate-spin" />}
              {tab === 'login' ? 'Ingresar' : 'Crear cuenta'}
            </button>

            {/* Demo credentials */}
            {tab === 'login' && (
              <div className="bg-white/3 rounded-xl px-4 py-3 border border-white/8">
                <p className="text-white/40 text-xs font-medium mb-1">Credenciales de prueba:</p>
                <p className="text-white/60 text-xs font-mono">admin@urbansole.com / password</p>
                <p className="text-white/60 text-xs font-mono">demo@urbansole.com / password</p>
              </div>
            )}

            {/* Divider - future Google login */}
            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-white/8" />
              </div>
              <div className="relative flex justify-center">
                <span className="text-white/20 text-xs bg-[#111] px-3">Próximamente</span>
              </div>
            </div>

            <button
              type="button"
              disabled
              className="w-full flex items-center justify-center gap-3 border border-white/10 text-white/30 py-3 rounded-xl text-sm cursor-not-allowed"
            >
              <svg className="w-4 h-4" viewBox="0 0 24 24">
                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Continuar con Google
            </button>
          </form>
        </div>

        <p className="text-center text-white/20 text-xs mt-6">
          Al registrarte aceptás los términos y condiciones de UrbanSole
        </p>
      </div>
    </div>
  );
}
