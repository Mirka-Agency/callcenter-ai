<?php

namespace App\Livewire\Employer\Employees\Concerns;

use App\Models\User;
use App\Services\EmployeeAvatarStorage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ManagesEmployeeAvatar
{
    /** @var TemporaryUploadedFile|null */
    public $avatar;

    /** @return array<string, list<string>> */
    protected function avatarValidationRules(): array
    {
        return [
            'avatar' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    protected function persistAvatar(User $user): void
    {
        if (! $this->avatar) {
            return;
        }

        $path = app(EmployeeAvatarStorage::class)->store($this->avatar, $user);
        $user->update(['avatar_path' => $path]);
        $this->reset('avatar');
    }
}
