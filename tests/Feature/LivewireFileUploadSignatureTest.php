<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustCapRoverTlsTermination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

class LivewireFileUploadSignatureTest extends TestCase
{
    public function test_tls_termination_middleware_marks_production_requests_secure(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $request = Request::create('/app', 'GET');
        $this->assertFalse($request->isSecure());

        (new TrustCapRoverTlsTermination)->handle($request, fn (Request $request) => response('ok'));

        $this->assertTrue($request->isSecure());
    }

    public function test_production_middleware_allows_signed_upload_without_proxy_headers(): void
    {
        $this->withoutMiddleware();
        $this->app->detectEnvironment(fn () => 'production');

        config([
            'app.env' => 'production',
            'app.url' => 'https://call-center.example.test',
        ]);

        URL::forceRootUrl('https://call-center.example.test');
        URL::forceScheme('https');

        $signedUrl = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5));
        $parsed = parse_url($signedUrl);
        $path = $parsed['path'].'?'.$parsed['query'];

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'call-center.example.test',
            'SERVER_PORT' => '8000',
        ])->post($path, [], ['Accept' => 'application/json']);

        $this->assertNotSame(401, $response->getStatusCode(), 'Signed upload URL should not be rejected as unauthorized behind TLS termination.');
    }

    public function test_unsigned_upload_request_is_rejected(): void
    {
        $this->withoutMiddleware();

        $this->post(EndpointResolver::uploadPath(), [], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }
}
