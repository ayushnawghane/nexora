<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Companies\CompanyAddressController;
use App\Http\Controllers\Companies\CompanyContactController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\CompanyGstinController;
use App\Http\Controllers\Masters\MasterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThemePreferenceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'two-factor', 'password.fresh'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/preferences/theme', ThemePreferenceController::class)->name('preferences.theme');

    Route::prefix('admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');

        Route::resource('roles', RoleController::class)->except(['show']);
    });

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

require __DIR__.'/auth.php';
