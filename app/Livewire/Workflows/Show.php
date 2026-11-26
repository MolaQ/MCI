<?php

namespace App\Livewire\Workflows;

use App\Domain\Workflows\Actions\CancelRequest;
use App\Domain\Workflows\Actions\DecideRequest;
use App\Domain\Workflows\Actions\ResubmitRequest;
use App\Domain\Workflows\RequestFields;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\WorkflowRequest;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * One request: stage path, answers, attachments and history; the decision
 * panel for current approvers (Zatwierdź / Zwróć do poprawy / Odrzuć with a
 * comment, @mentions and images) and, for the requester, correction and
 * withdrawal.
 */
class Show extends Component
{
    use WithImageUploads;

    #[Locked]
    public int $requestId;

    public string $comment = '';

    public $newImage = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $images = [];

    public bool $editing = false;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(WorkflowRequest $request): void
    {
        $this->authorize('view', $request);
        $this->requestId = $request->id;
        Auth::user()->unreadNotifications()->where('data->workflow_request_id', $request->id)->update(['read_at' => now()]);
    }

    #[Computed]
    public function request(): WorkflowRequest
    {
        return WorkflowRequest::query()->with(['school', 'approverSchool', 'requester', 'template', 'images', 'actions.user', 'actions.images', 'tasks'])->findOrFail($this->requestId);
    }

    public function updatedNewImage(): void
    {
        $this->validateOnly('newImage', ['newImage' => $this->imageUploadRule()]);
        if (count($this->images) >= config('mci.media.max_post_images')) {
            $this->addError('newImage', __('Możesz dodać najwyżej :count obrazów.', ['count' => config('mci.media.max_post_images')]));
        } elseif ($this->newImage) {
            $this->images[] = $this->newImage;
        }
        $this->newImage = null;
    }

    public function removeImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function decide(string $decision, DecideRequest $decide): void
    {
        $this->validate(['comment' => ['nullable', 'string', 'max:20000'], 'images.*' => $this->imageUploadRule()]);
        try {
            $decide->handle(Auth::user(), $this->request, $decision, $this->comment, $this->images);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->reset('comment', 'images');
        unset($this->request);
        Flux::toast(variant: 'success', text: match ($decision) {
            'approve' => __('Zatwierdzono.'),
            'reject' => __('Odrzucono wniosek.'),
            default => __('Zwrócono do poprawy.'),
        });
    }

    public function startEdit(): void
    {
        $this->authorize('resubmit', $this->request);
        $this->title = $this->request->title;
        $this->data = $this->request->data ?? [];
        $this->editing = true;
    }

    public function resubmit(ResubmitRequest $resubmit): void
    {
        try {
            $resubmit->handle(Auth::user(), $this->request, $this->title, $this->data, $this->comment);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->reset('editing', 'comment');
        unset($this->request);
        Flux::toast(variant: 'success', text: __('Wniosek wrócił do etapu „:stage”.', ['stage' => $this->request->currentStage()['name'] ?? '']));
    }

    public function cancel(CancelRequest $cancel): void
    {
        $cancel->handle(Auth::user(), $this->request);
        unset($this->request);
        Flux::toast(text: __('Wycofano wniosek.'));
    }

    public function render()
    {
        $r = $this->request;
        $user = Auth::user();

        return view('livewire.workflows.show', [
            'canDecide' => $user->can('decide', $r),
            'canResubmit' => $user->can('resubmit', $r),
            'canCancel' => $user->can('cancel', $r),
            'fields' => $r->template?->fields ?? [],
            'display' => fn (array $field, mixed $value) => RequestFields::display($field, $value),
        ])->title($r->title);
    }
}
