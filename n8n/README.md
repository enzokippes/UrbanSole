# Flujo de n8n para UrbanSole

Este directorio incluye el archivo `urbansole-workflow.json` para integrar la API de Laravel con n8n mediante webhooks.

## Funcionamiento

El servicio `App\Services\N8nWebhookService` de Laravel envia una peticion `HTTP POST` a la URL configurada en `N8N_WEBHOOK_URL` (`http://localhost:5678/webhook/urbansole` por defecto) cuando ocurren los siguientes eventos:

- `user.registered`: al registrarse un usuario nuevo en `/api/auth/register`.
- `product.created`: al dar de alta una zapatilla desde `/api/admin/products`.
- `product.updated` y `product.deleted`: al modificar o borrar un producto.
- `stock.low`: cuando una variante queda con 3 unidades o menos al actualizar un producto.

Cada peticion incluye en el cuerpo JSON el bloque `email_notification` con el destinatario, el asunto y el cuerpo en HTML para que el nodo de correo de n8n lo envie directamente.

## Pasos para usar el flujo

1. Iniciar n8n desde una terminal:
   ```bash
   npx n8n
   ```
2. Entrar a `http://localhost:5678`.
3. Crear un workflow nuevo y desde el menu superior derecho elegir `Import from File...`, seleccionando `n8n/urbansole-workflow.json`.
4. Abrir el nodo de envio de email y cargar los datos de una cuenta SMTP (por ejemplo una cuenta de prueba gratuita de Ethereal Email en `smtp.ethereal.email` puerto `587` o una cuenta de Gmail con contraseña de aplicacion).
5. Activar el workflow o pulsar `Listen for test event` y disparar un evento desde la pestaña de n8n en `http://localhost:4200/admin` o registrando un usuario nuevo.
