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
#[Title('آپلودهای من')]
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
            ]);
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('audio')
                ?: $e->validator->errors()->first()
                ?: 'اعتبارسنجی ناموفق بود. لطفاً فرم را بررسی و دوباره تلاش کنید.';

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
                ? ($e->validator->errors()->first('audio') ?: $e->validator->errors()->first() ?: 'آپلود فایل صوتی ناموفق بود.')
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

        $this->reset(['audio', 'title', 'customerName', 'customerPhone', 'notes', 'category', 'tags', 'conversationDate']);
        $this->selectedFileName = null;
        $this->selectedFileSize = null;
        $this->dispatchUploadSuccessToast(route('employee.uploads.show', $callId));
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
        ]);

        try {
            $membership = EmployeeContext::membership();

            $callId = $uploadService->uploadFromSample(
                organizationId: $membership->organization_id,
                uploaderUserId: auth()->id(),
                uploaderType: UploaderType::Employee,
                organizationUserId: $membership->id,
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

            Log::error('Employee sample conversation upload exception', [
                'sample_id' => $sampleId,
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
            $this->addError('audio', $message);
            $this->dispatchUploadErrorToast($message);

            return;
        }

        $this->reset(['audio', 'title', 'customerName', 'customerPhone', 'notes', 'category', 'tags', 'conversationDate']);
        $this->selectedFileName = null;
        $this->selectedFileSize = null;
        $this->dispatchUploadSuccessToast(route('employee.uploads.show', $callId));
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(): void {}

    public function render()
    {
        $uploads = Call::query()
            ->where('organization_id', EmployeeContext::organizationId())
            ->where('source', ConversationSource::ManualUpload)
            ->where(function ($query) {
                $membershipId = EmployeeContext::membership()->id;

                $query->where('organization_user_id', $membershipId)
                    ->orWhere('uploader_id', auth()->id());
            })
            ->withPlayableOrAnalyzedAudio()
            ->with(['latestAnalysis', 'recording', 'processingJob'])
            ->latest()
            ->paginate(12);

        return view('livewire.employee.uploads.index', [
            'uploads' => $uploads,
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
