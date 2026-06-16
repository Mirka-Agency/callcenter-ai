<?php

use App\Livewire\Employee\Activity\Index as ActivityIndex;
use App\Livewire\Employee\Calls\Index as CallsIndex;
use App\Livewire\Employee\Calls\Show as CallsShow;
use App\Livewire\Employee\Coaching\Index as CoachingIndex;
use App\Livewire\Employee\Customers\Edit as CustomersEdit;
use App\Livewire\Employee\Customers\Index as CustomersIndex;
use App\Livewire\Employee\Customers\Show as CustomersShow;
use App\Livewire\Employee\Dashboard\Overview as EmployeeDashboard;
use App\Livewire\Employee\Profile\Edit as ProfileEdit;
use App\Livewire\Employee\Performance\Index as PerformanceIndex;
use App\Livewire\Employee\Uploads\Index as UploadsIndex;
use App\Livewire\Employee\Uploads\Show as UploadsShow;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::redirect('login', '/login');
});

Route::middleware(['auth', 'employee'])->group(function () {
    Route::get('/', EmployeeDashboard::class)->name('dashboard');
    Route::get('/performance', PerformanceIndex::class)->name('performance');
    Route::get('/uploads', UploadsIndex::class)->name('uploads');
    Route::get('/uploads/{upload}', UploadsShow::class)->name('uploads.show');
    Route::get('/processing-queue', \App\Livewire\Employee\ProcessingQueue\Index::class)->name('processing-queue.index');
    Route::get('/processing-queue/{job}', \App\Livewire\Employee\ProcessingQueue\Show::class)->name('processing-queue.show');
    Route::get('/calls', CallsIndex::class)->name('calls');
    Route::get('/calls/{analysis}', CallsShow::class)->name('calls.show');
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', CustomersIndex::class)->name('index');
        Route::get('/{customer}/edit', CustomersEdit::class)->name('edit');
        Route::get('/{customer}', CustomersShow::class)->name('show');
    });
    Route::get('/coaching', CoachingIndex::class)->name('coaching');
    Route::get('/activity', ActivityIndex::class)->name('activity');
    Route::get('/profile', ProfileEdit::class)->name('profile.edit');
});
