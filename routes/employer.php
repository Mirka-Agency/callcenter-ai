<?php

use App\Http\Controllers\CustomerListExportController;
use App\Http\Controllers\Employer\ReportExportController;
use App\Livewire\Crm\Deals\Index as CrmDealsIndex;
use App\Livewire\Crm\Deals\OpportunityList as CrmDealsOpportunityList;
use App\Livewire\Crm\Deals\Show as CrmDealsShow;
use App\Livewire\Crm\Overview as CrmOverview;
use App\Livewire\Crm\Products\Index as CrmProductsIndex;
use App\Livewire\Crm\Settings as CrmSettings;
use App\Livewire\Crm\Reminders\Index as CrmRemindersIndex;
use App\Livewire\Crm\Tasks\Index as CrmTasksIndex;
use App\Livewire\Crm\Tasks\Show as CrmTasksShow;
use App\Livewire\Employer\Crm\Connections\Create;
use App\Livewire\Employer\Crm\Connections\Edit;
use App\Livewire\Employer\Crm\Index as CrmIndex;
use App\Livewire\Employer\Customers\Companies\Create as CustomerCompaniesCreate;
use App\Livewire\Employer\Customers\Companies\Edit as CustomerCompaniesEdit;
use App\Livewire\Employer\Customers\Companies\Index as CustomerCompaniesIndex;
use App\Livewire\Employer\Customers\Companies\Show as CustomerCompaniesShow;
use App\Livewire\Employer\Customers\Contacts\Create as CustomerContactsCreate;
use App\Livewire\Employer\Customers\Contacts\Index as CustomerContactsIndex;
use App\Livewire\Employer\Customers\Edit as CustomersEdit;
use App\Livewire\Employer\Customers\Index as CustomersIndex;
use App\Livewire\Employer\Customers\Reviews\Index as CustomerReviewsIndex;
use App\Livewire\Employer\Customers\Show as CustomersShow;
use App\Livewire\Employer\Dashboard\Overview as EmployerDashboard;
use App\Livewire\Employer\Employees\Create as EmployeeCreate;
use App\Livewire\Employer\Employees\Edit as EmployeeEdit;
use App\Livewire\Employer\Intelligence\Index as IntelligenceIndex;
use App\Livewire\Employer\Intelligence\Performance as IntelligencePerformance;
use App\Livewire\Employer\Intelligence\PerformanceShow as IntelligencePerformanceShow;
use App\Livewire\Employer\Intelligence\Show as IntelligenceShow;
use App\Livewire\Employer\ManualAnalyses\Index as ManualAnalysesIndex;
use App\Livewire\Employer\ManualAnalyses\Show as ManualAnalysesShow;
use App\Livewire\Employer\Organization\Profile as OrganizationProfile;
use App\Livewire\Employer\ProcessingQueue\Index;
use App\Livewire\Employer\ProcessingQueue\Show;
use App\Livewire\Employer\Profile\Edit as ProfileEdit;
use App\Livewire\Employer\Voip\Extensions;
use App\Livewire\Employer\Voip\Index as VoipIndex;
use App\Livewire\Employer\Wallet\Index as WalletIndex;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::redirect('login', '/login');
});

Route::middleware(['auth', 'employer'])->group(function () {
    Route::get('/', EmployerDashboard::class)->name('dashboard');

    Route::redirect('employees', '/app/intelligence/performance');
    Route::prefix('employees')->name('employees.')->group(function () {
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
        Route::get('/', Index::class)->name('index');
        Route::get('/{job}', Show::class)->name('show');
    });

    Route::middleware('organization-crm:external')->group(function () {
        Route::get('/crm', CrmIndex::class)->name('crm.index');
        Route::prefix('crm/connections')->name('crm.connections.')->group(function () {
            Route::get('/', App\Livewire\Employer\Crm\Connections\Index::class)->name('index');
            Route::get('/create', Create::class)->name('create');
            Route::get('/{connection}/edit', Edit::class)->name('edit');
        });
    });
    Route::middleware('organization-crm:internal')->group(function () {
        Route::get('/crm/overview', CrmOverview::class)->name('crm.overview');
        Route::get('/crm/settings', CrmSettings::class)->name('crm.settings');
        Route::prefix('crm/deals')->name('crm.deals.')->group(function () {
            Route::get('/', CrmDealsIndex::class)->name('index');
            Route::get('/list', CrmDealsOpportunityList::class)->name('list');
            Route::get('/{deal}', CrmDealsShow::class)->name('show')->whereNumber('deal');
        });
        Route::prefix('crm/tasks')->name('crm.tasks.')->group(function () {
            Route::get('/', CrmTasksIndex::class)->name('index');
            Route::get('/{task}', CrmTasksShow::class)->name('show')->whereNumber('task');
        });
        Route::get('/crm/reminders', CrmRemindersIndex::class)->name('crm.reminders.index');
        Route::get('/crm/products', CrmProductsIndex::class)->name('crm.products.index');
    });
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', CustomersIndex::class)->name('index');
        Route::get('/companies', CustomerCompaniesIndex::class)->name('companies.index');
        Route::get('/companies/export/{format}', [CustomerListExportController::class, 'companies'])
            ->name('companies.export')
            ->whereIn('format', ['xlsx', 'pdf']);
        Route::get('/contacts', CustomerContactsIndex::class)->name('contacts.index');
        Route::get('/contacts/export/{format}', [CustomerListExportController::class, 'contacts'])
            ->name('contacts.export')
            ->whereIn('format', ['xlsx', 'pdf']);
        Route::get('/contacts/create', CustomerContactsCreate::class)->name('contacts.create');
        Route::get('/reviews', CustomerReviewsIndex::class)->name('reviews.index');
        Route::get('/companies/create', CustomerCompaniesCreate::class)->name('companies.create');
        Route::get('/companies/{customerCompany}/edit', CustomerCompaniesEdit::class)->name('companies.edit');
        Route::get('/companies/{customerCompany}', CustomerCompaniesShow::class)->name('companies.show');
        Route::get('/{customer}/edit', CustomersEdit::class)->name('edit');
        Route::get('/{customer}', CustomersShow::class)->name('show');
    });
    Route::permanentRedirect('/unmatched-extensions', '/app/extensions');
    Route::get('/extensions', Extensions::class)->name('extensions.index');
    Route::get('/voip', VoipIndex::class)->name('voip.index');
    Route::prefix('voip/connections')->name('voip.connections.')->group(function () {
        Route::get('/', App\Livewire\Employer\Voip\Connections\Index::class)->name('index');
        Route::get('/create', App\Livewire\Employer\Voip\Connections\Create::class)->name('create');
        Route::get('/{connection}/edit', App\Livewire\Employer\Voip\Connections\Edit::class)->name('edit');
    });
    Route::get('/organization', OrganizationProfile::class)->name('organization.profile');
    Route::permanentRedirect('/holidays', '/app/organization')->name('holidays.index');
    Route::get('/wallet', WalletIndex::class)->name('wallet.index');
    Route::get('/profile', ProfileEdit::class)->name('profile.edit');
});
