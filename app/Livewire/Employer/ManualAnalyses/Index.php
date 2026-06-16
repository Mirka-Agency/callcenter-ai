<?php

namespace App\Livewire\Employer\ManualAnalyses;

use App\Application\Call\Services\ManualAudioUploadService;
use App\Domain\Call\DTOs\ManualUploadMetadata;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Call\Enums\UploaderType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Livewire\Concerns\DispatchesUploadToasts;
use App\Livewire\Concerns\InteractsWithManualAudioUpload;
use App\Models\Call;
use App\Models\OrganizationUser;
use App\Services\AiBillingService;
use App\Services\EmployerContext;
use App\Support\SampleConversations;
use App\Support\UserFacingError;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.employer')]
#[Title('آپلود تماس')]
class Index extends Component
{
    use DispatchesUploadToasts;
    use InteractsWithManualAudioUpload;
    use WithFileUploads;
    use WithPagination;

    public $audio;

    public string $title = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $notes = '';

    public string $category = '';

    public string $tags = '';

    public ?string $conversationDate = null;

    public ?int $employeeId = null;

    public ?int $filterEmployeeId = null;

    public string $search = '';

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    public ?string $highlightedSampleId = null;

    public function mount(): void
    {
        $sampleId = request()->string('sample')->toString();

        if ($sampleId !== '' && SampleConversations::find($sampleId) !== null) {
            $this->highlightedSampleId = $sampleId;
        }
    }

    public function submitForAnalysis(ManualAudioUploadService $uploadService): void
    {
        try {
            $this->validate([
                'audio' => 'required|file|max:51200',
                'title' => 'nullable|string|max:255',
                'customerName' => 'nullable|string|max:255',
                'customerPhone' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:5000',
                'category' => 'nullable|string|max:100',
                'tags' => 'nullable|string|max:500',
                'conversationDate' => 'nullable|date',
                'employeeId' => 'nullable|integer|exists:organization_user,id',
            ]);
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('audio')
                ?: $e->validator->errors()->first()
                ?: 'اعتبارسنجی ناموفق بود. لطفاً فرم را بررسی و دوباره تلاش کنید.';

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $organizationId = EmployerContext::organizationId();

        if ($this->employeeId) {
            abort_unless(
                OrganizationUser::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($this->employeeId)
                    ->exists(),
                422,
            );
        }

        try {
            $callId = $uploadService->upload(
                organizationId: $organizationId,
                uploaderUserId: auth()->id(),
                uploaderType: UploaderType::Employer,
                organizationUserId: $this->employeeId,
                file: $this->audio,
                metadata: new ManualUploadMetadata(
                    title: $this->title ?: null,
                    customerName: $this->customerName ?: null,
                    customerPhone: $this->customerPhone ?: null,
                    notes: $this->notes ?: null,
                    category: $this->category ?: null,
                    tags: $this->parseTags($this->tags),
                    conversationDate: $this->conversationDate ? new \DateTimeImmutable($this->conversationDate) : null,
                ),
            );
        } catch (InsufficientWalletBalanceException|ValidationException $e) {
            $message = $e instanceof ValidationException
                ? ($e->validator->errors()->first('audio') ?: $e->validator->errors()->first() ?: 'آپلود فایل صوتی ناموفق بود.')
                : $e->getMessage();

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        } catch (\Throwable $e) {
            $message = UserFacingError::upload();

            Log::error('Manual upload exception', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $this->resetUploadFormFields();
        $this->markUploadZoneSuccess();
        $this->dispatchUploadSuccessToast(route('employer.manual-analyses.show', $callId));
    }

    public function submitSampleForAnalysis(string $sampleId, ManualAudioUploadService $uploadService): void
    {
        $sample = SampleConversations::find($sampleId);

        if ($sample === null || ! ($sample['available'] ?? false)) {
            $message = 'فایل این مکالمه نمونه هنوز در دسترس نیست.';
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $this->validate([
            'title' => 'nullable|string|max:255',
            'customerName' => 'nullable|string|max:255',
            'customerPhone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|string|max:500',
            'conversationDate' => 'nullable|date',
            'employeeId' => 'nullable|integer|exists:organization_user,id',
        ]);

        $organizationId = EmployerContext::organizationId();

        if ($this->employeeId) {
            abort_unless(
                OrganizationUser::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($this->employeeId)
                    ->exists(),
                422,
            );
        }

        try {
            $callId = $uploadService->uploadFromSample(
                organizationId: $organizationId,
                uploaderUserId: auth()->id(),
                uploaderType: UploaderType::Employer,
                organizationUserId: $this->employeeId,
                absolutePath: $sample['absolute_path'],
                displayFilename: $sample['filename'],
                metadata: new ManualUploadMetadata(
                    title: $this->title ?: $sample['title'],
                    customerName: $this->customerName ?: null,
                    customerPhone: $this->customerPhone ?: null,
                    notes: $this->notes ?: $sample['description'],
                    category: $this->category ?: $sample['category'],
                    tags: $this->parseTags($this->tags) ?? ['نمونه دمو'],
                    conversationDate: $this->conversationDate ? new \DateTimeImmutable($this->conversationDate) : null,
                ),
            );
        } catch (InsufficientWalletBalanceException|ValidationException $e) {
            $message = $e instanceof ValidationException
                ? ($e->validator->errors()->first('audio') ?: $e->validator->errors()->first() ?: 'شروع تحلیل نمونه ناموفق بود.')
                : $e->getMessage();

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        } catch (\Throwable $e) {
            $message = UserFacingError::upload();

            Log::error('Sample conversation upload exception', [
                'sample_id' => $sampleId,
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $this->resetUploadFormFields();
        $this->markUploadZoneSuccess();
        $this->dispatchUploadSuccessToast(route('employer.manual-analyses.show', $callId));
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(): void {}

    public function render()
    {
        $organizationId = EmployerContext::organizationId();

        $uploads = Call::query()
            ->where('organization_id', $organizationId)
            ->where('source', ConversationSource::ManualUpload)
            ->withPlayableOrAnalyzedAudio()
            ->with(['employee.user', 'uploader', 'latestAnalysis', 'recording', 'processingJob'])
            ->when($this->filterEmployeeId, fn ($q) => $q->where('organization_user_id', $this->filterEmployeeId))
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_phone', 'like', "%{$this->search}%");
            }))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateUntil, fn ($q) => $q->whereDate('created_at', '<=', $this->dateUntil))
            ->latest()
            ->paginate(15);

        $employees = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();

        $wallet = app(AiBillingService::class)->walletOverview($organizationId);

        return view('livewire.employer.manual-analyses.index', [
            'uploads' => $uploads,
            'employees' => $employees,
            'wallet' => $wallet,
            'organizationId' => $organizationId,
            'sampleConversations' => SampleConversations::all(),
        ]);
    }

    private function parseTags(string $tags): ?array
    {
        if (blank($tags)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags))));
    }
}
