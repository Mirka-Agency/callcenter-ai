<?php

namespace Tests\Feature;

use App\Livewire\Employee\Uploads\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeUploadLivewireTest extends TestCase
{
    public function test_employee_upload_livewire_action_reaches_backend(): void
    {
        $user = User::query()->where('email', 'admi2n@mirka.test')->first();

        $this->assertNotNull($user, 'Test employee user must exist');

        Storage::fake('local');

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->set('audio', UploadedFile::fake()->create('test.wav', 100, 'audio/wav'))
            ->call('submitForAnalysis')
            ->assertHasNoErrors()
            ->assertRedirect();
    }
}
