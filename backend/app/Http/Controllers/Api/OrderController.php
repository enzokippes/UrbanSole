<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\NotificationService;
use App\Services\OrderReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
        protected OrderReceiptService $receiptService
    ) {}

    public function paymentInfo()
    {
        return response()->json([
            'bank' => config('services.bank'),
            'payment_methods' => [
                ['value' => 'transfer', 'label' => 'Transferencia bancaria'],
                ['value' => 'card', 'label' => 'Tarjeta (Débito / Crédito)'],
                ['value' => 'cash', 'label' => 'Efectivo al recibir / retirar'],
            ],
            'delivery_methods' => [
                ['value' => 'standard', 'label' => 'Envío a domicilio'],
                ['value' => 'pickup', 'label' => 'Retiro en tienda'],
            ],
        ]);
    }

    public function index(Request $request)
    {
        return $request->user()->orders()->with('items')->latest('id')->paginate(10);
    }

    public function show(Request $request, int $id)
    {
        return $request->user()->orders()->with('items')->findOrFail($id);
    }

    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum > 0 && ($sum % 10) === 0;
    }

    private function detectCardBrand(string $digits): string
    {
        if (str_starts_with($digits, '4')) {
            return 'Visa';
        }
        if (preg_match('/^(5[1-5]|2[2-7])/', $digits)) {
            return 'Mastercard';
        }
        if (preg_match('/^3[47]/', $digits)) {
            return 'American Express';
        }

        return 'Tarjeta';
    }

    private function authorizeCardPayment(array $data): string
    {
        $holder = trim((string) ($data['card_holder'] ?? ''));
        $digits = preg_replace('/\D+/', '', (string) ($data['card_number'] ?? ''));
        $expiry = trim((string) ($data['card_expiry'] ?? ''));
        $cvv = preg_replace('/\D+/', '', (string) ($data['card_cvv'] ?? ''));
        $installments = (int) ($data['card_installments'] ?? 1);

        if (mb_strlen($holder) < 3) {
            throw ValidationException::withMessages(['card_holder' => 'Ingresá el nombre del titular tal como figura en la tarjeta.']);
        }
        if (strlen($digits) < 13 || strlen($digits) > 19 || ! $this->passesLuhn($digits)) {
            throw ValidationException::withMessages(['card_number' => 'El número de tarjeta ingresado no es válido.']);
        }
        if (! preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $expiry, $m)) {
            throw ValidationException::withMessages(['card_expiry' => 'Ingresá el vencimiento en formato MM/AA.']);
        }
        $expMonth = (int) $m[1];
        $expYear = strlen($m[2]) === 2 ? (int) ('20' . $m[2]) : (int) $m[2];
        $nowYear = (int) now()->format('Y');
        $nowMonth = (int) now()->format('n');
        if ($expYear < $nowYear || ($expYear === $nowYear && $expMonth < $nowMonth)) {
            throw ValidationException::withMessages(['card_expiry' => 'La tarjeta ingresada está vencida.']);
        }
        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            throw ValidationException::withMessages(['card_cvv' => 'Ingresá un código de seguridad (CVV) válido de 3 o 4 dígitos.']);
        }
        if (! in_array($installments, [1, 3, 6], true)) {
            $installments = 1;
        }

        $brand = $this->detectCardBrand($digits);
        $last4 = substr($digits, -4);
        $quotaLabel = $installments === 1 ? '1 pago' : "{$installments} cuotas";
        $authCode = strtoupper(Str::random(6));

        return "{$brand} •••• {$last4} · {$quotaLabel} · Aut. {$authCode}";
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'checkout_key' => 'required|uuid',
            'recipient' => 'required|string|max:255', 'phone' => 'required|string|max:40',
            'address' => 'required|string|max:255', 'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'delivery_method' => ['nullable', Rule::in(['standard', 'pickup'])],
            'payment_method' => ['nullable', Rule::in(['transfer', 'card', 'cash'])],
            'card_holder' => 'nullable|string|max:120',
            'card_number' => 'nullable|string|max:32',
            'card_expiry' => 'nullable|string|max:10',
            'card_cvv' => 'nullable|string|max:6',
            'card_installments' => 'nullable|integer|in:1,3,6',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer',
            'items.*.size' => 'required|string|max:255',
            'items.*.color' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:100',
        ]);
        $deliveryMethod = $data['delivery_method'] ?? 'standard';
        $paymentMethod = $data['payment_method'] ?? 'transfer';
        $cardReference = null;
        if ($paymentMethod === 'card') {
            $cardReference = $this->authorizeCardPayment($data);
        }
        $lowStockByProduct = [];
        $order = DB::transaction(function () use ($request, $data, $deliveryMethod, $paymentMethod, $cardReference, &$lowStockByProduct) {
            $lowStockByProduct = [];
            // Serialize checkouts for this user and make retries safe.
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $previous = Order::where('checkout_key', $data['checkout_key'])->first();
            if ($previous) {
                abort_unless($previous->user_id === $request->user()->id, 409);

                return $previous->load('items');
            }
            $lines = [];
            foreach ($data['items'] as $item) {
                $key = json_encode([$item['product_id'], $item['size'], $item['color']]);
                if (! isset($lines[$key])) {
                    $lines[$key] = $item;
                } else {
                    $lines[$key]['quantity'] += $item['quantity'];
                }
            }
            // Consistent product lock order also coordinates catalog writes.
            $products = Product::whereIn('id', array_column($lines, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $snapshots = [];
            $totalCents = 0;
            foreach ($lines as $item) {
                $product = $products->get($item['product_id']);
                $variant = ProductVariant::where('product_id', $item['product_id'])->where('size', $item['size'])->where('color', $item['color'])->orderBy('id')->lockForUpdate()->first();
                if (! $product || ! $product->active || ! $variant || $variant->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Un producto ya no está disponible o no tiene stock suficiente. Revisá tu carrito.']);
                }
                $cents = (int) round((float) $product->price * 100);
                $totalCents += $cents * $item['quantity'];
                $variant->decrement('stock', $item['quantity']);
                $remainingStock = (int) $variant->fresh()->stock;
                if ($remainingStock <= 3) {
                    if (! isset($lowStockByProduct[$product->id])) {
                        $lowStockByProduct[$product->id] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'low_stock_variants_count' => 0,
                            'min_stock' => $remainingStock,
                        ];
                    }
                    $lowStockByProduct[$product->id]['low_stock_variants_count']++;
                    $lowStockByProduct[$product->id]['min_stock'] = min(
                        $lowStockByProduct[$product->id]['min_stock'],
                        $remainingStock
                    );
                }
                $snapshots[] = ['product_variant_id' => $variant->id, 'product_name' => $product->name, 'size' => $variant->size, 'color' => $variant->color, 'quantity' => $item['quantity'], 'unit_price' => $product->price];
            }
            $userOrderNumber = Order::where('user_id', $request->user()->id)->count() + 1;
            $isCardPaid = $paymentMethod === 'card' && $cardReference !== null;
            $order = Order::create(array_merge(
                collect($data)->only(['checkout_key', 'recipient', 'phone', 'address', 'city', 'postal_code'])->all(),
                [
                    'user_id' => $request->user()->id,
                    'user_order_number' => $userOrderNumber,
                    'customer_name' => $request->user()->name,
                    'customer_email' => $request->user()->email,
                    'status' => $isCardPaid ? 'confirmed' : 'pending',
                    'delivery_method' => $deliveryMethod,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $isCardPaid ? 'paid' : 'pending',
                    'payment_reference' => $cardReference,
                    'payment_submitted_at' => $isCardPaid ? now() : null,
                    'total' => $totalCents / 100,
                ]
            ));
            $order->items()->createMany($snapshots);

            return $order->load('items');
        }, 3);

        if ($order->wasRecentlyCreated) {
            $order->load('user:id,name,email');
            $pdfBase64 = null;
            try {
                $pdfBase64 = base64_encode($this->receiptService->renderPdfContent($order));
            } catch (\Throwable $e) {
                Log::warning("No se pudo generar el PDF para el pedido #{$order->id}: " . $e->getMessage());
            }

            $buyerEmail = $order->user?->email ?? $request->user()->email;
            $displayOrderNumber = $order->user_order_number ?: $order->id;
            $this->notifications->dispatch('order.created', [
                'order_id' => $displayOrderNumber,
                'internal_order_id' => $order->id,
                'user_id' => $order->user_id,
                'customer_name' => $order->user?->name ?? $order->recipient,
                'email' => $buyerEmail,
                'recipient' => $order->recipient,
                'phone' => $order->phone,
                'address' => $order->address,
                'city' => $order->city,
                'postal_code' => $order->postal_code,
                'delivery_method' => $order->delivery_method,
                'delivery_label' => $this->receiptService->deliveryMethodLabel($order->delivery_method),
                'payment_method' => $order->payment_method,
                'payment_label' => $this->receiptService->paymentMethodLabel($order->payment_method),
                'bank' => $order->payment_method === 'transfer' ? config('services.bank') : null,
                'total' => (float) $order->total,
                'items' => $this->receiptService->buildItemsPayload($order),
                'receipt_filename' => $this->receiptService->filename($order),
                'receipt_pdf_base64' => $pdfBase64,
                'receipt_url' => url("/api/orders/{$order->id}/receipt"),
            ], $buyerEmail, $buyerEmail);

            foreach ($lowStockByProduct as $alertPayload) {
                $this->notifications->dispatch('stock.low', $alertPayload, $buyerEmail);
            }
        }

        return response()->json($order, 201);
    }

    public function receipt(Request $request, int $id)
    {
        $order = $request->user()->orders()->with(['items', 'user:id,name,email'])->findOrFail($id);
        $pdf = $this->receiptService->renderPdfContent($order);
        $filename = $this->receiptService->filename($order);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function cancel(Request $request, int $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $order = $request->user()->orders()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden cancelar pedidos en estado pendiente.',
                ]);
            }

            $items = $order->items()->orderBy('product_variant_id')->get();
            foreach ($items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first()?->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);

            return response()->json([
                'message' => 'Pedido cancelado correctamente.',
                'order' => $order->load('items'),
            ]);
        }, 3);
    }

    public function advance(Request $request, int $id)
    {
        $order = $request->user()->orders()->with('items')->findOrFail($id);

        $nextStatus = match ($order->status) {
            'pending' => 'confirmed',
            'confirmed' => 'shipped',
            'shipped' => 'delivered',
            default => null,
        };

        if (! $nextStatus) {
            throw ValidationException::withMessages([
                'status' => 'El pedido ya alcanzó su estado final y no puede avanzar más.',
            ]);
        }

        $order->update(['status' => $nextStatus]);

        return response()->json($order->fresh()->load('items'));
    }

    public function uploadPaymentProof(Request $request, int $id)
    {
        $order = $request->user()->orders()->with('items')->findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'No podés subir un comprobante a un pedido cancelado.'], 422);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Este pedido ya tiene el pago acreditado.'], 422);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $rawFilename = urldecode((string) $request->header('X-File-Name', ''));
        $reference = trim(urldecode((string) ($request->header('X-Payment-Reference', '') ?: $request->input('reference', ''))));
        if (mb_strlen($reference) > 120) {
            $reference = mb_substr($reference, 0, 120);
        }

        $binary = null;
        $ext = null;

        if ($rawFilename !== '' && ! $request->hasFile('proof') && ! $request->hasFile('file')) {
            $ext = strtolower(pathinfo($rawFilename, PATHINFO_EXTENSION));
            if (! in_array($ext, $allowed, true)) {
                return response()->json(['message' => 'Formato no permitido. Subí JPG, PNG, WEBP o PDF.'], 422);
            }
            $binary = $request->getContent();
        } else {
            $uploaded = $request->file('proof') ?? $request->file('file');
            if (! $uploaded) {
                return response()->json(['message' => 'Seleccioná una imagen o PDF del comprobante de transferencia.'], 422);
            }
            $ext = strtolower($uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension() ?: 'jpg');
            if (! in_array($ext, $allowed, true)) {
                return response()->json(['message' => 'Formato no permitido. Subí JPG, PNG, WEBP o PDF.'], 422);
            }
            $binary = file_get_contents($uploaded->getRealPath());
        }

        if (! $binary || strlen($binary) === 0) {
            return response()->json(['message' => 'El archivo está vacío.'], 422);
        }

        if (strlen($binary) > 10 * 1024 * 1024) {
            return response()->json(['message' => 'El comprobante no puede superar los 10 MB.'], 422);
        }

        if (! empty($order->payment_proof_path) && Storage::disk('local')->exists($order->payment_proof_path)) {
            Storage::disk('local')->delete($order->payment_proof_path);
        }

        $path = "payment-proofs/order-{$order->id}-" . Str::lower(Str::random(8)) . ".{$ext}";
        Storage::disk('local')->put($path, $binary);

        $order->update([
            'payment_proof_path' => $path,
            'payment_reference' => $reference !== '' ? $reference : null,
            'payment_status' => 'proof_submitted',
            'payment_submitted_at' => now(),
        ]);

        $this->notifications->dispatch('order.payment_proof_submitted', [
            'order_id' => $order->user_order_number ?: $order->id,
            'internal_order_id' => $order->id,
            'customer_name' => $request->user()->name,
            'payment_reference' => $order->payment_reference,
            'total' => (float) $order->total,
        ], $request->user()->email);

        return response()->json([
            'message' => 'Comprobante enviado correctamente. Lo vamos a verificar para confirmar tu pedido.',
            'order' => $order->fresh()->load('items'),
        ]);
    }

    public function downloadPaymentProof(Request $request, int $id)
    {
        $order = $request->user()->orders()->findOrFail($id);

        if (empty($order->payment_proof_path) || ! Storage::disk('local')->exists($order->payment_proof_path)) {
            abort(404, 'No hay comprobante adjunto para este pedido.');
        }

        $ext = strtolower(pathinfo($order->payment_proof_path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        $num = $order->user_order_number ?: $order->id;

        return response(Storage::disk('local')->get($order->payment_proof_path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"comprobante-pedido-{$num}.{$ext}\"",
        ]);
    }
}
