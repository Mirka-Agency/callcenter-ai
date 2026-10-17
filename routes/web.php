<?php

use App\Application\Impersonation\Actions\StopImpersonationAction;
use App\Http\Controllers\ConsultationRequestController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\ImpersonationController;
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

Route::post('/consultation-requests', [ConsultationRequestController::class, 'store'])
    ->middleware(['throttle:8,1', 'landing-host'])
    ->name('consultation-requests.store');

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

Route::match(['get', 'post'], '/webhooks/voip/{token}', VoipWebhookController::class)
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('webhooks.voip');
