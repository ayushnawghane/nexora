<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Approvals\EmailApprovalController;
use App\Http\Controllers\Companies\CompanyAddressController;
use App\Http\Controllers\Companies\CompanyContactController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\CompanyGstinController;
use App\Http\Controllers\Companies\CompanyLookupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Deals\DealBillingController;
use App\Http\Controllers\Deals\DealController;
use App\Http\Controllers\Deals\DealStatusController;
use App\Http\Controllers\Deals\JobSheetController;
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

    // Once the engagement letter is issued, a transaction is worked on as a deal.
    Route::prefix('deals')->name('deals.')->group(function () {
        Route::get('/', [DealController::class, 'index'])->name('index');
        Route::get('{transaction}', [DealController::class, 'show'])->name('show');
        Route::put('{transaction}/billing', [DealBillingController::class, 'update'])->name('billing.update');
        Route::post('{transaction}/status', [DealStatusController::class, 'store'])->name('status.store');
        Route::post('{transaction}/job-sheet/{activity}', [JobSheetController::class, 'submit'])->name('job-sheet.submit');
        Route::post('{transaction}/job-sheet/entries/{entry}/check', [JobSheetController::class, 'check'])->name('job-sheet.check');
    });
    Route::prefix('deal-status-requests/{statusRequest}')->name('deals.status.')->controller(DealStatusController::class)->group(function () {
        Route::post('vote', 'vote')->name('vote');
        Route::post('withdraw', 'withdraw')->name('withdraw');
        Route::get('noc', 'noc')->name('noc');
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
