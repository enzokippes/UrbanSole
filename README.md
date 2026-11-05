# UrbanSole

Tienda web de calzado desarrollada para la materia Programacion IV (UTN - Facultad Regional Concordia). El proyecto consta de una API REST en Laravel 11, un cliente web en Angular con visualizacion de modelos 3D en formato `.glb` y un flujo de automatizacion de correos mediante webhooks en n8n.

## Estructura del repositorio

- `backend/`: API REST en Laravel 11, autenticacion con Sanctum, migraciones, seeders y pruebas con PHPUnit.
- `frontend/`: Aplicacion SPA en Angular 22 con visor 3D interactivo (`model-viewer`), catalogo con filtros y panel de administracion.
- `n8n/`: Archivo de flujo importable (`urbansole-workflow.json`) para recibir webhooks desde Laravel y enviar notificaciones por correo.

## Requisitos previos

- PHP 8.2 o superior y Composer
- Node.js 20 o superior y npm
- SQLite (configurado por defecto) o MySQL

## Instalacion y ejecucion

### 1. Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

El servidor queda corriendo en `http://localhost:8000`.

Rutas utiles del backend:
- Base API: `http://localhost:8000/api`
- Documentacion Swagger / OpenAPI: `http://localhost:8000/api/docs`
- Visor de tablas SQL y consola de pruebas de endpoints: `http://localhost:8000/db-viewer`

Usuarios creados por el seeder:
- Administrador: `admin@urbansole.com` / `password`
- Cliente: `customer@urbansole.com` / `password`

### 2. Frontend (Angular)

```bash
cd frontend
npm install
npm start
```

La aplicacion queda disponible en `http://localhost:4200`.

### 3. Automatizacion con n8n (opcional)

Para levantar n8n localmente sin instalar paquetes globales:

```bash
npx n8n
```

Luego abrir `http://localhost:5678`, importar el archivo `n8n/urbansole-workflow.json` y configurar las credenciales SMTP en el nodo de envio de correo. Si n8n no esta corriendo, el backend registra el evento igualmente sin interrumpir el uso normal de la tienda.

## Pruebas automatizadas

Para correr la bateria de tests del backend (autenticacion, filtros de catalogo, permisos por rol y envio de webhooks):

```bash
cd backend
php artisan test
```

## Endpoints principales

| Metodo | Ruta | Descripcion |
| :--- | :--- | :--- |
| `POST` | `/api/auth/register` | Registro de usuario, emision de token y disparo de webhook a n8n |
| `POST` | `/api/auth/login` | Inicio de sesion y entrega de token Sanctum |
| `POST` | `/api/auth/oauth` | Inicio de sesion con proveedor externo (Google / GitHub) |
| `POST` | `/api/auth/logout` | Revocacion del token actual |
| `GET` | `/api/products` | Listado paginado con filtros por categoria (Hombre, Mujer, Niño), talle, color y precio |
| `GET` | `/api/products/{id}` | Detalle de producto con sus variantes de stock y modelos 3D por color |
| `POST` | `/api/admin/products` | Alta de producto con validacion `StoreProductRequest` (requiere rol admin) |
| `PUT` | `/api/admin/products/{id}` | Edicion de producto con `UpdateProductRequest` (requiere rol admin) |
| `DELETE` | `/api/admin/products/{id}` | Baja de producto (requiere rol admin) |
| `GET` | `/api/admin/stats` | Resumen de metricas de inventario y usuarios |
| `POST` | `/api/admin/webhooks/n8n` | Disparo manual de eventos de prueba hacia n8n |
