<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Approvals\EmailApprovalController;
use App\Http\Controllers\Billing\BillingQueueController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Companies\CompanyAddressController;
use App\Http\Controllers\Companies\CompanyContactController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\CompanyGstinController;
use App\Http\Controllers\Companies\CompanyLookupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Deals\DealBillingController;
use App\Http\Controllers\Deals\DealConditionController;
use App\Http\Controllers\Deals\DealController;
use App\Http\Controllers\Deals\DealDocumentController;
use App\Http\Controllers\Deals\DealExecutionController;
use App\Http\Controllers\Deals\DealInvoiceController;
use App\Http\Controllers\Deals\DealIsinController;
use App\Http\Controllers\Deals\DealSecurityController;
use App\Http\Controllers\Deals\DealStatusController;
use App\Http\Controllers\Deals\IsinController;
use App\Http\Controllers\Deals\JobSheetController;
use App\Http\Controllers\GodMode\GodModeController;
use App\Http\Controllers\GodMode\GodModeLetterController;
use App\Http\Controllers\Masters\MasterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\TaxSettingsController;
use App\Http\Controllers\ThemePreferenceController;
use App\Http\Controllers\Transactions\EngagementLetterController;
use App\Http\Controllers\Transactions\TransactionController;
use App\Http\Controllers\Transactions\TransactionWizardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'two-factor', 'password.fresh'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/preferences/theme', ThemePreferenceController::class)->name('preferences.theme');

    Route::prefix('admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');

        Route::resource('roles', RoleController::class)->except(['show']);

        Route::prefix('tax-settings')->name('settings.tax')->controller(TaxSettingsController::class)->group(function () {
            Route::get('/', 'index');
            Route::put('gstin', 'updateGstin')->name('.gstin');
            Route::post('rates', 'storeRate')->name('.rates.store');
            Route::delete('rates/{rate}', 'destroyRate')->name('.rates.destroy');
        });
    });

    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{approvalRequest}/vote', [ApprovalController::class, 'vote'])->name('approvals.vote');
    Route::post('transactions/{transaction}/submit', [ApprovalController::class, 'submit'])->name('transactions.submit');
    Route::post('transactions/{transaction}/revise', [ApprovalController::class, 'revise'])->name('transactions.revise');
    Route::post('transactions/{transaction}/letter', [EngagementLetterController::class, 'issue'])->name('transactions.letter.issue');
    Route::get('transactions/{transaction}/letters/{version}', [EngagementLetterController::class, 'download'])
        ->whereNumber('version')->name('transactions.letter.download');

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('drafts', [TransactionController::class, 'drafts'])->name('drafts');
        Route::get('pending', [TransactionController::class, 'pending'])->name('pending');
        Route::get('approved', [TransactionController::class, 'approved'])->name('approved');
        Route::get('export/{list}', [TransactionController::class, 'export'])
            ->whereIn('list', ['drafts', 'pending', 'approved'])->name('export');

        Route::controller(TransactionWizardController::class)->group(function () {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{transaction}', 'show')->name('show');
            Route::get('{transaction}/edit', 'edit')->name('edit');
            Route::put('{transaction}/basics', 'updateBasics')->name('basics');
            Route::put('{transaction}/contacts', 'updateContacts')->name('contacts');
            Route::put('{transaction}/issue', 'updateIssue')->name('issue');
            Route::put('{transaction}/fees', 'updateFees')->name('fees');
            Route::post('{transaction}/schedule/verify', 'verifySchedule')->name('schedule.verify');
        });
    });

    // Every ISIN across deals (Stack's ISIN MIS) and its Excel export.
    Route::get('isins', [IsinController::class, 'index'])->name('isins.index');
    Route::get('isins/export', [IsinController::class, 'export'])->name('isins.export');

    // Billing: what's ready to bill, every invoice across deals, and what can be done to each.
    Route::get('billing', [BillingQueueController::class, 'index'])->name('billing.queue');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::prefix('invoices/{invoice}')->name('invoices.')->controller(InvoiceController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('pdf', 'pdf')->name('pdf');
        Route::put('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
        Route::post('issue', 'issue')->name('issue');
        Route::post('send-back', 'sendBack')->name('send-back');
        Route::post('convert', 'convert')->name('convert');
        Route::post('cancel', 'cancel')->name('cancel');
        Route::post('resend', 'resend')->name('resend');
        Route::post('credit-notes', 'creditNote')->name('credit-notes.store');
        Route::post('receipts', 'receipt')->name('receipts.store');
    });
    Route::post('invoice-receipts/{receipt}/reverse', [InvoiceController::class, 'reverseReceipt'])->name('invoice-receipts.reverse');

    // Once the engagement letter is issued, a transaction is worked on as a deal.
    Route::prefix('deals')->name('deals.')->group(function () {
        Route::get('/', [DealController::class, 'index'])->name('index');
        Route::get('{transaction}', [DealController::class, 'show'])->name('show');
        Route::put('{transaction}/billing', [DealBillingController::class, 'update'])->name('billing.update');
        Route::post('{transaction}/status', [DealStatusController::class, 'store'])->name('status.store');
        Route::post('{transaction}/job-sheet/{activity}', [JobSheetController::class, 'submit'])->name('job-sheet.submit');
        Route::post('{transaction}/job-sheet/entries/{entry}/check', [JobSheetController::class, 'check'])->name('job-sheet.check');

        Route::controller(DealDocumentController::class)->group(function () {
            Route::post('{transaction}/documents', 'store')->name('documents.store');
            Route::delete('{transaction}/documents/{document}', 'destroy')->name('documents.destroy');
            Route::post('{transaction}/documents/{document}/file', 'upload')->name('documents.upload');
            Route::get('{transaction}/files/{file}', 'download')->name('files.download');
            Route::delete('{transaction}/files/{file}', 'removeFile')->name('files.destroy');
        });
        Route::controller(DealConditionController::class)->prefix('{transaction}/conditions')->name('conditions.')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::post('{condition}/files', 'upload')->name('upload');
            Route::post('{condition}/check', 'check')->name('check');
            Route::put('{condition}/due-date', 'dueDate')->name('due-date');
            Route::post('{condition}/waive', 'waive')->name('waive');
            Route::delete('{condition}', 'destroy')->name('destroy');
        });
        Route::controller(DealExecutionController::class)->prefix('{transaction}/executions')->name('executions.')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::post('schedule', 'schedule')->name('schedule');
            Route::post('pick-up', 'pickUp')->name('pick-up');
            Route::post('{execution}/record', 'record')->name('record');
            Route::post('{execution}/check', 'check')->name('check');
            Route::delete('{execution}', 'destroy')->name('destroy');
        });
        Route::controller(DealSecurityController::class)->group(function () {
            Route::post('{transaction}/securities', 'store')->name('securities.store');
            Route::put('{transaction}/securities/{security}', 'update')->name('securities.update');
            Route::delete('{transaction}/securities/{security}', 'destroy')->name('securities.destroy');
            Route::post('{transaction}/registrations', 'register')->name('registrations.store');
            Route::post('{transaction}/registrations/{registration}/events', 'event')->name('registrations.event');
            Route::post('{transaction}/diligence', 'addDiligence')->name('diligence.store');
            Route::post('{transaction}/diligence/{item}/files', 'uploadDiligence')->name('diligence.upload');
            Route::post('{transaction}/diligence/{item}/check', 'checkDiligence')->name('diligence.check');
            Route::delete('{transaction}/diligence/{item}', 'removeDiligence')->name('diligence.destroy');
        });
        Route::controller(DealIsinController::class)->group(function () {
            Route::post('{transaction}/isins', 'store')->name('isins.store');
            Route::put('{transaction}/isins/{isin}', 'update')->name('isins.update');
            Route::post('{transaction}/isins/{isin}/allotments', 'allot')->name('isins.allot');
            Route::post('{transaction}/isins/{isin}/schedule', 'schedule')->name('isins.schedule');
            Route::post('{transaction}/isin-reminders', 'remind')->name('isins.remind');
            Route::put('{transaction}/isin-payments/{payment}/due-date', 'move')->name('isin-payments.move');
            Route::post('{transaction}/isin-payments/{payment}/record', 'record')->name('isin-payments.record');
            Route::delete('{transaction}/isin-payments/{payment}', 'destroy')->name('isin-payments.destroy');
        });
        Route::controller(DealInvoiceController::class)->group(function () {
            Route::post('{transaction}/invoices', 'store')->name('invoices.store');
            Route::post('{transaction}/expenses', 'storeExpense')->name('expenses.store');
            Route::post('{transaction}/expenses/{expense}', 'updateExpense')->name('expenses.update');
            Route::delete('{transaction}/expenses/{expense}', 'destroyExpense')->name('expenses.destroy');
        });
    });
    Route::prefix('deal-status-requests/{statusRequest}')->name('deals.status.')->controller(DealStatusController::class)->group(function () {
        Route::post('vote', 'vote')->name('vote');
        Route::post('withdraw', 'withdraw')->name('withdraw');
        Route::get('noc', 'noc')->name('noc');
    });

    // God Mode: super-admins only, with a 2FA code entered in the last 15 minutes.
    Route::prefix('god-mode')->name('god-mode.')->middleware(['can:use-god-mode', 'two-factor.recent:15'])->group(function () {
        Route::get('/', [GodModeController::class, 'index'])->name('index');
        Route::get('companies/{company}', [GodModeController::class, 'company'])->name('companies');
        Route::get('transactions/{transaction}', [GodModeController::class, 'transaction'])->name('transactions');
        Route::post('correct/{editor}/{id}', [GodModeController::class, 'correct'])->name('correct');
        Route::post('changes/{change}/rollback', [GodModeController::class, 'rollback'])->name('rollback');
        Route::post('transactions/{transaction}/schedule/verify', [GodModeController::class, 'verifySchedule'])->name('schedule.verify');
        Route::prefix('transactions/{transaction}/letter')->name('letter.')->controller(GodModeLetterController::class)->group(function () {
            Route::post('regenerate', 'regenerate')->name('regenerate');
            Route::post('wording', 'wording')->name('wording');
            Route::post('number', 'number')->name('number');
            Route::post('pdf', 'pdf')->name('pdf');
        });
    });

    Route::get('companies/lookup/{type}', CompanyLookupController::class)
        ->whereIn('type', ['cin', 'gstin', 'pan'])->middleware('throttle:30,1')->name('companies.lookup');
    Route::resource('companies', CompanyController::class)->except(['destroy']);
    Route::post('companies/{company}/toggle-active', [CompanyController::class, 'toggleActive'])->name('companies.toggle-active');
    Route::prefix('companies/{company}')->name('companies.')->scopeBindings()->group(function () {
        Route::post('gstins', [CompanyGstinController::class, 'store'])->name('gstins.store');
        Route::put('gstins/{gstin}', [CompanyGstinController::class, 'update'])->name('gstins.update');
        Route::post('gstins/{gstin}/toggle', [CompanyGstinController::class, 'toggle'])->name('gstins.toggle');
        Route::post('addresses', [CompanyAddressController::class, 'store'])->name('addresses.store');
        Route::put('addresses/{address}', [CompanyAddressController::class, 'update'])->name('addresses.update');
        Route::post('addresses/{address}/toggle', [CompanyAddressController::class, 'toggle'])->name('addresses.toggle');
        Route::post('contacts', [CompanyContactController::class, 'store'])->name('contacts.store');
        Route::put('contacts/{contact}', [CompanyContactController::class, 'update'])->name('contacts.update');
        Route::post('contacts/{contact}/toggle', [CompanyContactController::class, 'toggle'])->name('contacts.toggle');
    });

    Route::prefix('masters')->name('masters.')->controller(MasterController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{master}', 'show')->name('show');
        Route::post('{master}', 'store')->name('store');
        Route::put('{master}/{record}', 'update')->whereNumber('record')->name('update');
        Route::post('{master}/{record}/toggle', 'toggle')->whereNumber('record')->name('toggle');
        Route::delete('{master}/{record}', 'destroy')->whereNumber('record')->name('destroy');
    });
});

// Approver links from email: no sign-in, but the URL must carry a valid, unexpired signature.
Route::middleware(['signed', 'throttle:20,1'])->controller(EmailApprovalController::class)->group(function () {
    Route::get('approve/{approvalRequest}/{user}', 'show')->name('approvals.email');
    Route::post('approve/{approvalRequest}/{user}', 'vote')->name('approvals.email.vote');
});

require __DIR__.'/auth.php';
