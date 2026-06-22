<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Twilio\Rest\Client;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\WhatsAppSession;

class WhatsAppController extends Controller
{
    private const MAX_HISTORY = 20;
    private const MAX_IMAGES  = 3;

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function handle(Request $request): \Illuminate\Http\Response
    {
        $from = $request->input('From');
        $body = $request->input('Body');

        $session = WhatsAppSession::firstOrCreate(
            ['phone_number' => $from],
            ['messages' => [], 'cart' => [], 'step' => 'greeting']
        );

        $reply          = '';
        $productsToShow = collect();

        try {
            // Step-based routing takes priority over intent detection
            if ($session->step === 'need_name') {
                $session->customer_name = trim($body);
                $session->step          = 'need_address';
                $reply = "Terima kasih, *{$session->customer_name}*! Sekarang tolong berikan alamat pengiriman Anda.";

            } elseif ($session->step === 'need_address') {
                $session->address = trim($body);
                $session->save();
                [$reply, $productsToShow] = $this->createOrder($session);

            } else {
                // Stage 1: GPT extracts intent, mode, and keywords in one fast call
                $intent    = $this->extractIntent($body);
                $mode      = $intent['mode']       ?? 'chat';
                $keywords  = $intent['keywords']    ?? [];
                $bulkItems = $intent['bulk_items']  ?? [];

                if ($mode === 'cancel') {
                    $session->cart = [];
                    $session->save();
                    $reply = 'Pesanan dibatalkan. Ada yang bisa kami bantu lagi?';

                } elseif ($mode === 'done') {
                    if (empty($session->cart)) {
                        $reply = 'Belum ada produk dalam keranjang. Silakan pilih produk terlebih dahulu.';
                    } else {
                        $reply = $this->formatCartSummary($session->cart)
                               . "\n\nKetik *ya* untuk konfirmasi atau *batal* untuk membatalkan.";
                    }

                } elseif ($mode === 'confirm') {
                    if (empty($session->cart)) {
                        $reply = 'Belum ada produk dalam keranjang. Silakan pilih produk terlebih dahulu.';
                    } elseif (empty($session->customer_name)) {
                        $session->step = 'need_name';
                        $session->save();
                        $reply = 'Siap! Boleh minta nama Anda untuk pesanan ini?';
                    } elseif (empty($session->address)) {
                        $session->step = 'need_address';
                        $session->save();
                        $reply = "Boleh minta alamat pengirimannya, *{$session->customer_name}*?";
                    } else {
                        [$reply, $productsToShow] = $this->createOrder($session);
                    }

                } elseif ($mode === 'bulk' && !empty($bulkItems)) {
                    [$reply, $productsToShow] = $this->handleBulkOrder($bulkItems, $session);

                } else {
                    // Stage 2: search DB with extracted keywords
                    $matchedProducts = $this->searchByKeywords($keywords);

                    // Stage 3: GPT generates reply using only the relevant product subset
                    [$reply, $productsToShow, $cartAdd] = $this->generateChatReply($body, $session, $matchedProducts);

                    $cart = $session->cart ?? [];
                    foreach ($cartAdd as $item) {
                        $product = Product::where('stock', '>', 0)
                            ->where('product_name', 'like', '%' . ($item['product_name'] ?? '') . '%')
                            ->first();
                        if ($product) {
                            $cart = $this->addToCart($cart, $product, (float)($item['qty'] ?? 1), $item['unit'] ?? $product->sale_unit);
                        }
                    }

                    $history   = $session->messages ?? [];
                    $history[] = ['role' => 'user',      'content' => $body];
                    $history[] = ['role' => 'assistant',  'content' => $reply];

                    $session->cart     = $cart;
                    $session->messages = $history;
                    $session->step     = 'chat';
                    $session->save();
                }
            }

            $session->last_activity = now();
            $session->save();

        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            \Log::warning('OpenAI rate limit hit', ['from' => $from]);
            $reply = 'Maaf, sistem kami sedang sibuk. Silakan coba lagi dalam beberapa saat.';
        } catch (\Throwable $e) {
            \Log::error('WhatsApp webhook error: ' . $e->getMessage(), ['from' => $from]);
            $reply = 'Maaf, terjadi kesalahan. Silakan coba lagi atau hubungi kami langsung.';
        }

        // Send reply + optional product images
        try {
            $this->sendMessage($from, $reply);

            $baseUrl = rtrim(env('FILESYSTEM_URL', env('APP_URL')), '/');
            foreach ($productsToShow as $product) {
                $imageUrl = $baseUrl . '/storage/' . $product->image;
                $caption  = "📦 *{$product->product_name}*\n"
                          . "💰 Rp" . number_format($product->sale_price, 0, ',', '.') . "/{$product->sale_unit}\n"
                          . "📦 Stok: {$product->stock} {$product->sale_unit}";
                $this->sendMessage($from, $caption, $imageUrl);
            }
        } catch (\Throwable $e) {
            \Log::error('Twilio send failed: ' . $e->getMessage(), ['to' => $from]);
        }

        return response('', 200);
    }

    // ─── Stage 1: intent + keyword extraction (no product list needed) ────────

    private function extractIntent(string $body): array
    {
        $response = OpenAI::chat()->create([
            'model'           => 'gpt-4o-mini',
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                [
                    'role'    => 'system',
                    'content' => 'Kamu adalah ekstraktor intent pembelian. Analisis pesan pelanggan dan kembalikan JSON:
{
  "mode": "bulk" | "chat" | "confirm" | "done" | "cancel",
  "keywords": ["kata kunci nama produk dari pesan"],
  "bulk_items": [{"item": "nama produk", "qty": angka, "unit": "satuan"}]
}

Panduan mode:
- "bulk": pesan berisi BANYAK item sekaligus dengan jumlah masing-masing (contoh: "panci 2 lusin, bangku 3 lusin, ember 5 lusin")
- "chat": pertanyaan, satu produk, atau percakapan umum
- "confirm": setuju/konfirmasi pesanan ("ya", "iya", "ok", "setuju", "benar", "lanjut", "pesan", "order")
- "done": selesai memilih ("udah", "cukup", "itu saja", "sudah", "selesai", "segitu saja")
- "cancel": batalkan ("batal", "cancel", "tidak jadi")

bulk_items hanya diisi saat mode=bulk. unit default "buah" jika tidak disebutkan.',
                ],
                ['role' => 'user', 'content' => $body],
            ],
        ]);

        return json_decode($response->choices[0]->message->content, true) ?? [];
    }

    // ─── Stage 2: DB search with keywords ────────────────────────────────────

    private function searchByKeywords(array $keywords): \Illuminate\Support\Collection
    {
        if (empty($keywords)) {
            return Product::where('stock', '>', 0)->get();
        }

        $results = Product::where('stock', '>', 0)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('product_name', 'like', '%' . $kw . '%');
                }
            })
            ->get();

        // Fall back to full catalog if no keyword matches
        return $results->isEmpty()
            ? Product::where('stock', '>', 0)->get()
            : $results;
    }

    // ─── Stage 3: GPT reply with only the relevant product subset ────────────

    private function generateChatReply(string $body, WhatsAppSession $session, \Illuminate\Support\Collection $products): array
    {
        $cart = $session->cart ?? [];

        $productList = $products->map(fn ($p) =>
            "{$p->product_name} - Rp" . number_format($p->sale_price, 0, ',', '.') . "/{$p->sale_unit} (stok: {$p->stock})"
        )->join("\n");

        $cartSummary = empty($cart)
            ? 'Keranjang kosong.'
            : collect($cart)->map(fn ($i) => "- {$i['product_name']}: {$i['qty']} {$i['unit']}")->join("\n");

        $history  = $session->messages ?? [];
        $messages = array_merge(
            [[
                'role'    => 'system',
                'content' => "Kamu adalah asisten penjualan Toko Sentosa, toko grosir peralatan rumah tangga.

Produk relevan tersedia:
{$productList}

Keranjang saat ini:
{$cartSummary}

Panduan:
- Cocokkan nama produk yang mirip jika tidak ada yang persis sama.
- Jika pembeli mau memesan, tambahkan ke cart_add.
- Tampilkan gambar (show_products) jika pembeli menanyakan atau ingin melihat produk tertentu.
- Jawab singkat dan ramah dalam Bahasa Indonesia.

Balas dalam format JSON:
{
  \"reply\": \"teks balasan\",
  \"show_products\": [\"nama produk\"],
  \"cart_add\": [{\"product_name\": \"nama\", \"qty\": angka, \"unit\": \"satuan\"}]
}",
            ]],
            array_slice($history, -self::MAX_HISTORY),
            [['role' => 'user', 'content' => $body]]
        );

        $response = OpenAI::chat()->create([
            'model'           => 'gpt-4o-mini',
            'messages'        => $messages,
            'response_format' => ['type' => 'json_object'],
        ]);

        $raw    = $response->choices[0]->message->content;
        $result = json_decode($raw, true) ?? [];

        $reply     = $result['reply']        ?? $raw;
        $cartAdd   = $result['cart_add']     ?? [];
        $showNames = $result['show_products'] ?? [];

        $productsToShow = collect($showNames)
            ->take(self::MAX_IMAGES)
            ->map(fn ($name) => Product::where('stock', '>', 0)
                ->where('product_name', 'like', '%' . $name . '%')
                ->first())
            ->filter(fn ($p) => $p && $p->image);

        return [$reply, $productsToShow, $cartAdd];
    }

    // ─── Bulk order mode ──────────────────────────────────────────────────────

    private function handleBulkOrder(array $bulkItems, WhatsAppSession $session): array
    {
        $cart     = $session->cart ?? [];
        $matched  = [];
        $clarify  = [];
        $notFound = [];

        foreach ($bulkItems as $item) {
            $name = $item['item'] ?? '';
            $qty  = (float)($item['qty']  ?? 1);
            $unit = $item['unit'] ?? 'buah';

            $found = Product::where('stock', '>', 0)
                ->where('product_name', 'like', '%' . $name . '%')
                ->get();

            if ($found->count() === 1) {
                $product   = $found->first();
                $cart      = $this->addToCart($cart, $product, $qty, $unit);
                $matched[] = "✅ {$product->product_name} × {$qty} {$unit} — Rp"
                           . number_format($product->sale_price * $qty, 0, ',', '.');
            } elseif ($found->count() > 1) {
                $options   = $found->take(3)->map(fn ($p) => "  • {$p->product_name}")->join("\n");
                $clarify[] = "❓ *{$name}* ada beberapa pilihan:\n{$options}";
            } else {
                $notFound[] = "❌ {$name} — tidak ditemukan";
            }
        }

        $session->cart = $cart;
        $session->save();

        $lines = ['📋 *Rekap Pesanan:*'];

        if (!empty($matched)) {
            $lines[] = "\n\n*Berhasil ditambahkan:*\n" . implode("\n", $matched);
        }
        if (!empty($clarify)) {
            $lines[] = "\n\n*Perlu klarifikasi:*\n" . implode("\n\n", $clarify);
        }
        if (!empty($notFound)) {
            $lines[] = "\n\n*Tidak ditemukan:*\n" . implode("\n", $notFound);
        }

        if (!empty($cart)) {
            $total   = collect($cart)->sum('subtotal');
            $lines[] = "\n\n💰 *Total sementara: Rp" . number_format($total, 0, ',', '.') . "*";
            $lines[] = "\n\nKetik *selesai* untuk lihat ringkasan atau tambah produk lagi.";
        }

        return [implode('', $lines), collect()];
    }

    // ─── Create Sale + SaleDetails + decrement stock ──────────────────────────

    private function createOrder(WhatsAppSession $session): array
    {
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            return ['Keranjang kosong. Silakan pilih produk terlebih dahulu.', collect()];
        }

        $sale = null;

        DB::transaction(function () use ($session, $cart, &$sale) {
            $customer = Customer::firstOrCreate(
                ['phone' => $session->phone_number],
                [
                    'customer_name' => $session->customer_name ?? 'Pelanggan WhatsApp',
                    'address'       => $session->address       ?? '',
                ]
            );

            // Keep customer info current
            $customer->update([
                'customer_name' => $session->customer_name ?? $customer->customer_name,
                'address'       => $session->address       ?? $customer->address,
            ]);

            $total = collect($cart)->sum('subtotal');

            $sale = Sale::create([
                'customer_id'    => $customer->id,
                'user_id'        => 1,
                'sale_date'      => now()->toDateString(),
                'payment_method' => 'Tunai',
                'payment_status' => 'Belum Bayar',
                'total_amount'   => $total,
                'is_new'         => true,
                'notes'          => 'Pesanan via WhatsApp (' . $session->phone_number . ')',
            ]);

            foreach ($cart as $item) {
                SaleDetail::create([
                    'sale_id'    => $sale->sale_id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['qty'],
                    'unit'       => $item['unit'],
                    'price'      => $item['price'],
                    'discount'   => 0,
                    'subtotal'   => $item['subtotal'],
                ]);

                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['qty']);
            }
        });

        $total     = collect($cart)->sum('subtotal');
        $invoiceNo = str_pad($sale->sale_id, 6, '0', STR_PAD_LEFT);
        $itemList  = collect($cart)
            ->map(fn ($i) => "- {$i['product_name']}: {$i['qty']} {$i['unit']}")
            ->join("\n");

        $session->cart = [];
        $session->step = 'chat';
        $session->save();

        $reply = "✅ *Pesanan Berhasil Dibuat!*\n\n"
               . "🧾 No. Invoice: *INV-{$invoiceNo}*\n"
               . "👤 Nama: {$session->customer_name}\n"
               . "📍 Alamat: {$session->address}\n\n"
               . "*Produk:*\n{$itemList}\n\n"
               . "💰 *Total: Rp" . number_format($total, 0, ',', '.') . "*\n"
               . "💳 Status: Belum Bayar\n\n"
               . "Terima kasih sudah berbelanja di Toko Sentosa! 🙏";

        return [$reply, collect()];
    }

    // ─── Cart helper ──────────────────────────────────────────────────────────

    private function addToCart(array $cart, Product $product, float $qty, string $unit): array
    {
        foreach ($cart as &$item) {
            if ($item['product_id'] === $product->id) {
                $item['qty']     += $qty;
                $item['subtotal'] = $item['price'] * $item['qty'];
                return $cart;
            }
        }

        $cart[] = [
            'product_id'   => $product->id,
            'product_name' => $product->product_name,
            'qty'          => $qty,
            'unit'         => $unit ?: $product->sale_unit,
            'price'        => (float)$product->sale_price,
            'subtotal'     => (float)$product->sale_price * $qty,
        ];

        return $cart;
    }

    private function formatCartSummary(array $cart): string
    {
        $total    = collect($cart)->sum('subtotal');
        $itemList = collect($cart)
            ->map(fn ($i) => "- {$i['product_name']}: {$i['qty']} {$i['unit']} — Rp" . number_format($i['subtotal'], 0, ',', '.'))
            ->join("\n");

        return "🛒 *Ringkasan Pesanan:*\n{$itemList}\n\n💰 *Total: Rp" . number_format($total, 0, ',', '.') . "*";
    }

    // ─── Twilio ───────────────────────────────────────────────────────────────

    private function sendMessage(string $to, string $body, ?string $mediaUrl = null): void
    {
        $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));

        $params = [
            'from' => env('TWILIO_WHATSAPP_FROM'),
            'body' => $body,
        ];

        if ($mediaUrl) {
            $params['mediaUrl'] = [$mediaUrl];
        }

        $twilio->messages->create($to, $params);
    }
}
