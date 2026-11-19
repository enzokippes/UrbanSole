<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderReceiptService
{
    public const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'transfer' => 'Transferencia bancaria',
        'card' => 'Tarjeta (Débito / Crédito)',
        'cash' => 'Efectivo al recibir / retirar',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'pending' => 'Pendiente de pago',
        'proof_submitted' => 'Comprobante enviado',
        'paid' => 'Pago acreditado',
    ];

    public const DELIVERY_METHOD_LABELS = [
        'standard' => 'Envío a domicilio',
        'pickup' => 'Retiro en tienda',
    ];

    public function statusLabel(?string $status): string
    {
        $key = $status ?: 'pending';

        return self::STATUS_LABELS[$key] ?? ucfirst($key);
    }

    public function paymentMethodLabel(?string $method): string
    {
        $key = $method ?: 'transfer';

        return self::PAYMENT_METHOD_LABELS[$key] ?? ucfirst($key);
    }

    public function paymentStatusLabel(?string $status): string
    {
        $key = $status ?: 'pending';

        return self::PAYMENT_STATUS_LABELS[$key] ?? ucfirst($key);
    }

    public function deliveryMethodLabel(?string $method): string
    {
        $key = $method ?: 'standard';

        return self::DELIVERY_METHOD_LABELS[$key] ?? ucfirst($key);
    }

    public function filename(Order $order): string
    {
        $num = $order->user_order_number ?: $order->id;

        return "recibo-urbansole-pedido-{$num}.pdf";
    }

    public function renderPdfContent(Order $order): string
    {
        $order->loadMissing(['items', 'user:id,name,email']);

        return Pdf::loadView('pdf.order-receipt', [
            'order' => $order,
            'statusLabel' => $this->statusLabel($order->status),
            'paymentMethodLabel' => $this->paymentMethodLabel($order->payment_method),
            'paymentStatusLabel' => $this->paymentStatusLabel($order->payment_status),
            'deliveryMethodLabel' => $this->deliveryMethodLabel($order->delivery_method),
        ])->setPaper('a4')->output();
    }

    public function buildItemsPayload(Order $order): array
    {
        $order->loadMissing('items');

        return $order->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'size' => $item->size,
            'color' => $item->color,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'subtotal' => round((float) $item->unit_price * (int) $item->quantity, 2),
        ])->values()->all();
    }
}
