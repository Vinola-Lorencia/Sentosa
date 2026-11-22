<?php

namespace App\Console\Commands;

use App\Models\Sale;
use Illuminate\Console\Command;

/**
 * Read-only: lists storefront (toko online) orders the owner hasn't
 * processed yet (is_new = true). Only runs SELECT queries — nothing is
 * created, updated, saved or deleted.
 *
 * Storefront orders are recognised by the "Pembeli: ..." note that
 * TokoController::prosesCheckout() writes; WhatsApp orders (also is_new)
 * carry "Pesanan via WhatsApp (...)" instead and are left out.
 */
class ListPendingOnlineOrders extends Command
{
    public const NOTES_PREFIX = 'Pembeli:';

    protected $signature = 'orders:list-pending-online';

    protected $description = 'Tampilkan pesanan toko online yang belum diproses (read-only)';

    public function handle(): int
    {
        $orders = Sale::query()
            ->with(['customer', 'user', 'details.product'])
            ->where('is_new', true)
            ->where('notes', 'like', self::NOTES_PREFIX.'%')
            ->orderBy('created_at')
            ->orderBy('sale_id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Tidak ada pesanan toko online yang belum diproses.');
            $this->line('Total pesanan: 0');

            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            $this->line(sprintf(
                'Pesanan #%d | %s WIB | %s',
                $order->sale_id,
                $order->created_at?->toWib()->format('d/m/Y H:i') ?? '-',
                $this->customerName($order),
            ));

            $this->table(
                ['Produk', 'Jumlah', 'Satuan', 'Stok saat ini'],
                $order->details->map(fn ($detail) => [
                    $detail->product?->product_name ?? "(produk #{$detail->product_id} tidak ditemukan)",
                    $this->formatQuantity((float) $detail->quantity),
                    $detail->unit ?: '-',
                    $detail->product?->stok_display ?? '-',
                ])->all(),
            );
        }

        $this->info("Total pesanan: {$orders->count()}");

        return self::SUCCESS;
    }

    /**
     * Linked customer first; prosesCheckout() only links one when a customer
     * shares the buyer's account name, so fall back to the name typed at
     * checkout ("Pembeli: <nama> | ...") and then the account name.
     */
    private function customerName(Sale $order): string
    {
        if ($order->customer?->customer_name) {
            return $order->customer->customer_name;
        }

        if (preg_match('/^'.preg_quote(self::NOTES_PREFIX, '/').'\s*([^|]+)/', (string) $order->notes, $matches)) {
            return trim($matches[1]);
        }

        return $order->user?->name ?? '-';
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',');
    }
}
