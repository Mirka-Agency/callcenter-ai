<?php

namespace App\Livewire\Employee\Activity;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Services\EmployeeActivityAnalytics;
use App\Services\EmployeeContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('فعالیت اخیر')]
class Index extends Component
{
    #[Url(as: 'preset')]
    public string $period = 'last_30';

    #[Url(as: 'type')]
    public string $type = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public function setPeriod(string $preset): void
    {
        $this->period = $preset;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function render()
    {
        $membership = EmployeeContext::membership()->load('user');
        $preset = ReportDatePreset::tryFrom($this->period) ?? ReportDatePreset::Last30;
        $filter = ReportFilter::make(
            organizationId: EmployeeContext::organizationId(),
            preset: $preset,
            employeeIds: [$membership->id],
        );

        $analytics = app(EmployeeActivityAnalytics::class);
        $type = in_array($this->type, ['all', 'analysis', 'upload', 'feedback'], true) ? $this->type : 'all';
        $search = trim($this->search) !== '' ? trim($this->search) : null;

        return view('livewire.employee.activity.index', [
            'membership' => $membership,
            'summary' => $analytics->summary($filter, $membership),
            'volumeTrend' => $analytics->volumeTrend($filter, $membership),
            'timeline' => $analytics->timeline($filter, $membership, $type, $search),
            'recentFeedback' => $analytics->recentFeedback($filter, $membership),
            'followUps' => $analytics->followUps($filter, $membership),
            'periodPresets' => [
                ReportDatePreset::Last7,
                ReportDatePreset::Last30,
                ReportDatePreset::ThisMonth,
                ReportDatePreset::CurrentQuarter,
            ],
            'activePreset' => $preset,
            'activeType' => $type,
        ]);
    }
}
