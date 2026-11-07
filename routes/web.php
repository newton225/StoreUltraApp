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
        ->middleware('can:orders.download_zip')->name('orders.documents.zip');
    Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('documents', [DocumentController::class, 'store'])->middleware('can:orders.issue_documents')->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('deliveries', [DeliveryController::class, 'index'])->middleware('can:manage-deliveries')->name('deliveries.index');
    Route::get('deliveries/{delivery}/signature', [DeliveryController::class, 'signature'])->name('deliveries.signature');
    Route::post('orders/{order}/deliveries', [DeliveryController::class, 'store'])->middleware('throttle:uploads')
        ->middleware('can:orders.record_delivery')->name('orders.deliveries.store');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->middleware('can:orders.record_payment')->name('orders.payments.store');
    Route::post('orders/{order}/transition', [OrderTransitionController::class, 'store'])->name('orders.transition');

    Route::middleware('can:manage-orders')->group(function () {
        Route::post('orders/advance-payments', [OrderAdvancePaymentController::class, 'store'])->middleware('can:orders.record_payment')->name('orders.advance-payments.store');
        Route::post('orders/proformas', [OrderProformaController::class, 'store'])->middleware('can:orders.batch')->name('orders.proformas.store');
        Route::post('orders/batch', [OrderBatchController::class, 'store'])->middleware('can:orders.batch')->name('orders.batch.store');
        Route::post('documents/{document}/cancel', [DocumentController::class, 'cancel'])->middleware('can:orders.cancel_documents')->name('documents.cancel');
        Route::patch('invoices/{invoice}/fne', [InvoiceController::class, 'fne'])->middleware('can:orders.fne')->name('invoices.fne');
        Route::post('clients/{client}/plans', [SupplyPlanController::class, 'store'])->middleware('can:orders.plans')->name('clients.plans.store');
        Route::patch('plans/{plan}', [SupplyPlanController::class, 'update'])->middleware('can:orders.plans')->name('plans.update');
    });

    Route::get('documents', [DocumentController::class, 'index'])->middleware('can:manage-documents')->name('documents.index');
    Route::middleware('can:manage-payments')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/arrears', [PaymentController::class, 'arrears'])->name('payments.arrears');
    });

    Route::middleware('can:manage-purchases')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('purchases/generate', [PurchaseController::class, 'generate'])->middleware('can:purchases.generate')->name('purchases.generate');
        Route::get('purchases/{purchaseOrder}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('purchases/{purchaseOrder}/send', [PurchaseController::class, 'send'])->middleware('can:purchases.send')->name('purchases.send');
        Route::post('purchases/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->middleware('can:purchases.receive')->name('purchases.receive');
        Route::post('purchases/{purchaseOrder}/cancel', [PurchaseController::class, 'cancel'])->middleware('can:purchases.cancel')->name('purchases.cancel');
        Route::post('shops/{shop}/payments', [ShopPaymentController::class, 'store'])->middleware('can:purchases.pay_shop')->name('shops.payments.store');
    });

    Route::middleware('can:manage-cash')->group(function () {
        Route::get('cash', [CashMovementController::class, 'index'])->name('cash.index');
        Route::post('cash', [CashMovementController::class, 'store'])->middleware('can:cash.record')->name('cash.store');
        Route::post('cash/{cashMovement}/cancel', [CashMovementController::class, 'cancel'])->middleware('can:cash.cancel')->name('cash.cancel');
        Route::post('cash/closings', [CashCloseController::class, 'store'])->middleware('can:cash.close')->name('cash.closings.store');
        Route::post('cash/link-expenses', [CashCloseController::class, 'link'])->middleware('can:cash.link_expenses')->name('cash.link-expenses');
    });

    Route::middleware('can:manage-expenses')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/create', [ExpenseController::class, 'create'])->middleware('can:expenses.create')->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware('throttle:uploads')->middleware('can:expenses.create')->name('expenses.store');
        Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->middleware('can:expenses.cancel')->name('expenses.cancel');
        Route::get('expenses/{expense}/proof', [ExpenseController::class, 'proof'])->middleware('can:expenses.view_proof')->name('expenses.proof');
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
        Route::post('kits/{kit}/versions', [KitVersionController::class, 'store'])->middleware('can:catalog.kits')->name('kits.versions.store');
        Route::patch('products/{product}/archive', [ProductController::class, 'archive'])->middleware('can:catalog.archive')->name('products.archive');
        Route::patch('products/{product}/restore', [ProductController::class, 'restore'])->middleware('can:catalog.archive')->name('products.restore');
        Route::post('products/{product}/prices', [ProductPriceController::class, 'store'])->middleware('can:catalog.prices')->name('products.prices.store');
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
