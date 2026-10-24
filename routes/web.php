<?php

use App\Application\Impersonation\Actions\StopImpersonationAction;
use App\Http\Controllers\Admin\PrintBillingInvoiceController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ConsultationRequestController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\VoipWebhookController;
use App\Livewire\Auth\Login;
use App\Services\ImpersonationService;
use App\Support\LandingHost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! LandingHost::matches(request())) {
        return redirect()->route('login');
    }

    return view('landing');
})->name('home');

Route::get('/about', function () {
    if (! LandingHost::matches(request())) {
        return redirect()->route('login');
    }

    return view('about');
})->name('about');

Route::get('/contact', function () {
    if (! LandingHost::matches(request())) {
        return redirect()->route('login');
    }

    return view('contact');
})->name('contact');

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/{child}.xml', [SitemapController::class, 'child'])
    ->where('child', '[a-z]+')
    ->name('sitemap.child');

Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/category/{slug}', [BlogController::class, 'category'])
    ->where('slug', '[^/]+')
    ->name('blog.category');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[^/]+')
    ->name('blog.show');

Route::post('/consultation-requests', [ConsultationRequestController::class, 'store'])
    ->middleware(['throttle:8,1', 'landing-host'])
    ->name('consultation-requests.store');

Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware(['throttle:8,1', 'landing-host'])
    ->name('contact-messages.store');

Route::post('/demo-requests', [DemoRequestController::class, 'store'])
    ->middleware(['throttle:8,1', 'landing-host'])
    ->name('demo-requests.store');

Route::middleware('guest')->group(function () {
    Route::get('login', Login::class)->name('login');
});

Route::post('/logout', function () {
    if (app(ImpersonationService::class)->isImpersonating()) {
        $redirectUrl = app(StopImpersonationAction::class)->execute();

        return redirect($redirectUrl);
    }

    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');

Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonation.stop');

Route::get('/admin/billing-invoices/{invoice}/print', PrintBillingInvoiceController::class)
    ->middleware('auth')
    ->name('admin.billing-invoices.print');

Route::match(['get', 'post'], '/webhooks/voip/{token}', VoipWebhookController::class)
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('webhooks.voip');
