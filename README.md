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
