<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasAvatar;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasAvatar;
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->role->canAccessAdminPanel();
    }

    public function portalRoute(): string
    {
        return $this->role->homeRoute();
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function primaryOrganization(): ?Organization
    {
        return $this->organizations()->first();
    }

    public function employeeOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot([
                'first_name',
                'last_name',
                'mobile',
                'position',
                'department',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function relatedOrganizations(): Collection
    {
        return match ($this->role) {
            UserRole::Employer => $this->organizations,
            UserRole::Employee => $this->employeeOrganizations,
            default => collect(),
        };
    }
}
