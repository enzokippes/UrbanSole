<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class N8nWebhookService
{
    /**
     * Dispara un evento HTTP POST real hacia el Webhook de n8n (Semana 15).
     * Incluye la plantilla de email lista para el nodo "Send Email" / "Gmail" de n8n.
     * Si n8n no está corriendo localmente, captura el error de forma resiliente.
     */
    public function dispatch(string $event, array $data = [], ?string $triggeredBy = null, ?string $overrideEmail = null): array
    {
        $enabled = (bool) config('services.n8n.enabled', true);
        $webhookUrl = (string) config('services.n8n.webhook_url', 'http://localhost:5678/webhook/urbansole');
        $notifyEmail = $overrideEmail ?: (string) config('services.n8n.notify_email', 'admin@urbansole.com');

        $emailNotification = $this->buildEmailNotification($event, $data, $notifyEmail);

        $payload = [
            'event' => $event,
            'source' => 'UrbanSole API (Laravel 11)',
            'timestamp' => now()->toIso8601String(),
            'triggered_by' => $triggeredBy ?? 'system',
            'email_notification' => $emailNotification,
            'data' => $data,
        ];

        if (!$enabled || empty($webhookUrl)) {
            return [
                'status' => 'disabled',
                'delivered' => false,
                'http_status' => null,
                'webhook_url' => $webhookUrl,
                'notify_email' => $notifyEmail,
                'webhook_engine' => 'n8n Automation Workflow',
                'payload' => $payload,
                'message' => "El envío de webhooks a n8n está deshabilitado (N8N_WEBHOOK_ENABLED=false).",
            ];
        }

        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->withHeaders([
                    'X-UrbanSole-Event' => $event,
                    'X-UrbanSole-Source' => 'Laravel-11-API',
                ])
                ->post($webhookUrl, $payload);

            $delivered = $response->successful();

            return [
                'status' => $delivered ? 'dispatched' : 'webhook_error',
                'delivered' => $delivered,
                'http_status' => $response->status(),
                'webhook_url' => $webhookUrl,
                'notify_email' => $notifyEmail,
                'webhook_engine' => 'n8n Automation Workflow',
                'n8n_response' => $response->json() ?? $response->body(),
                'payload' => $payload,
                'message' => $delivered
                    ? "Evento '{$event}' entregado a n8n ({$webhookUrl}) para enviar email a {$notifyEmail}."
                    : "n8n respondió con HTTP {$response->status()} al procesar '{$event}'.",
            ];
        } catch (Throwable $e) {
            Log::warning("n8n webhook '{$event}' no pudo entregarse a {$webhookUrl}: " . $e->getMessage(), [
                'event' => $event,
                'notify_email' => $notifyEmail,
            ]);

            return [
                'status' => 'queued_offline',
                'delivered' => false,
                'http_status' => null,
                'webhook_url' => $webhookUrl,
                'notify_email' => $notifyEmail,
                'webhook_engine' => 'n8n Automation Workflow',
                'error' => $e->getMessage(),
                'payload' => $payload,
                'message' => "Evento '{$event}' generado y registrado (n8n no detectado en {$webhookUrl}; iniciá 'npx n8n' para entrega en vivo y envío de email a {$notifyEmail}).",
            ];
        }
    }

    /**
     * Construye el asunto y cuerpo HTML del correo electrónico que enviará n8n según el evento.
     */
    protected function buildEmailNotification(string $event, array $data, string $recipientEmail): array
    {
        $recipient = $data['email'] ?? $recipientEmail;

        switch ($event) {
            case 'user.registered':
                $userName = $data['name'] ?? 'Nuevo Cliente';
                $userEmail = $data['email'] ?? $recipientEmail;
                $subject = "👟 ¡Bienvenido/a a UrbanSole 3D, {$userName}! (Confirmación de Registro)";
                $summary = "Nuevo usuario registrado en UrbanSole: {$userName} ({$userEmail}).";
                break;

            case 'product.created':
                $prodName = $data['name'] ?? 'Zapatilla 3D';
                $cat = $data['category'] ?? 'General';
                $price = isset($data['price']) ? '$' . number_format((float) $data['price'], 2) : '-';
                $subject = "🆕 [UrbanSole Catálogo 3D] Nueva zapatilla publicada: {$prodName} ({$cat})";
                $summary = "Se dio de alta '{$prodName}' en la categoría {$cat} con precio {$price} y modelo 3D interactivo ({$data['model_3d_url']}).";
                break;

            case 'product.updated':
                $prodName = $data['name'] ?? 'Zapatilla 3D';
                $price = isset($data['price']) ? '$' . number_format((float) $data['price'], 2) : '-';
                $subject = "✏️ [UrbanSole Catálogo 3D] Producto actualizado: {$prodName}";
                $summary = "Se actualizaron los datos del calzado '{$prodName}' (Precio actual: {$price}).";
                break;

            case 'product.deleted':
                $prodName = $data['name'] ?? 'Producto';
                $subject = "🗑️ [UrbanSole Catálogo 3D] Producto eliminado: {$prodName}";
                $summary = "El producto '{$prodName}' (ID #{$data['product_id']}) fue eliminado del catálogo.";
                break;

            case 'stock.low':
                $prodName = $data['product_name'] ?? ($data['name'] ?? 'Zapatilla');
                $subject = "⚠️ [ALERTA DE STOCK - UrbanSole] Stock crítico en {$prodName}";
                $summary = "El producto '{$prodName}' tiene variantes con stock crítico (<= 3 pares disponibles). Revisar reposición urgente.";
                break;

            default:
                $subject = "🔔 [UrbanSole n8n Automation] Evento disparado: {$event}";
                $summary = "El backend Laravel 11 de UrbanSole emitió el evento '{$event}' hacia el flujo de trabajo n8n.";
                break;
        }

        $html = "
        <div style=\"font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0f0f13; color: #f4f4f5; border-radius: 16px; overflow: hidden; border: 1px solid #27272a;\">
            <div style=\"background: linear-gradient(135deg, #18181b 0%, #09090b 100%); padding: 24px 32px; border-bottom: 1px solid #27272a;\">
                <span style=\"font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #4ade80; font-weight: 700;\">UrbanSole 3D · Automatización n8n</span>
                <h2 style=\"margin: 8px 0 0; font-size: 20px; color: #ffffff;\">{$subject}</h2>
            </div>
            <div style=\"padding: 28px 32px;\">
                <p style=\"font-size: 15px; line-height: 1.6; color: #d4d4d8; margin-top: 0;\">{$summary}</p>
                <div style=\"background: #18181b; border-left: 4px solid #4ade80; padding: 16px; border-radius: 8px; margin: 20px 0;\">
                    <p style=\"margin: 0 0 6px; font-size: 12px; color: #a1a1aa; text-transform: uppercase;\">Detalle Técnico del Evento</p>
                    <p style=\"margin: 4px 0; font-size: 13px;\"><strong>Evento:</strong> <code>{$event}</code></p>
                    <p style=\"margin: 4px 0; font-size: 13px;\"><strong>Destinatario:</strong> {$recipient}</p>
                    <p style=\"margin: 4px 0; font-size: 13px;\"><strong>Fecha/Hora:</strong> " . now()->format('d/m/Y H:i:s') . "</p>
                </div>
                <p style=\"font-size: 12px; color: #71717a; margin-top: 24px;\">
                    Correo generado automáticamente por el flujo <strong>Laravel 11 API → Webhook → n8n → Email</strong> (Programación IV - UTN Facultad Regional Concordia).
                </p>
            </div>
        </div>";

        return [
            'to' => $recipient,
            'admin_copy' => $recipientEmail,
            'subject' => $subject,
            'summary' => $summary,
            'html' => trim($html),
        ];
    }
}
