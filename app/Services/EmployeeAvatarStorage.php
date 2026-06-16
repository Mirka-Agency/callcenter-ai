<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EmployeeAvatarStorage
{
    public function store(UploadedFile $file, ?User $user = null): string
    {
        $this->deleteStoredUpload($user?->avatar_path);

        return $file->store('avatars', 'public');
    }

    public function deleteStoredUpload(?string $path): void
    {
        if (! filled($path) || str_starts_with($path, '/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
