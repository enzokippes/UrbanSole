<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Compra #{{ $order->user_order_number ?: $order->id }} - UrbanSole</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #18181b;
            font-size: 12px;
            margin: 0;
            padding: 28px;
            background: #ffffff;
        }
        .header {
            background: #111115;
            color: #ffffff;
            padding: 20px 24px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .brand-accent { color: #4ade80; }
        .brand-sub {
            font-size: 10px;
            color: #a1a1aa;
            margin-top: 4px;
        }
        .receipt-meta {
            text-align: right;
            font-size: 11px;
            color: #e4e4e7;
        }
        .receipt-num {
            font-size: 15px;
            font-weight: bold;
            color: #4ade80;
            margin-bottom: 4px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 12px;
            background: #27272a;
            color: #4ade80;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-cell {
            width: 50%;
            vertical-align: top;
            padding: 12px 14px;
            background: #f4f4f5;
            border: 1px solid #e4e4e7;
        }
        .info-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #71717a;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .info-line {
            margin: 3px 0;
            font-size: 11px;
            color: #18181b;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table th {
            background: #18181b;
            color: #ffffff;
            text-align: left;
            padding: 9px 10px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table th.right,
        .items-table td.right {
            text-align: right;
        }
        .items-table th.center,
        .items-table td.center {
            text-align: center;
        }
        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e4e4e7;
            font-size: 11px;
        }
        .totals-table {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 8px;
            font-size: 12px;
        }
        .totals-table .label {
            color: #52525b;
        }
        .totals-table .val {
            text-align: right;
            font-weight: bold;
        }
        .totals-table .grand td {
            border-top: 2px solid #18181b;
            padding-top: 10px;
            font-size: 15px;
            color: #111115;
        }
        .footer {
            margin-top: 32px;
            padding-top: 12px;
            border-top: 1px solid #e4e4e7;
            font-size: 10px;
            color: #71717a;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="brand">URBAN<span class="brand-accent">SOLE</span> 3D</div>
                    <div class="brand-sub">Comprobante oficial de compra</div>
                </td>
                <td class="receipt-meta">
                    <div class="receipt-num">Recibo #{{ str_pad((string) ($order->user_order_number ?: $order->id), 6, '0', STR_PAD_LEFT) }}</div>
                    <div>Fecha: {{ $order->created_at?->format('d/m/Y H:i') }}</div>
                    <div class="status-badge">{{ $statusLabel }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="info-grid">
        <tr>
            <td class="info-cell">
                <div class="info-title">Datos del Cliente</div>
                <div class="info-line"><strong>Nombre:</strong> {{ $order->user?->name ?? $order->customer_name ?? $order->recipient }}</div>
                <div class="info-line"><strong>Correo:</strong> {{ $order->user?->email ?? $order->customer_email ?? '-' }}</div>
                <div class="info-line"><strong>Pedido N°:</strong> #{{ $order->user_order_number ?: $order->id }}</div>
                <div class="info-line"><strong>Forma de pago:</strong> {{ $paymentMethodLabel ?? 'Transferencia bancaria' }} ({{ $paymentStatusLabel ?? 'Pendiente de pago' }})</div>
                @if (!empty($order->payment_reference))
                    <div class="info-line"><strong>Ref. de pago:</strong> {{ $order->payment_reference }}</div>
                @endif
            </td>
            <td class="info-cell">
                <div class="info-title">Datos de Entrega</div>
                <div class="info-line"><strong>Modalidad:</strong> {{ $deliveryMethodLabel ?? 'Envío a domicilio' }}</div>
                <div class="info-line"><strong>Destinatario:</strong> {{ $order->recipient }}</div>
                <div class="info-line"><strong>Teléfono:</strong> {{ $order->phone }}</div>
                <div class="info-line"><strong>Dirección:</strong> {{ $order->address }}, {{ $order->city }} ({{ $order->postal_code }})</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Modelo</th>
                <th class="center">Talle</th>
                <th>Color</th>
                <th class="center">Cant.</th>
                <th class="right">Precio Unit.</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td><strong>{{ $item->product_name }}</strong></td>
                    <td class="center">{{ $item->size }}</td>
                    <td>{{ $item->color }}</td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right"><strong>${{ number_format((float) $item->unit_price * $item->quantity, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="label">Subtotal:</td>
            <td class="val">${{ number_format((float) $order->total, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Envío:</td>
            <td class="val">Gratis</td>
        </tr>
        <tr class="grand">
            <td class="label"><strong>Total pagado:</strong></td>
            <td class="val">${{ number_format((float) $order->total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Gracias por tu compra en UrbanSole.<br>
        Comprobante generado automáticamente para el pedido #{{ $order->user_order_number ?: $order->id }}.
    </div>
</body>
</html>
