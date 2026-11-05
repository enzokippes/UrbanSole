<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return response()->json([
        'message' => 'UrbanSole API is running',
        'version' => '2.0',
        'docs' => url('/api/docs'),
        'database_viewer' => url('/db-viewer'),
    ]);
});

// Semana 3, 4 y 8: Explorador visual de Base de Datos + Probador de Endpoints API en vivo
Route::get('/db-viewer', function () {
    $driver = DB::getDriverName();
    $databaseName = DB::getDatabaseName();
    $tables = ['products', 'product_variants', 'users', 'personal_access_tokens', 'migrations'];
    $selectedTable = request('table', 'products');
    if (!in_array($selectedTable, $tables, true)) {
        $selectedTable = 'products';
    }

    $tableCounts = [];
    foreach ($tables as $t) {
        $tableCounts[$t] = Schema::hasTable($t) ? DB::table($t)->count() : 0;
    }

    $columns = Schema::hasTable($selectedTable) ? Schema::getColumnListing($selectedTable) : [];
    $rows = Schema::hasTable($selectedTable) ? DB::table($selectedTable)->orderByDesc('id')->limit(100)->get() : collect();

    $tabsHtml = '';
    foreach ($tables as $t) {
        $active = $t === $selectedTable ? 'background:#4ade80;color:#09090b;font-weight:800;' : 'background:#18181b;color:#e4e4e7;';
        $count = $tableCounts[$t];
        $tabsHtml .= "<a href='?table={$t}' style='text-decoration:none;padding:10px 16px;border-radius:10px;border:1px solid #27272a;font-size:13px;{$active}'>Tabla: {$t} <span style='opacity:0.75'>({$count})</span></a>";
    }

    $headersHtml = '';
    foreach ($columns as $col) {
        $headersHtml .= "<th style='padding:10px 12px;text-align:left;border-bottom:1px solid #27272a;color:#a1a1aa;font-size:11px;text-transform:uppercase;'>{$col}</th>";
    }

    $rowsHtml = '';
    foreach ($rows as $row) {
        $rowsHtml .= "<tr style='border-bottom:1px solid #1f1f23;'>";
        foreach ($columns as $col) {
            $val = $row->$col ?? 'NULL';
            if (is_string($val) && strlen($val) > 95) {
                $val = substr($val, 0, 95) . '...';
            }
            $escaped = htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
            $rowsHtml .= "<td style='padding:10px 12px;font-size:12px;color:#e4e4e7;font-family:Consolas,monospace;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'>{$escaped}</td>";
        }
        $rowsHtml .= "</tr>";
    }

    return response(<<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>UrbanSole — Base de Datos & Probador de Endpoints API</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #09090b; color: #f4f4f5; font-family: 'Segoe UI', system-ui, sans-serif; padding: 24px; }
    .container { max-width: 1420px; margin: 0 auto; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; background: #141418; padding: 18px 24px; border-radius: 16px; border: 1px solid #27272a; }
    .api-tester { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .card { background: #141418; border: 1px solid #27272a; border-radius: 14px; padding: 18px; }
    .card h3 { margin: 0 0 6px; font-size: 15px; color: #fff; display: flex; align-items: center; gap: 8px; }
    .badge { font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 800; text-transform: uppercase; }
    .post { background: rgba(74,222,128,0.18); color: #4ade80; }
    .put { background: rgba(56,189,248,0.18); color: #38bdf8; }
    .delete { background: rgba(248,113,113,0.18); color: #f87171; }
    .field { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
    .field label { font-size: 11px; color: #a1a1aa; }
    .input { background: #09090b; border: 1px solid #27272a; color: #fff; padding: 8px 10px; border-radius: 8px; font-size: 12px; width: 100%; }
    .btn { margin-top: 12px; width: 100%; padding: 9px 14px; border-radius: 8px; border: none; font-weight: 700; font-size: 12px; cursor: pointer; }
    .btn-green { background: #4ade80; color: #09090b; }
    .btn-blue { background: #38bdf8; color: #09090b; }
    .btn-red { background: #f87171; color: #09090b; }
    #apiResponseBox { display: none; margin-bottom: 20px; background: #09090b; border: 1px solid #4ade80; border-radius: 12px; padding: 14px; font-family: Consolas, monospace; font-size: 12px; color: #4ade80; white-space: pre-wrap; max-height: 240px; overflow-y: auto; }
  </style>
</head>
<body>
  <div class="container">
    <div class="top-bar">
      <div>
        <span style="font-size:11px;color:#4ade80;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">Programación IV · UTN Concordia · Semanas 3, 4, 5, 6 y 8</span>
        <h1 style="margin:4px 0 0;font-size:22px;">Explorador de Base de Datos + Consola de Endpoints API REST</h1>
      </div>
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <a href="/api/docs" target="_blank" style="background:#38bdf8;color:#09090b;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:800;font-size:12px;">Abrir Swagger UI Completo (/api/docs) ↗</a>
        <div style="font-size:12px;color:#a1a1aa;background:#09090b;padding:8px 14px;border-radius:10px;border:1px solid #27272a;">
          <div><strong>Motor:</strong> <span style="color:#4ade80;text-transform:uppercase;">{$driver}</span></div>
          <div><strong>Archivo BD:</strong> <code>database.sqlite</code></div>
        </div>
      </div>
    </div>

    <!-- CONSOLA INTERACTIVA PARA INGRESAR DATOS A LOS ENDPOINTS DE LA API Y VERLOS EN LA BD -->
    <div class="api-tester">
      <!-- 1. POST /api/admin/products -->
      <div class="card">
        <h3><span class="badge post">POST</span> <code>/api/admin/products</code></h3>
        <p style="margin:0 0 8px;font-size:12px;color:#a1a1aa;">Insertar una nueva zapatilla 3D en la tabla <code>products</code> y <code>product_variants</code>:</p>
        <div class="field">
          <label>Nombre de la Zapatilla</label>
          <input id="p_name" class="input" value="Nike Air Force 1 Mocha UTN" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div class="field">
            <label>Categoría</label>
            <select id="p_cat" class="input">
              <option value="Hombre">Hombre</option>
              <option value="Mujer">Mujer</option>
              <option value="Niño">Niño</option>
            </select>
          </div>
          <div class="field">
            <label>Precio ($)</label>
            <input id="p_price" type="number" class="input" value="159.99" />
          </div>
        </div>
        <div class="field">
          <label>Modelo 3D (.glb)</label>
          <select id="p_glb" class="input">
            <option value="/models/af1-mocha.glb">/models/af1-mocha.glb (AF1 Mocha)</option>
            <option value="/models/jordan-chicago.glb">/models/jordan-chicago.glb (Jordan 1 Chicago)</option>
            <option value="/models/af1-pastel-shadow.glb">/models/af1-pastel-shadow.glb (AF1 Pastel Mujer)</option>
            <option value="/models/airmax-orange.glb">/models/airmax-orange.glb (Air Max Orange Niño)</option>
          </select>
        </div>
        <button class="btn btn-green" onclick="crearProductoAPI()">Enviar POST a la API e Insertar en BD</button>
      </div>

      <!-- 2. PUT /api/admin/products/{id} -->
      <div class="card">
        <h3><span class="badge put">PUT</span> <code>/api/admin/products/{id}</code></h3>
        <p style="margin:0 0 8px;font-size:12px;color:#a1a1aa;">Modificar un producto existente por ID usando la API REST:</p>
        <div style="display:grid;grid-template-columns:80px 1fr;gap:8px;">
          <div class="field">
            <label>ID Producto</label>
            <input id="u_id" type="number" class="input" value="1" />
          </div>
          <div class="field">
            <label>Nuevo Nombre</label>
            <input id="u_name" class="input" value="Air Jordan 1 High OG Chicago (Editado API)" />
          </div>
        </div>
        <div class="field">
          <label>Nuevo Precio ($)</label>
          <input id="u_price" type="number" class="input" value="195.00" />
        </div>
        <button class="btn btn-blue" onclick="actualizarProductoAPI()">Enviar PUT a la API y Actualizar BD</button>
      </div>

      <!-- 3. POST /api/auth/register -->
      <div class="card">
        <h3><span class="badge post">POST</span> <code>/api/auth/register</code></h3>
        <p style="margin:0 0 8px;font-size:12px;color:#a1a1aa;">Registrar usuario en <code>users</code>, crear token Sanctum y disparar Webhook n8n:</p>
        <div class="field">
          <label>Nombre completo</label>
          <input id="r_name" class="input" value="Profesor Programación IV" />
        </div>
        <div class="field">
          <label>Email</label>
          <input id="r_email" class="input" value="profe.utn@concordia.edu.ar" />
        </div>
        <div class="field">
          <label>Contraseña (mín. 8 caracteres)</label>
          <input id="r_pass" type="text" class="input" value="password123" />
        </div>
        <button class="btn btn-green" onclick="registrarUsuarioAPI()">Enviar POST Registro + Token Sanctum</button>
      </div>

      <!-- 4. DELETE /api/admin/products/{id} -->
      <div class="card">
        <h3><span class="badge delete">DELETE</span> <code>/api/admin/products/{id}</code></h3>
        <p style="margin:0 0 8px;font-size:12px;color:#a1a1aa;">Eliminar un producto por su ID desde el endpoint protegido con Sanctum:</p>
        <div class="field">
          <label>ID del Producto a eliminar</label>
          <input id="d_id" type="number" class="input" value="13" />
        </div>
        <button class="btn btn-red" onclick="eliminarProductoAPI()">Enviar DELETE a la API y Borrar de BD</button>
      </div>
    </div>

    <div id="apiResponseBox"></div>

    <!-- TABS DE TABLAS DE LA BASE DE DATOS -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
      {$tabsHtml}
    </div>

    <div style="background:#141418;border:1px solid #27272a;border-radius:16px;overflow:hidden;">
      <div style="padding:14px 20px;border-bottom:1px solid #27272a;display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:14px;">Registros en vivo de la tabla: <code style="color:#38bdf8;">{$selectedTable}</code> ({$tableCounts[$selectedTable]} filas)</strong>
        <button onclick="window.location.reload()" style="background:#27272a;color:#fff;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px;">↻ Refrescar Tabla</button>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr>{$headersHtml}</tr></thead>
          <tbody>{$rowsHtml}</tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    async function obtenerTokenAdmin() {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email: 'admin@urbansole.com', password: 'password' })
      });
      const data = await res.json();
      return data.token;
    }

    function mostrarRespuesta(metodo, endpoint, status, data, redirectTable) {
      const box = document.getElementById('apiResponseBox');
      box.style.display = 'block';
      box.textContent = 'Respuesta HTTP ' + status + ' (' + metodo + ' ' + endpoint + '):\\n' + JSON.stringify(data, null, 2);
      setTimeout(() => {
        window.location.href = '/db-viewer?table=' + redirectTable;
      }, 1400);
    }

    async function crearProductoAPI() {
      const token = await obtenerTokenAdmin();
      const body = {
        name: document.getElementById('p_name').value,
        brand: 'Nike UrbanSole',
        category: document.getElementById('p_cat').value,
        price: parseFloat(document.getElementById('p_price').value),
        model_3d_url: document.getElementById('p_glb').value,
        description: 'Zapatilla 3D insertada en vivo mediante el endpoint POST /api/admin/products.'
      };
      const res = await fetch('/api/admin/products', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      mostrarRespuesta('POST', '/api/admin/products', res.status, data, 'products');
    }

    async function actualizarProductoAPI() {
      const token = await obtenerTokenAdmin();
      const id = document.getElementById('u_id').value;
      const body = {
        name: document.getElementById('u_name').value,
        price: parseFloat(document.getElementById('u_price').value)
      };
      const res = await fetch('/api/admin/products/' + id, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      mostrarRespuesta('PUT', '/api/admin/products/' + id, res.status, data, 'products');
    }

    async function registrarUsuarioAPI() {
      const pass = document.getElementById('r_pass').value;
      const body = {
        name: document.getElementById('r_name').value,
        email: document.getElementById('r_email').value,
        password: pass,
        password_confirmation: pass
      };
      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      mostrarRespuesta('POST', '/api/auth/register', res.status, data, 'users');
    }

    async function eliminarProductoAPI() {
      const token = await obtenerTokenAdmin();
      const id = document.getElementById('d_id').value;
      const res = await fetch('/api/admin/products/' + id, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer ' + token
        }
      });
      const data = await res.json();
      mostrarRespuesta('DELETE', '/api/admin/products/' + id, res.status, data, 'products');
    }
  </script>
</body>
</html>
HTML, 200)->header('Content-Type', 'text/html');
});

// Semana 8: Documentación interactiva Swagger UI
Route::get('/api/docs', function () {
    $specUrl = url('/api/openapi.json');
    return response(<<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>UrbanSole API - Swagger UI (Programación IV)</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({
        url: "{$specUrl}",
        dom_id: '#swagger-ui',
        deepLinking: true,
      });
    };
  </script>
</body>
</html>
HTML, 200)->header('Content-Type', 'text/html');
});

// Serve uploaded model files
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath);
})->where('path', '.*');
