<?php

namespace App\Livewire\Employee\Dashboard;

use App\Services\EmployeeContext;
use App\Services\EmployeeDashboardAnalytics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('داشبورد عملکرد')]
class Overview extends Component
{
    public function render()
    {
        $membership = EmployeeContext::membership();
        $analytics = EmployeeDashboardAnalytics::forEmployee($membership);

        return view('livewire.employee.dashboard.overview', [
            'membership' => $membership,
            'cockpit' => $analytics->cockpit(),
            'achievements' => $analytics->achievements(),
            'topStrengths' => $analytics->topStrengths(),
            ...($improvementAreas = $analytics->topImprovementAreas()),
            'topWeaknesses' => $improvementAreas['items'],
            'weaknessesDerived' => $improvementAreas['derived'],
            'followUps' => $analytics->followUps(),
            'recommendations' => $analytics->recommendations(),
            'recentCalls' => $analytics->recentCalls(),
        ]);
    }
}
