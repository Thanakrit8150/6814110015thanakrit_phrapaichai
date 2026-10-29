<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware(['auth', 'role:staff'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::resource('customers', CustomerController::class)->except('show');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{order}', [SaleController::class, 'show'])->name('sales.show');
    Route::patch('/sales/{order}/payment', [SaleController::class, 'updatePayment'])->name('sales.payment');
    Route::patch('/sales/{order}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/manual.pdf', [ReportController::class, 'manual'])->name('reports.manual');
    Route::middleware('role:admin')->group(function () {
        Route::resource('employees', EmployeeController::class)->except('show', 'destroy');
        Route::resource('products', ProductController::class)->except('show');
    });
});
