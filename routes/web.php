<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\PiutangController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\TokoController;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:Pemilik'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Custom routes SEBELUM resource
    Route::get('purchase-orders/{id}/items', [PurchaseOrderController::class, 'getItems'])->name('purchase-orders.items');
    Route::get('purchase-orders/{id}/send-whatsapp', [PurchaseOrderController::class, 'sendWhatsApp'])->name('purchase-orders.send-whatsapp');

    // Piutang — manual routes
    Route::get('piutang', [PiutangController::class, 'index'])->name('piutang.index');
    Route::get('piutang/{id}', [PiutangController::class, 'show'])->name('piutang.show');
    Route::post('piutang/{id}/bayar', [PiutangController::class, 'bayar'])->name('piutang.bayar');
      Route::delete('piutang/{id}', [PiutangController::class, 'destroy'])->name('piutang.destroy');

    // Laporan
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('penjualan', [LaporanController::class, 'penjualan'])->name('penjualan');
        Route::get('penjualan/export-excel', [LaporanController::class, 'exportPenjualanExcel'])->name('penjualan.excel');
        Route::get('penjualan/export-pdf', [LaporanController::class, 'exportPenjualanPdf'])->name('penjualan.pdf');

        Route::get('pembelian', [LaporanController::class, 'pembelian'])->name('pembelian');
        Route::get('pembelian/export-excel', [LaporanController::class, 'exportPembelianExcel'])->name('pembelian.excel');
        Route::get('pembelian/export-pdf', [LaporanController::class, 'exportPembelianPdf'])->name('pembelian.pdf');

        Route::get('piutang', [LaporanController::class, 'piutang'])->name('piutang');
      
        Route::get('piutang/export-excel', [LaporanController::class, 'exportPiutangExcel'])->name('piutang.excel');
        Route::get('piutang/export-pdf', [LaporanController::class, 'exportPiutangPdf'])->name('piutang.pdf');
    });

    // ─── Route Pembeli ────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:Pembeli'])->prefix('toko')->name('toko.')->group(function () {
        Route::get('/', [TokoController::class, 'index'])->name('index');
        Route::get('keranjang', [TokoController::class, 'keranjang'])->name('keranjang');
        Route::post('keranjang/tambah', [TokoController::class, 'tambahKeranjang'])->name('keranjang.tambah');
        Route::patch('keranjang/{id}', [TokoController::class, 'updateKeranjang'])->name('keranjang.update');
        Route::delete('keranjang/{id}', [TokoController::class, 'hapusKeranjang'])->name('keranjang.hapus');
        Route::get('checkout', [TokoController::class, 'checkout'])->name('checkout');
        Route::post('checkout', [TokoController::class, 'prosesCheckout'])->name('checkout.proses');
        Route::get('pesanan', [TokoController::class, 'pesanan'])->name('pesanan');
    });

    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('suppliers', SupplierController::class)->except(['show']);
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::resource('purchases', PurchaseController::class);
    Route::patch('sales/{id}/tandai-diproses', [SaleController::class, 'tandaiDiproses'])->name('sales.tandai-diproses');
    Route::resource('sales', SaleController::class);
    Route::resource('pengiriman', PengirimanController::class);
});

require __DIR__.'/auth.php';