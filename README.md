# UrbanSole - E-Commerce

Proyecto full-stack de tienda de calzado desarrollado con Laravel 11 en el backend y React 18 con Vite en el frontend.

---

## Historia de Usuario: Registro de Nuevos Clientes

Como nuevo cliente, quiero crear una cuenta con mis datos personales y contraseña para poder realizar pedidos y guardar mi historial de compras.

### Tareas del Sprint

1. **[FRONT] Vistas de Login y Registro con estado global en AuthPage.jsx**
   - Interfaz con tabs ("Ingresar" / "Registrarse") y formulario controlado.
   - Validación del lado del cliente de coincidencia de contraseñas.
   - Toggle de visibilidad de password (Eye / EyeOff).
   - Manejo de estados de carga (Loader2) y captura de errores de validación de la API.
   - Almacenamiento de token y usuario en localStorage mediante AuthContext.

2. **[BACK] Endpoints de Registro y Login con emisión de tokens Sanctum en AuthController.php**
   - Endpoint `POST /api/auth/register` con validaciones (nombre, email único, password mínimo 8 caracteres y confirmación).
   - Hasheo de contraseñas con bcrypt (`Hash::make`).
   - Endpoint `POST /api/auth/login` con validación de credenciales y revocación de tokens anteriores.
   - Emisión de tokens de acceso personales mediante Laravel Sanctum.

3. **[QA] Casos de prueba de Autenticación y Validación de Entradas**
   - Pruebas automatizadas de registro exitoso y login exitoso.
   - Casos de fallo: contraseñas menores a 8 caracteres, confirmación desigual, emails duplicados y formatos de email inválidos.
   - Verificación de hash bcrypt en base de datos SQLite.

---

## Historia de Usuario 1.2: Control de Acceso Basado en Roles (RBAC) y Manejo de Sesión Expirada

Como administrador de seguridad del sistema, quiero restringir rutas protegidas según el rol del usuario y cerrar sesiones expiradas para salvaguardar los datos de la plataforma.

### Tareas del Sprint

1. **[BACK] Middleware de protección de rutas y Middleware de Administrador en IsAdmin.php**
   - Creación del middleware `IsAdmin.php` verificando `$request->user()->isAdmin()`, respondiendo HTTP 403 ante accesos no autorizados.
   - Registro del alias de middleware `is.admin` en `bootstrap/app.php`.
   - Grupo de rutas con prefijo `/admin` protegido por `auth:sanctum` y `is.admin`.
   - Revocación de tokens en logout mediante `$request->user()->currentAccessToken()->delete()`.

2. **[FRONT] Configurar Route Guards y Axios Interceptors en App.jsx y api/index.js**
   - Componentes envoltorios `ProtectedRoute` (redirige a `/login` si no hay usuario) y `AdminRoute` (redirige a `/` si el usuario no es admin).
   - Inyección automática de token Bearer en cabeceras de Axios.
   - Interceptor global de respuestas que captura errores HTTP 401, purga el almacenamiento local (`urbansole_token`, `urbansole_user`) y redirige a `/login`.

3. **[QA] Pruebas de Seguridad y Restricción de Roles (RBAC)**
   - Pruebas funcionales en `SecurityTest.php` verificando respuesta 403 para usuarios con rol `customer` en rutas `/api/admin/*`.
   - Verificación de acceso permitido 200 para usuarios con rol `admin`.
   - Comprobación en base de datos de que el logout revoca efectivamente el token de `personal_access_tokens`.
   - Verificación de error 401 ante peticiones sin token en rutas protegidas.

---

## Puesta en Marcha

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan test
php artisan serve
```

### Frontend (React)

```bash
cd frontend
npm install
npm run dev
```
