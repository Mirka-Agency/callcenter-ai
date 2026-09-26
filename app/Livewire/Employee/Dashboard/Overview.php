<?php

namespace App\Livewire\Employee\Dashboard;

use App\Services\EmployeeContext;
use App\Services\EmployeeDashboardAnalytics;
use App\Support\OrganizationHolidays;
use Illuminate\Support\Facades\Cache;
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
        $payload = Cache::remember(
            'employee-dashboard:'.$membership->id.':'.OrganizationHolidays::cacheToken($membership->organization_id),
            120,
            function () use ($membership) {
                $analytics = EmployeeDashboardAnalytics::forEmployee($membership);
                $improvementAreas = $analytics->topImprovementAreas();

                return [
                    'cockpit' => $analytics->cockpit(),
                    'achievements' => $analytics->achievements(),
                    'topStrengths' => $analytics->topStrengths(),
                    'improvementAreas' => $improvementAreas,
                    'followUps' => $analytics->followUps(),
                    'recommendations' => $analytics->recommendations(),
                    'recentCalls' => $analytics->recentCalls(),
                ];
            },
        );

        return view('livewire.employee.dashboard.overview', [
            'membership' => $membership,
            'cockpit' => $payload['cockpit'],
            'achievements' => $payload['achievements'],
            'topStrengths' => $payload['topStrengths'],
            'items' => $payload['improvementAreas']['items'],
            'derived' => $payload['improvementAreas']['derived'],
            'topWeaknesses' => $payload['improvementAreas']['items'],
            'weaknessesDerived' => $payload['improvementAreas']['derived'],
            'followUps' => $payload['followUps'],
            'recommendations' => $payload['recommendations'],
            'recentCalls' => $payload['recentCalls'],
        ]);
    }
}
