<?php

use App\Http\Controllers\CustomerListExportController;
use App\Livewire\Crm\Deals\Index as CrmDealsIndex;
use App\Livewire\Crm\Deals\OpportunityList as CrmDealsOpportunityList;
use App\Livewire\Crm\Deals\Show as CrmDealsShow;
use App\Livewire\Crm\Overview as CrmOverview;
use App\Livewire\Crm\Invoices\Index as CrmInvoicesIndex;
use App\Livewire\Crm\Invoices\Show as CrmInvoicesShow;
use App\Livewire\Crm\Pipelines\Form as CrmPipelinesForm;
use App\Livewire\Crm\Pipelines\Index as CrmPipelinesIndex;
use App\Livewire\Crm\Products\Index as CrmProductsIndex;
use App\Livewire\Crm\Reminders\Index as CrmRemindersIndex;
use App\Livewire\Crm\Tasks\Index as CrmTasksIndex;
use App\Livewire\Crm\Tasks\Show as CrmTasksShow;
use App\Livewire\Employee\Activity\Index as ActivityIndex;
use App\Livewire\Employee\Calls\Index as CallsIndex;
use App\Livewire\Employee\Calls\Show as CallsShow;
use App\Livewire\Employee\Coaching\Index as CoachingIndex;
use App\Livewire\Employee\Customers\Companies\Create as CustomerCompaniesCreate;
use App\Livewire\Employee\Customers\Companies\Edit as CustomerCompaniesEdit;
use App\Livewire\Employee\Customers\Companies\Index as CustomerCompaniesIndex;
use App\Livewire\Employee\Customers\Companies\Show as CustomerCompaniesShow;
use App\Livewire\Employee\Customers\Contacts\Create as CustomerContactsCreate;
use App\Livewire\Employee\Customers\Contacts\Index as CustomerContactsIndex;
use App\Livewire\Employee\Customers\Edit as CustomersEdit;
use App\Livewire\Employee\Customers\Index as CustomersIndex;
use App\Livewire\Employee\Customers\Reviews\Index as CustomerReviewsIndex;
use App\Livewire\Employee\Customers\Show as CustomersShow;
use App\Livewire\Employee\Dashboard\Overview as EmployeeDashboard;
use App\Livewire\Employee\Performance\Index as PerformanceIndex;
use App\Livewire\Employee\ProcessingQueue\Index;
use App\Livewire\Employee\ProcessingQueue\Show;
use App\Livewire\Employee\Profile\Edit as ProfileEdit;
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
    Route::get('/processing-queue', Index::class)->name('processing-queue.index');
    Route::get('/processing-queue/{job}', Show::class)->name('processing-queue.show');
    Route::get('/calls', CallsIndex::class)->name('calls');
    Route::get('/calls/{analysis}', CallsShow::class)->name('calls.show');
    Route::middleware('organization-crm:internal')->group(function () {
        Route::get('/crm/overview', CrmOverview::class)->name('crm.overview');
        Route::prefix('crm/deals')->name('crm.deals.')->group(function () {
            Route::get('/', CrmDealsIndex::class)->name('index');
            Route::get('/list', CrmDealsOpportunityList::class)->name('list');
            Route::get('/{deal}', CrmDealsShow::class)->name('show')->whereNumber('deal');
        });
        Route::prefix('crm/pipelines')->name('crm.pipelines.')->group(function () {
            Route::get('/', CrmPipelinesIndex::class)->name('index');
            Route::get('/create', CrmPipelinesForm::class)->name('create');
            Route::get('/{pipeline}/edit', CrmPipelinesForm::class)->name('edit')->whereNumber('pipeline');
        });
        Route::prefix('crm/tasks')->name('crm.tasks.')->group(function () {
            Route::get('/', CrmTasksIndex::class)->name('index');
            Route::get('/{task}', CrmTasksShow::class)->name('show')->whereNumber('task');
        });
        Route::get('/crm/reminders', CrmRemindersIndex::class)->name('crm.reminders.index');
        Route::get('/crm/products', CrmProductsIndex::class)->name('crm.products.index');
        Route::prefix('crm/invoices')->name('crm.invoices.')->group(function () {
            Route::get('/', CrmInvoicesIndex::class)->name('index');
            Route::get('/{invoice}', CrmInvoicesShow::class)->name('show')->whereNumber('invoice');
        });
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
    Route::get('/coaching', CoachingIndex::class)->name('coaching');
    Route::get('/activity', ActivityIndex::class)->name('activity');
    Route::get('/profile', ProfileEdit::class)->name('profile.edit');
});
