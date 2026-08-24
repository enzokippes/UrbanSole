# UrbanSole 👟

Tienda de zapatillas con visualización 3D interactiva. Stack: Laravel 11 + React + Vite + Tailwind CSS v4 + Google model-viewer.

> ⚠️ **Demo only.** Los nombres Nike/Air Force 1/Jordan/Air Max son marcas registradas usadas solo como datos de prueba internos, no para producción ni distribución pública.

---

## 📦 Estructura del proyecto

```
UrbanShoes/
├── backend/     ← Laravel 11 API (PHP + SQLite + Sanctum)
└── frontend/    ← React + Vite + Tailwind CSS v4
```

---

## 🛠️ Setup — Paso a paso

### Paso 1: Instalar PHP y Composer

**Windows** — Descargá e instalá:
1. **PHP 8.2+**: https://windows.php.net/download/ → `VS16 x64 Thread Safe` → `php-8.2.x-Win32-vs16-x64.zip`
   - Extraé a `C:\php`
   - Agregá `C:\php` al PATH del sistema
   - Copiá `php.ini-development` → `php.ini`
   - Habilitá en `php.ini`: `extension=pdo_sqlite`, `extension=sqlite3`, `extension=openssl`, `extension=mbstring`, `extension=fileinfo`

2. **Composer**: https://getcomposer.org/Composer-Setup.exe
   - Instalá con el setup wizard (detecta PHP automáticamente)

Verificá:
```powershell
php --version   # PHP 8.2.x
composer --version   # Composer 2.x
```

---

### Paso 2: Setup del Backend (Laravel)

```powershell
# 1. Ir al directorio backend
cd backend

# 2. Instalar dependencias PHP
composer install

# 3. Crear el .env
Copy-Item .env.example .env

# 4. Generar la clave de la app
php artisan key:generate

# 5. Crear el archivo SQLite
New-Item -Path "database/database.sqlite" -ItemType File -Force

# 6. Ejecutar migraciones y seeders
php artisan migrate --seed

# 7. Iniciar el servidor
php artisan serve
```

El backend quedará en: **http://localhost:8000**

Usuarios de prueba:
- Admin: `admin@urbansole.com` / `password`
- Demo: `demo@urbansole.com` / `password`

---

### Paso 3: Setup del Frontend (React)

```powershell
# 1. Ir al directorio frontend (en otra terminal)
cd frontend

# 2. Instalar dependencias (ya debería estar listo si seguiste el setup)
npm install

# 3. Iniciar el dev server
npm run dev
```

El frontend quedará en: **http://localhost:5173**

---

## 🚀 Rutas disponibles

| URL | Descripción |
|-----|-------------|
| `/` | Home con hero 3D y productos destacados |
| `/catalog` | Catálogo completo con filtros |
| `/catalog?category=Hombre` | Filtrar por categoría |
| `/catalog?search=air` | Búsqueda |
| `/product/:slug` | Página de producto con visor 3D |
| `/login` | Login / Registro |
| `/checkout` | Checkout (requiere login) |
| `/orders` | Mis pedidos (requiere login) |
| `/admin` | Panel admin (requiere rol admin) |

## 🔌 API Endpoints

Base URL: `http://localhost:8000/api`

```
POST  /auth/register
POST  /auth/login
POST  /auth/logout         (auth)
GET   /auth/user           (auth)

GET   /products            ?search= &category= &size= &color= &min_price= &max_price= &featured= &sort_by= &sort_dir= &page=
GET   /products/:slug

GET   /cart                (auth)
POST  /cart                (auth)
PUT   /cart/:id            (auth)
DELETE /cart/:id           (auth)
DELETE /cart               (auth)

POST  /orders              (auth)
GET   /orders              (auth)

GET   /admin/stats         (admin)
GET   /admin/products      (admin)
POST  /admin/products      (admin)
PUT   /admin/products/:id  (admin)
DELETE /admin/products/:id (admin)
GET   /admin/orders        (admin)
PUT   /admin/orders/:id    (admin)
```

---

## 🥿 Agregar modelos 3D reales

Para reemplazar los placeholders por modelos `.glb` reales de zapatillas:

1. **Descargá modelos gratuitos** de:
   - [Sketchfab](https://sketchfab.com/search?features=downloadable&licenses=7c23a1ba438d4306920229c12afcb5f7&q=sneaker&type=models) (filtrar por CC0 o CC-BY)
   - [Google Poly / model-viewer samples](https://modelviewer.dev/examples/augmented-reality/)

2. **Guardá los archivos** en `backend/storage/app/public/models/`:
   ```
   storage/app/public/models/air-force-1.glb
   storage/app/public/models/air-jordan-1.glb
   ...
   ```

3. **Actualizá las URLs** en el seeder o directamente en la base de datos:
   ```sql
   UPDATE products
   SET model_3d_url = 'http://localhost:8000/storage/models/air-force-1.glb'
   WHERE slug = 'air-force-1-low';
   ```

4. Creá el symlink de storage (una sola vez):
   ```powershell
   php artisan storage:link
   ```

---

## 🎨 Diseño

- **Paleta**: Negro `#0a0a0a` con acentos blancos — estilo Nike minimalista
- **Tipografía**: Inter (Google Fonts) — headings Black weight
- **Animaciones**: fade-up en hero, float en el modelo 3D, hover scale en cards
- **Glassmorphism**: Navbar al hacer scroll, cart sidebar, user menu
- **3D**: Google `<model-viewer>` con auto-rotate, camera controls

---

## ⚙️ Variables de entorno (frontend)

Si el backend no corre en `localhost:8000`, editá `frontend/vite.config.js`:

```js
proxy: {
  '/api': {
    target: 'http://TU_BACKEND_URL',
    changeOrigin: true,
  }
}
```

---

## 🧩 Extender con Google Login

El botón de Google está preparado (disabled). Para activarlo:

1. Instalá Laravel Socialite: `composer require laravel/socialite`
2. Configurá el Google OAuth en [console.cloud.google.com](https://console.cloud.google.com)
3. Agregá en `.env`:
   ```
   GOOGLE_CLIENT_ID=xxx
   GOOGLE_CLIENT_SECRET=xxx
   GOOGLE_REDIRECT_URL=http://localhost:8000/api/auth/google/callback
   ```
4. Creá los endpoints en `AuthController`:
   - `GET /api/auth/google` → redirect
   - `GET /api/auth/google/callback` → handle

---

## 🏗️ Producción

```powershell
# Build frontend
cd frontend
npm run build
# El dist/ puede servirse desde nginx/apache

# Backend
cd backend
php artisan config:cache
php artisan route:cache
php artisan optimize
```
