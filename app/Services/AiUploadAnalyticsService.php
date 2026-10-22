<?php

namespace App\Services;

use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiUploadAnalyticsService
{
    public function platformOverview(): array
    {
        $uploads = $this->manualUploadsQuery();

        $usage = ConversationAnalysis::query()
            ->where('source', ConversationSource::ManualUpload);

        return [
            'total_uploads' => (clone $uploads)->count(),
            'total_analyzed' => (clone $usage)->count(),
        ];
    }

    public function uploadsPerOrganization(): Collection
    {
        return Call::query()
            ->select('organization_id', DB::raw('COUNT(*) as upload_count'))
            ->where('source', ConversationSource::ManualUpload)
            ->groupBy('organization_id')
            ->with('organization:id,title')
            ->orderByDesc('upload_count')
            ->get();
    }

    public function uploadsPerEmployee(): Collection
    {
        return Call::query()
            ->select('organization_user_id', 'organization_id', DB::raw('COUNT(*) as upload_count'))
            ->where('source', ConversationSource::ManualUpload)
            ->whereNotNull('organization_user_id')
            ->groupBy('organization_user_id', 'organization_id')
            ->with(['employee:id,first_name,last_name', 'organization:id,title'])
            ->orderByDesc('upload_count')
            ->limit(50)
            ->get();
    }

    public function manualUploadsQuery(): Builder
    {
        return Call::query()->where('source', ConversationSource::ManualUpload);
    }

    public function organizationUploadCount(int $organizationId): int
    {
        return $this->manualUploadsQuery()
            ->where('organization_id', $organizationId)
            ->count();
    }

    public function employeeUploadCount(int $organizationUserId): int
    {
        return $this->manualUploadsQuery()
            ->where('organization_user_id', $organizationUserId)
            ->count();
    }
}
