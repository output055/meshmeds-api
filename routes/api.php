<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\VoidSaleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\ReversalLogController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ReportController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    
    // Inventory
    Route::apiResource('drugs', DrugController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('expenses', ExpenseController::class);
    Route::get('dashboard-stats', [DashboardController::class, 'index']);
    Route::patch('/drugs/{drug}/restock', [DrugController::class, 'restock']);
    
    // POS Endpoint
    Route::post('/pos/checkout', [PosController::class, 'store']);
    Route::post('/pos/void/{receipt_number}', [VoidSaleController::class, 'reverse']);

    // User Management
    Route::apiResource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);

    // Sales & Reversals History
    Route::get('/sales', [SalesController::class, 'index']);
    Route::get('/sales/{sale}', [SalesController::class, 'show']);
    Route::get('/reversals', [ReversalLogController::class, 'index']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/reports/financial', [ReportController::class, 'getFinancialReport']);
});
