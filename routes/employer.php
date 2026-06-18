<?php

use App\Http\Controllers\Employer\ReportExportController;
use App\Livewire\Employer\Crm\Index as CrmIndex;
use App\Livewire\Employer\Customers\Companies\Create as CustomerCompaniesCreate;
use App\Livewire\Employer\Customers\Companies\Edit as CustomerCompaniesEdit;
use App\Livewire\Employer\Customers\Companies\Index as CustomerCompaniesIndex;
use App\Livewire\Employer\Customers\Companies\Show as CustomerCompaniesShow;
use App\Livewire\Employer\Customers\Contacts\Index as CustomerContactsIndex;
use App\Livewire\Employer\Customers\Edit as CustomersEdit;
use App\Livewire\Employer\Customers\Index as CustomersIndex;
use App\Livewire\Employer\Customers\Show as CustomersShow;
use App\Livewire\Employer\Dashboard\Overview as EmployerDashboard;
use App\Livewire\Employer\Employees\Create as EmployeeCreate;
use App\Livewire\Employer\Employees\Edit as EmployeeEdit;
use App\Livewire\Employer\Employees\Index as EmployeesIndex;
use App\Livewire\Employer\ManualAnalyses\Index as ManualAnalysesIndex;
use App\Livewire\Employer\ManualAnalyses\Show as ManualAnalysesShow;
use App\Livewire\Employer\Profile\Edit as ProfileEdit;
use App\Livewire\Employer\Intelligence\Index as IntelligenceIndex;
use App\Livewire\Employer\Intelligence\Performance as IntelligencePerformance;
use App\Livewire\Employer\Intelligence\PerformanceShow as IntelligencePerformanceShow;
use App\Livewire\Employer\Intelligence\Show as IntelligenceShow;
use App\Livewire\Employer\Reports\Index as ReportsIndex;
use App\Livewire\Employer\Voip\Index as VoipIndex;
use App\Livewire\Employer\Wallet\Index as WalletIndex;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::redirect('login', '/login');
});

Route::middleware(['auth', 'employer'])->group(function () {
    Route::get('/', EmployerDashboard::class)->name('dashboard');

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', EmployeesIndex::class)->name('index');
        Route::get('/create', EmployeeCreate::class)->name('create');
        Route::get('/{employee}/edit', EmployeeEdit::class)->name('edit');
    });

    Route::prefix('intelligence')->name('intelligence.')->group(function () {
        Route::get('/', IntelligenceIndex::class)->name('index');
        Route::get('/performance/export/{format}', [ReportExportController::class, 'teamPerformance'])
            ->name('performance.export')
            ->whereIn('format', ['csv', 'xlsx', 'pdf']);
        Route::get('/performance', IntelligencePerformance::class)->name('performance');
        Route::get('/performance/{employee}/export/{format}', [ReportExportController::class, 'employeePerformance'])
            ->name('performance.show.export')
            ->whereIn('format', ['csv', 'xlsx', 'pdf']);
        Route::get('/performance/{employee}', IntelligencePerformanceShow::class)->name('performance.show');
        Route::get('/{analysis}', IntelligenceShow::class)->name('show');
    });

    Route::prefix('manual-analyses')->name('manual-analyses.')->group(function () {
        Route::get('/', ManualAnalysesIndex::class)->name('index');
        Route::get('/{upload}', ManualAnalysesShow::class)->name('show');
    });

    Route::prefix('processing-queue')->name('processing-queue.')->group(function () {
        Route::get('/', \App\Livewire\Employer\ProcessingQueue\Index::class)->name('index');
        Route::get('/{job}', \App\Livewire\Employer\ProcessingQueue\Show::class)->name('show');
    });

    Route::get('/crm', CrmIndex::class)->name('crm.index');
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', CustomersIndex::class)->name('index');
        Route::get('/companies', CustomerCompaniesIndex::class)->name('companies.index');
        Route::get('/contacts', CustomerContactsIndex::class)->name('contacts.index');
        Route::get('/companies/create', CustomerCompaniesCreate::class)->name('companies.create');
        Route::get('/companies/{customerCompany}/edit', CustomerCompaniesEdit::class)->name('companies.edit');
        Route::get('/companies/{customerCompany}', CustomerCompaniesShow::class)->name('companies.show');
        Route::get('/{customer}/edit', CustomersEdit::class)->name('edit');
        Route::get('/{customer}', CustomersShow::class)->name('show');
    });
    Route::get('/voip', VoipIndex::class)->name('voip.index');
    Route::get('/reports/export/{format}', [ReportExportController::class, 'reports'])
        ->name('reports.export')
        ->whereIn('format', ['csv', 'xlsx', 'pdf']);
    Route::get('/reports', ReportsIndex::class)->name('reports.index');
    Route::redirect('/analytics', '/reports')->name('analytics.index');
    Route::get('/wallet', WalletIndex::class)->name('wallet.index');
    Route::get('/profile', ProfileEdit::class)->name('profile.edit');
});
