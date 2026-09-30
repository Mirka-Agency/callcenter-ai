<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class ViteHotFileGuardTest extends TestCase
{
    public function test_non_local_environment_ignores_vite_hot_marker(): void
    {
        $hotPath = public_path('hot');
        file_put_contents($hotPath, 'http://127.0.0.1:5173');

        try {
            $this->assertSame('testing', app()->environment());
            $this->assertFalse(
                Vite::isRunningHot(),
                'Outside local, Laravel must not follow public/hot even if the file exists.',
            );
            $this->assertStringEndsWith(
                'vite-hot-disabled',
                Vite::hotFile(),
            );
        } finally {
            @unlink($hotPath);
        }
    }
}
