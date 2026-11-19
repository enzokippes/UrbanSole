<?php

namespace App\Services;

use Composer\CaBundle\CaBundle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

class NotificationService
{
    /**
     * Genera la notificación HTML (con PDF adjunto cuando corresponde), la envía
     * directamente por Gmail SMTP y opcionalmente la reenvía a un webhook externo si está configurado.
     */
    public function dispatch(string $event, array $data = [], ?string $triggeredBy = null, ?string $overrideEmail = null): array
    {
        $enabled = (bool) config('services.n8n.enabled', true);
        $webhookUrl = (string) config('services.n8n.webhook_url', '');
        $notifyEmail = $overrideEmail ?: (string) config('services.n8n.notify_email', 'admin@urbansole.com');

        $emailNotification = $this->buildEmailNotification($event, $data, $notifyEmail);

        $payload = [
            'event' => $event,
            'source' => 'UrbanSole API',
            'timestamp' => now()->toIso8601String(),
            'triggered_by' => $triggeredBy ?? 'system',
            'email_notification' => $emailNotification,
            'data' => $data,
        ];

        if (! $enabled) {
            return [
                'status' => 'disabled',
                'delivered' => false,
                'smtp_delivered' => false,
                'http_status' => null,
                'notify_email' => $notifyEmail,
                'delivery_channel' => 'Disabled',
                'payload' => $payload,
                'message' => 'El envío automático de notificaciones está deshabilitado.',
            ];
        }

        $smtpResult = $this->sendDirectSmtpEmail($emailNotification);
        $recipient = $emailNotification['to'] ?? $notifyEmail;

        if ($smtpResult['sent'] && empty($webhookUrl)) {
            return [
                'status' => 'dispatched',
                'delivered' => true,
                'smtp_delivered' => true,
                'http_status' => 200,
                'notify_email' => $recipient,
                'delivery_channel' => 'Gmail SMTP',
                'payload' => $payload,
                'message' => "Correo enviado correctamente a {$recipient}.",
            ];
        }

        if (empty($webhookUrl)) {
            return [
                'status' => 'queued_offline',
                'delivered' => false,
                'smtp_delivered' => false,
                'http_status' => null,
                'notify_email' => $recipient,
                'delivery_channel' => 'SMTP',
                'payload' => $payload,
                'message' => "Evento '{$event}' registrado. Configurá Gmail SMTP en .env para enviar correos en vivo a {$recipient}.",
            ];
        }

        try {
            $response = Http::timeout($smtpResult['sent'] ? 1 : 2)
                ->acceptJson()
                ->withHeaders([
                    'X-UrbanSole-Event' => $event,
                    'X-UrbanSole-Source' => 'UrbanSole-API',
                ])
                ->post($webhookUrl, $payload);

            $webhookDelivered = $response->successful();
            $delivered = $webhookDelivered || $smtpResult['sent'];

            return [
                'status' => $delivered ? 'dispatched' : 'webhook_error',
                'delivered' => $delivered,
                'smtp_delivered' => $smtpResult['sent'],
                'http_status' => $response->status(),
                'notify_email' => $recipient,
                'delivery_channel' => $smtpResult['sent'] ? 'Gmail SMTP' : 'Webhook externo',
                'payload' => $payload,
                'message' => $smtpResult['sent']
                    ? "Correo enviado correctamente a {$recipient}."
                    : ($webhookDelivered
                        ? "Notificación '{$event}' enviada para {$recipient}."
                        : "El servidor externo respondió HTTP {$response->status()} al procesar '{$event}'."),
            ];
        } catch (Throwable $e) {
            if ($smtpResult['sent']) {
                return [
                    'status' => 'dispatched',
                    'delivered' => true,
                    'smtp_delivered' => true,
                    'http_status' => 200,
                    'notify_email' => $recipient,
                    'delivery_channel' => 'Gmail SMTP',
                    'payload' => $payload,
                    'message' => "Correo enviado correctamente a {$recipient}.",
                ];
            }

            Log::info("Notificación '{$event}' registrada sin envío SMTP ni webhook externo: " . $e->getMessage(), [
                'event' => $event,
                'notify_email' => $notifyEmail,
            ]);

            return [
                'status' => 'queued_offline',
                'delivered' => false,
                'smtp_delivered' => false,
                'http_status' => null,
                'notify_email' => $recipient,
                'delivery_channel' => 'Sin conexión SMTP',
                'payload' => $payload,
                'message' => "Evento '{$event}' registrado (verificá las credenciales SMTP para enviar el correo a {$recipient}).",
            ];
        }
    }

    /**
     * Envía el correo HTML (y adjunta el PDF si corresponde) directamente por SMTP desde Laravel
     * cuando MAIL_USERNAME y MAIL_PASSWORD están configuradas en .env.
     */
    protected function sendDirectSmtpEmail(array $emailNotification): array
    {
        if (app()->environment('testing')) {
            return ['sent' => false];
        }

        $username = trim((string) config('mail.mailers.smtp.username', ''));
        $password = str_replace(' ', '', trim((string) config('mail.mailers.smtp.password', '')));
        $to = trim((string) ($emailNotification['to'] ?? ''));

        if ($username === '' || $password === '' || $to === '') {
            return ['sent' => false];
        }

        try {
            config(['mail.mailers.smtp.password' => $password]);
            $mailer = Mail::mailer('smtp');
            $transport = $mailer->getSymfonyTransport();

            if (method_exists($transport, 'getStream')) {
                $stream = $transport->getStream();
                if ($stream instanceof SocketStream) {
                    $options = $stream->getStreamOptions();
                    $options['ssl']['cafile'] = CaBundle::getSystemCaRootBundlePath();
                    $stream->setStreamOptions($options);
                }
            }

            $mailer->html($emailNotification['html'] ?? '', function ($message) use ($emailNotification, $to) {
                $message->to($to)
                    ->subject($emailNotification['subject'] ?? 'Notificación UrbanSole 3D');

                $attachment = $emailNotification['attachment'] ?? null;
                if (is_array($attachment) && ! empty($attachment['content_base64']) && ! empty($attachment['filename'])) {
                    $binary = base64_decode($attachment['content_base64'], true);
                    if ($binary !== false && $binary !== '') {
                        $message->attachData(
                            $binary,
                            $attachment['filename'],
                            ['mime' => $attachment['mime_type'] ?? 'application/pdf']
                        );
                    }
                }
            });

            Log::info("Correo SMTP enviado exitosamente a {$to}: " . ($emailNotification['subject'] ?? ''));

            return ['sent' => true];
        } catch (Throwable $e) {
            Log::warning("No se pudo enviar correo SMTP directo a {$to}: " . $e->getMessage());

            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Construye el asunto y cuerpo HTML del correo electrónico según el evento de la tienda.
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

            case 'order.created':
                $orderId = $data['order_id'] ?? '-';
                $customerName = $data['customer_name'] ?? ($data['recipient'] ?? 'Cliente');
                $total = isset($data['total']) ? '$' . number_format((float) $data['total'], 2) : '$0.00';
                $subject = "🧾 [UrbanSole 3D] Recibo de compra — Pedido #{$orderId} ({$total})";
                $summary = "Gracias por tu compra, {$customerName}. Confirmamos tu pedido #{$orderId} por un total de {$total}.";
                break;

            case 'order.status_updated':
                $orderId = $data['order_id'] ?? '-';
                $statusLabel = $data['status_label'] ?? ($data['status'] ?? 'Actualizado');
                $customerName = $data['customer_name'] ?? ($data['recipient'] ?? 'Cliente');
                $subject = "📦 [UrbanSole 3D] Tu pedido #{$orderId} ahora está: {$statusLabel}";
                $summary = "Hola {$customerName}, te informamos que el estado de tu pedido #{$orderId} fue actualizado a '{$statusLabel}'.";
                if (! empty($data['note'])) {
                    $summary .= ' ' . $data['note'];
                }
                break;

            case 'order.payment_proof_submitted':
                $orderId = $data['order_id'] ?? '-';
                $customerName = $data['customer_name'] ?? 'Cliente';
                $total = isset($data['total']) ? '$' . number_format((float) $data['total'], 2) : '$0.00';
                $ref = ! empty($data['payment_reference']) ? " (Ref: {$data['payment_reference']})" : '';
                $subject = "💸 [UrbanSole Admin] Comprobante recibido — Pedido #{$orderId} ({$total})";
                $summary = "{$customerName} adjuntó un comprobante de transferencia{$ref} para el pedido #{$orderId} por {$total}. Podés verificarlo desde el panel Admin → Pedidos.";
                break;

            case 'catalog.3d.synced':
                $totalProducts = $data['total_products'] ?? 0;
                $modelsCount = $data['models_3d_count'] ?? 0;
                $totalStock = $data['total_stock'] ?? 0;
                $subject = "🔄 [UrbanSole Catálogo 3D] Resumen de sincronización de inventario";
                $summary = "Catálogo sincronizado correctamente: {$totalProducts} productos registrados, {$modelsCount} modelos 3D activos y {$totalStock} pares en stock total.";
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
                $subject = "🔔 [UrbanSole 3D] Notificación del sistema: {$event}";
                $summary = "Se emitió la notificación '{$event}' desde UrbanSole.";
                break;
        }

        $orderItemsHtml = '';
        if (in_array($event, ['order.created', 'order.status_updated'], true) && ! empty($data['items']) && is_array($data['items'])) {
            $rows = '';
            foreach ($data['items'] as $item) {
                $itemName = htmlspecialchars((string) ($item['product_name'] ?? 'Producto'), ENT_QUOTES, 'UTF-8');
                $itemSize = htmlspecialchars((string) ($item['size'] ?? '-'), ENT_QUOTES, 'UTF-8');
                $itemColor = htmlspecialchars((string) ($item['color'] ?? '-'), ENT_QUOTES, 'UTF-8');
                $qty = (int) ($item['quantity'] ?? 1);
                $subtotal = number_format((float) ($item['subtotal'] ?? ((float) ($item['unit_price'] ?? 0) * $qty)), 2);
                $rows .= "<tr>
                    <td style=\"padding:8px 10px;border-bottom:1px solid #27272a;color:#f4f4f5;\">{$itemName} (Talle {$itemSize} · {$itemColor})</td>
                    <td style=\"padding:8px 10px;border-bottom:1px solid #27272a;text-align:center;color:#d4d4d8;\">×{$qty}</td>
                    <td style=\"padding:8px 10px;border-bottom:1px solid #27272a;text-align:right;color:#4ade80;font-weight:700;\">\${$subtotal}</td>
                </tr>";
            }
            $shippingLine = htmlspecialchars(trim(($data['address'] ?? '') . ', ' . ($data['city'] ?? '') . ' (' . ($data['postal_code'] ?? '') . ')'), ENT_QUOTES, 'UTF-8');
            $paymentLine = htmlspecialchars((string) ($data['payment_label'] ?? 'Transferencia bancaria'), ENT_QUOTES, 'UTF-8');
            $deliveryLine = htmlspecialchars((string) ($data['delivery_label'] ?? 'Envío a domicilio'), ENT_QUOTES, 'UTF-8');
            $totalFormatted = number_format((float) ($data['total'] ?? 0), 2);
            $bankTransferHtml = '';
            if ($event === 'order.created' && ($data['payment_method'] ?? '') === 'transfer' && ! empty($data['bank'])) {
                $bankAlias = htmlspecialchars((string) ($data['bank']['alias'] ?? ''), ENT_QUOTES, 'UTF-8');
                $bankCbu = htmlspecialchars((string) ($data['bank']['cbu'] ?? ''), ENT_QUOTES, 'UTF-8');
                $bankHolder = htmlspecialchars((string) ($data['bank']['holder'] ?? ''), ENT_QUOTES, 'UTF-8');
                $bankName = htmlspecialchars((string) ($data['bank']['bank_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $bankTransferHtml = "
                <div style=\"background:#14281d;border:1px solid #22c55e;border-radius:10px;padding:14px 16px;margin:14px 0;\">
                    <p style=\"margin:0 0 6px;font-size:13px;font-weight:800;color:#4ade80;\">Datos para completar tu transferencia (\${$totalFormatted})</p>
                    <p style=\"margin:3px 0;font-size:13px;color:#e4e4e7;\"><strong>Alias:</strong> {$bankAlias}</p>
                    <p style=\"margin:3px 0;font-size:13px;color:#e4e4e7;\"><strong>CBU / CVU:</strong> {$bankCbu}</p>
                    <p style=\"margin:3px 0;font-size:13px;color:#e4e4e7;\"><strong>Titular:</strong> {$bankHolder} ({$bankName})</p>
                    <p style=\"margin:6px 0 0;font-size:12px;color:#bbf7d0;\">Luego de transferir, subí tu comprobante desde la sección <strong>Mis pedidos</strong>.</p>
                </div>";
            }
            $orderItemsHtml = "
                <table style=\"width:100%;border-collapse:collapse;margin:18px 0;background:#18181b;border-radius:8px;overflow:hidden;\">
                    <thead>
                        <tr style=\"background:#27272a;color:#a1a1aa;font-size:11px;text-transform:uppercase;\">
                            <th style=\"padding:8px 10px;text-align:left;\">Producto</th>
                            <th style=\"padding:8px 10px;text-align:center;\">Cant.</th>
                            <th style=\"padding:8px 10px;text-align:right;\">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan=\"2\" style=\"padding:10px;text-align:right;color:#a1a1aa;font-weight:700;\">Total:</td>
                            <td style=\"padding:10px;text-align:right;color:#4ade80;font-size:16px;font-weight:800;\">\${$totalFormatted}</td>
                        </tr>
                    </tfoot>
                </table>
                <p style=\"font-size:13px;color:#d4d4d8;margin:0 0 6px;\"><strong>Forma de pago:</strong> {$paymentLine} · <strong>Entrega:</strong> {$deliveryLine}</p>
                <p style=\"font-size:13px;color:#d4d4d8;margin:0 0 12px;\"><strong>Dirección:</strong> {$shippingLine}</p>{$bankTransferHtml}";
        }

        $html = "
        <div style=\"font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #0f0f13; color: #f4f4f5; border-radius: 16px; overflow: hidden; border: 1px solid #27272a;\">
            <div style=\"background: linear-gradient(135deg, #18181b 0%, #09090b 100%); padding: 24px 32px; border-bottom: 1px solid #27272a;\">
                <span style=\"font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #4ade80; font-weight: 700;\">UrbanSole · Notificación Oficial</span>
                <h2 style=\"margin: 8px 0 0; font-size: 20px; color: #ffffff;\">{$subject}</h2>
            </div>
            <div style=\"padding: 28px 32px;\">
                <p style=\"font-size: 15px; line-height: 1.6; color: #d4d4d8; margin-top: 0;\">{$summary}</p>
                {$orderItemsHtml}
                <div style=\"background: #18181b; border-left: 4px solid #4ade80; padding: 14px 16px; border-radius: 8px; margin: 20px 0;\">
                    <p style=\"margin: 4px 0; font-size: 13px;\"><strong>Cuenta:</strong> {$recipient}</p>
                    <p style=\"margin: 4px 0; font-size: 13px;\"><strong>Fecha:</strong> " . now()->format('d/m/Y H:i') . "</p>
                </div>
                <p style=\"font-size: 12px; color: #71717a; margin-top: 24px;\">
                    UrbanSole &copy; " . now()->format('Y') . " · Correo automático de confirmación.
                </p>
            </div>
        </div>";

        $notification = [
            'to' => $recipient,
            'admin_copy' => $recipientEmail,
            'subject' => $subject,
            'summary' => $summary,
            'html' => trim($html),
        ];

        if (! empty($data['receipt_pdf_base64']) && ! empty($data['receipt_filename'])) {
            $notification['attachment'] = [
                'filename' => $data['receipt_filename'],
                'mime_type' => 'application/pdf',
                'content_base64' => $data['receipt_pdf_base64'],
            ];
        }

        return $notification;
    }
}
