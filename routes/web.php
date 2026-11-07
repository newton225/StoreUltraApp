<?php

use App\Http\Controllers\AccessLogController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttachmentTypeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CashCloseController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KitController;
use App\Http\Controllers\KitVersionController;
use App\Http\Controllers\ListExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderAdvancePaymentController;
use App\Http\Controllers\OrderBatchController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderDocumentArchiveController;
use App\Http\Controllers\OrderProformaController;
use App\Http\Controllers\OrderTransitionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\Settings\BusinessSettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopPaymentController;
use App\Http\Controllers\SupplyPlanController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('tours/{tour}', [TourController::class, 'store'])->name('tours.store');
    Route::get('dashboard/synthesis', [DashboardController::class, 'pdf'])->name('dashboard.pdf');
    Route::get('exports/{report}', [ExportController::class, 'show'])->name('exports.show');
    Route::get('list-exports/{view}/{format}', [ListExportController::class, 'show'])->name('list-exports.show');

    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::patch('clients/{client}/status', [ClientController::class, 'status'])->name('clients.status');
    Route::get('order-documents/zip', [OrderDocumentArchiveController::class, 'show'])->middleware('throttle:archives')
        ->name('orders.documents.zip');
    Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('deliveries/{delivery}/signature', [DeliveryController::class, 'signature'])->name('deliveries.signature');
    Route::post('orders/{order}/deliveries', [DeliveryController::class, 'store'])->middleware('throttle:uploads')
        ->name('orders.deliveries.store');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
    Route::post('orders/{order}/transition', [OrderTransitionController::class, 'store'])->name('orders.transition');

    Route::middleware('can:manage-orders')->group(function () {
        Route::post('orders/advance-payments', [OrderAdvancePaymentController::class, 'store'])->name('orders.advance-payments.store');
        Route::post('orders/proformas', [OrderProformaController::class, 'store'])->name('orders.proformas.store');
        Route::post('orders/batch', [OrderBatchController::class, 'store'])->name('orders.batch.store');
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents/{document}/cancel', [DocumentController::class, 'cancel'])->name('documents.cancel');
        Route::patch('invoices/{invoice}/fne', [InvoiceController::class, 'fne'])->name('invoices.fne');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/arrears', [PaymentController::class, 'arrears'])->name('payments.arrears');
        Route::post('clients/{client}/plans', [SupplyPlanController::class, 'store'])->name('clients.plans.store');
        Route::patch('plans/{plan}', [SupplyPlanController::class, 'update'])->name('plans.update');
    });

    Route::middleware('can:manage-purchases')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('purchases/generate', [PurchaseController::class, 'generate'])->name('purchases.generate');
        Route::get('purchases/{purchaseOrder}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('purchases/{purchaseOrder}/send', [PurchaseController::class, 'send'])->name('purchases.send');
        Route::post('purchases/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::post('purchases/{purchaseOrder}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
        Route::post('shops/{shop}/payments', [ShopPaymentController::class, 'store'])->name('shops.payments.store');
    });

    Route::middleware('can:manage-cash')->group(function () {
        Route::get('cash', [CashMovementController::class, 'index'])->name('cash.index');
        Route::post('cash', [CashMovementController::class, 'store'])->name('cash.store');
        Route::post('cash/{cashMovement}/cancel', [CashMovementController::class, 'cancel'])->name('cash.cancel');
        Route::post('cash/closings', [CashCloseController::class, 'store'])->name('cash.closings.store');
        Route::post('cash/link-expenses', [CashCloseController::class, 'link'])->name('cash.link-expenses');
    });

    Route::middleware('can:manage-expenses')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware('throttle:uploads')->name('expenses.store');
        Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
        Route::get('expenses/{expense}/proof', [ExpenseController::class, 'proof'])->name('expenses.proof');
    });

    Route::get('profits', [ProfitController::class, 'index'])->middleware('can:view-profit')->name('profits.index');

    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('profiles/{profile}/rights', [ProfileController::class, 'toggleRight'])->name('profiles.rights');
        Route::resource('profiles', ProfileController::class)->except(['show']);
    });

    Route::middleware('can:manage-catalog')->group(function () {
        Route::resource('shops', ShopController::class)->except(['show', 'destroy']);
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::resource('kits', KitController::class)->except(['edit', 'destroy']);
        Route::post('kits/{kit}/versions', [KitVersionController::class, 'store'])->name('kits.versions.store');
        Route::patch('products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
        Route::patch('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::post('products/{product}/prices', [ProductPriceController::class, 'store'])->name('products.prices.store');
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::resource('payment-methods', PaymentMethodController::class)->except(['show', 'destroy']);
        Route::resource('expense-types', ExpenseTypeController::class)->only(['index', 'store', 'update']);
        Route::resource('attachment-types', AttachmentTypeController::class)->only(['index', 'store', 'update']);
        Route::get('attachments', [AttachmentController::class, 'index'])->name('attachments.index');
        Route::post('attachments', [AttachmentController::class, 'store'])->middleware('throttle:uploads')->name('attachments.store');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::get('attachments/{attachment}/file', [AttachmentController::class, 'file'])->name('attachments.file');
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit-log/archives/{month}', [AuditLogController::class, 'download'])->where('month', '[0-9]{4}-[0-9]{2}')->name('audit.archives.download');
        Route::get('access-log', [AccessLogController::class, 'index'])->name('access.index');
        Route::resource('zones', ZoneController::class)->except(['show', 'destroy']);
        Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
        Route::patch('company', [CompanyController::class, 'update'])->name('company.update');
        Route::post('company/logo', [CompanyController::class, 'storeLogo'])->middleware('throttle:uploads')->name('company.logo.store');
        Route::delete('company/logo', [CompanyController::class, 'destroyLogo'])->name('company.logo.destroy');
        Route::get('company/logo', [CompanyController::class, 'logo'])->name('company.logo');
        Route::get('parameters', [BusinessSettingsController::class, 'edit'])->name('parameters.edit');
        Route::patch('parameters', [BusinessSettingsController::class, 'update'])->name('parameters.update');
    });
});

require __DIR__.'/settings.php';
