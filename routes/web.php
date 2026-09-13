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
    ->middleware(['auth', 'role:Pemilik'])
    ->name('dashboard');

Route::get('/api/check-product-name', [ProductController::class, 'checkProductName'])
    ->middleware('auth');

Route::get('/api/karyawan-products/{userId}', [ProductController::class, 'byKaryawan'])
    ->middleware(['auth', 'role:Pemilik']);

Route::middleware('auth')->group(function () {

    // ─── Profile (semua role) ─────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Route Pembeli ────────────────────────────────────────────────────────
    Route::middleware('role:Pembeli')->prefix('toko')->name('toko.')->group(function () {
        Route::get('/', [TokoController::class, 'index'])->name('index');
        Route::get('keranjang', [TokoController::class, 'keranjang'])->name('keranjang');
        Route::post('keranjang/tambah', [TokoController::class, 'tambahKeranjang'])->name('keranjang.tambah');
        Route::patch('keranjang/{id}', [TokoController::class, 'updateKeranjang'])->name('keranjang.update');
        Route::delete('keranjang/{id}', [TokoController::class, 'hapusKeranjang'])->name('keranjang.hapus');
        Route::get('checkout', [TokoController::class, 'checkout'])->name('checkout');
        Route::post('checkout', [TokoController::class, 'prosesCheckout'])->name('checkout.proses');
        Route::get('pesanan', [TokoController::class, 'pesanan'])->name('pesanan');
        Route::get('tagihan', [TokoController::class, 'tagihan'])->name('tagihan');
        Route::post('products/{product}/view', [TokoController::class, 'markProductViewed'])->name('products.view');
    });

    // ─── Route Pemilik & Karyawan (Produk & Penjualan, tanpa hapus) ────────────
    Route::middleware('role:Pemilik,Karyawan')->group(function () {
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::match(['post', 'patch'], 'products/{product}/photo', [ProductController::class, 'updatePhoto'])->name('products.update-photo');

        Route::patch('sales/{id}/tandai-diproses', [SaleController::class, 'tandaiDiproses'])->name('sales.tandai-diproses');
        Route::resource('sales', SaleController::class)->except(['destroy']);
    });

    // ─── Route Karyawan saja ────────────────────────────────────────────────────
    Route::get('/my-products', [ProductController::class, 'myProducts'])
        ->name('products.my')
        ->middleware('role:Karyawan');

    // ─── Route Pemilik saja ───────────────────────────────────────────────────
    Route::middleware('role:Pemilik')->group(function () {

        // Custom routes SEBELUM resource
        Route::get('purchase-orders/{id}/items', [PurchaseOrderController::class, 'getItems'])->name('purchase-orders.items');
        Route::get('purchase-orders/{id}/send-whatsapp', [PurchaseOrderController::class, 'sendWhatsApp'])->name('purchase-orders.send-whatsapp');

        // Piutang
        Route::get('piutang', [PiutangController::class, 'index'])->name('piutang.index');
        Route::get('piutang/{id}', [PiutangController::class, 'show'])->name('piutang.show');
        Route::post('piutang/{id}/bayar', [PiutangController::class, 'bayar'])->name('piutang.bayar');
        Route::delete('piutang/{id}', [PiutangController::class, 'destroy'])->name('piutang.destroy');

        // Transaksi
        Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
        Route::resource('purchases', PurchaseController::class);
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::resource('pengiriman', PengirimanController::class);
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::resource('customers', CustomerController::class)->except(['show']);
        Route::resource('suppliers', SupplierController::class)->except(['show']);

        // Master data sensitif
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);

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
    });

});

require __DIR__.'/auth.php';