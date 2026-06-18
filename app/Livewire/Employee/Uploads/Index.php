<?php

namespace App\Livewire\Employee\Uploads;

use App\Application\Call\Services\ManualAudioUploadService;
use App\Domain\Call\DTOs\ManualUploadMetadata;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Call\Enums\UploaderType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Livewire\Concerns\DispatchesUploadToasts;
use App\Livewire\Concerns\InteractsWithManualAudioUpload;
use App\Models\Call;
use App\Services\AiBillingService;
use App\Services\EmployeeContext;
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

#[Layout('layouts.employee')]
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

    public string $search = '';

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    public ?string $draftCustomFrom = null;

    public ?string $draftCustomTo = null;

    public ?string $highlightedSampleId = null;

    public function mount(): void
    {
        $sampleId = request()->string('sample')->toString();

        if ($sampleId !== '' && SampleConversations::find($sampleId) !== null) {
            $this->highlightedSampleId = $sampleId;
        }

        $this->draftCustomFrom = $this->dateFrom;
        $this->draftCustomTo = $this->dateUntil;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyCustomDateRange(?string $from = null, ?string $to = null): void
    {
        if ($from !== null || $to !== null) {
            $this->draftCustomFrom = $from ?: null;
            $this->draftCustomTo = $to ?: null;
        }

        $this->dateFrom = $this->draftCustomFrom;
        $this->dateUntil = $this->draftCustomTo;
        $this->resetPage();
    }

    public function submitForAnalysis(ManualAudioUploadService $uploadService): void
    {
        if (! $this->audio) {
            $this->audioReady = false;
            $this->selectedFileName = null;
            $this->selectedFileSize = null;

            $message = $this->highlightedSampleId
                ? __('ui.upload.audio_required')
                : __('validation.required', ['attribute' => __('validation.attributes.audio')]);

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

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
            ]);
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('audio')
                ?: $e->validator->errors()->first()
                ?: __('ui.upload.validation_failed');

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        try {
            $membership = EmployeeContext::membership();

            $callId = $uploadService->upload(
                organizationId: $membership->organization_id,
                uploaderUserId: auth()->id(),
                uploaderType: UploaderType::Employee,
                organizationUserId: $membership->id,
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
                ? ($e->validator->errors()->first('audio') ?: $e->validator->errors()->first() ?: __('ui.upload.error_default'))
                : $e->getMessage();

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        } catch (\Throwable $e) {
            $message = UserFacingError::upload();

            Log::error('Employee upload exception', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $this->redirect(route('employee.uploads.show', $callId), navigate: true);
    }

    public function submitSampleForAnalysis(string $sampleId, ManualAudioUploadService $uploadService): void
    {
        $sample = SampleConversations::find($sampleId);

        if ($sample === null || ! ($sample['available'] ?? false)) {
            $message = 'فایل مکالمه نمونه در دسترس نیست. لطفاً کمی بعد دوباره تلاش کنید.';
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        try {
            $this->validate([
                'title' => 'nullable|string|max:255',
                'customerName' => 'nullable|string|max:255',
                'customerPhone' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:5000',
                'category' => 'nullable|string|max:100',
                'tags' => 'nullable|string|max:500',
                'conversationDate' => 'nullable|date',
            ]);
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first()
                ?: __('ui.upload.validation_failed');

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        try {
            $membership = EmployeeContext::membership();

            $callId = $uploadService->uploadFromSample(
                organizationId: $membership->organization_id,
                uploaderUserId: auth()->id(),
                uploaderType: UploaderType::Employee,
                organizationUserId: $membership->id,
                sampleId: $sampleId,
                absolutePath: $sample['absolute_path'],
                displayFilename: $sample['filename'],
                metadata: new ManualUploadMetadata(
                    title: $this->title ?: $sample['title'],
                    customerName: $this->customerName ?: null,
                    customerPhone: $this->customerPhone ?: null,
                    notes: $this->notes ?: null,
                    category: $this->category ?: $sample['category'],
                    tags: $this->parseTags($this->tags) ?? ['مکالمه نمونه'],
                    conversationDate: $this->conversationDate ? new \DateTimeImmutable($this->conversationDate) : null,
                ),
            );
        } catch (InsufficientWalletBalanceException|ValidationException $e) {
            $message = $e instanceof ValidationException
                ? ($e->validator->errors()->first('audio') ?: $e->validator->errors()->first() ?: __('ui.upload.sample_failed'))
                : $e->getMessage();

            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        } catch (\Throwable $e) {
            $message = UserFacingError::upload();

            Log::error('Employee sample conversation upload exception', [
                'sample_id' => $sampleId,
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $this->redirect(route('employee.uploads.show', $callId), navigate: true);
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(): void {}

    public function render()
    {
        $membershipId = EmployeeContext::membership()->id;

        $uploads = Call::query()
            ->where('organization_id', EmployeeContext::organizationId())
            ->where('source', ConversationSource::ManualUpload)
            ->where(function ($query) use ($membershipId) {
                $query->where('organization_user_id', $membershipId)
                    ->orWhere('uploader_id', auth()->id());
            })
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_phone', 'like', "%{$this->search}%");
            }))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateUntil, fn ($q) => $q->whereDate('created_at', '<=', $this->dateUntil))
            ->withPlayableOrAnalyzedAudio()
            ->with(['latestAnalysis', 'recording', 'processingJob'])
            ->latest()
            ->paginate(15);

        return view('livewire.employee.uploads.index', [
            'uploads' => $uploads,
            'membership' => EmployeeContext::membership()->load('user'),
            'organizationId' => EmployeeContext::organizationId(),
            'wallet' => app(AiBillingService::class)->walletOverview(EmployeeContext::organizationId()),
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
