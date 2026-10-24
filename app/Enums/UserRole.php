<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Employer = 'employer';
    case Employee = 'employee';
    case Writer = 'writer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('roles.super_admin'),
            self::Admin => __('roles.admin'),
            self::Employer => __('roles.employer'),
            self::Employee => __('roles.employee'),
            self::Writer => __('roles.writer'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }

    public function homeRoute(): string
    {
        return match ($this) {
            UserRole::Employer => route('employer.dashboard'),
            UserRole::Employee => route('employee.dashboard'),
            UserRole::SuperAdmin, UserRole::Admin => url('/admin'),
            UserRole::Writer => url('/admin/blog-posts'),
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::Writer], true);
    }

    /** Super admins and admins; writers use the admin panel but only for the blog. */
    public function isAdminStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function canAccessSuperAdminFeatures(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canAccessEmployerDashboard(): bool
    {
        return $this === self::Employer;
    }

    public function canAccessEmployeeDashboard(): bool
    {
        return $this === self::Employee;
    }
}
