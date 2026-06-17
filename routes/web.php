<?php

use App\Livewire\Auth\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(auth()->user()->portalRoute());
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', Login::class)->name('login');
});

Route::post('/logout', function () {
    if (app(\App\Services\ImpersonationService::class)->isImpersonating()) {
        $redirectUrl = app(\App\Application\Impersonation\Actions\StopImpersonationAction::class)->execute();

        return redirect($redirectUrl);
    }

    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::post('/impersonation/stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonation.stop');

Route::post('/webhooks/voip/{connection}', \App\Http\Controllers\VoipWebhookController::class)
    ->name('webhooks.voip');

