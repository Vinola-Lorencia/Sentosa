<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
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

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// ✅ Dashboard pakai controller
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
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

    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('suppliers', SupplierController::class)->except(['show']);
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::resource('purchases', PurchaseController::class);
    Route::resource('sales', SaleController::class);
    Route::resource('pengiriman', PengirimanController::class);
});

require __DIR__.'/auth.php';