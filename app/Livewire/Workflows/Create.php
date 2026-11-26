<?php

namespace App\Livewire\Workflows;

use App\Domain\Workflows\Actions\SubmitRequest;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\WorkflowTemplate;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Nowy wniosek: pick a template, fill in its fields, add attachments, submit. */
#[Title('Nowy wniosek')]
class Create extends Component
{
    use WithImageUploads;

    #[Url(as: 'szablon', except: '')]
    public string $templateId = '';

    public string $title = '';

    /** @var array<string, mixed> */
    public array $data = [];

    public $newImage = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $images = [];

    public function mount(): void
    {
        abort_unless(app(CurrentSchool::class)->id(), 403);
        if ($this->templateId !== '' && ! $this->template) {
            $this->templateId = '';
        }
    }

    #[Computed]
    public function templates()
    {
        return WorkflowTemplate::query()->availableIn(app(CurrentSchool::class)->required())
            ->withCount('stages')->orderByRaw('case when school_id is null then 1 else 0 end')->orderBy('position')->get();
    }

    #[Computed]
    public function template(): ?WorkflowTemplate
    {
        return $this->templateId !== '' ? $this->templates->firstWhere('id', (int) $this->templateId)?->load('stages') : null;
    }

    public function choose(int $id): void
    {
        $this->templateId = (string) $id;
        unset($this->template);
        abort_unless($this->template, 404);
        $this->data = collect($this->template->fields ?? [])->mapWithKeys(fn ($f) => [$f['key'] => $f['type'] === 'checkbox' ? false : ''])->all();
        $this->title = '';
        $this->images = [];
        $this->resetErrorBag();
    }

    public function back(): void
    {
        $this->reset('templateId', 'title', 'data', 'images');
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

    public function submit(SubmitRequest $submit)
    {
        abort_unless($this->template, 404);
        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'images' => ['array', 'max:'.config('mci.media.max_post_images')],
            'images.*' => $this->imageUploadRule(),
        ], [], ['title' => __('tytuł')]);

        try {
            $request = $submit->handle(Auth::user(), app(CurrentSchool::class)->required(), $this->template, $this->title, $this->data, $this->images);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }

        Flux::toast(variant: 'success', text: __('Złożono wniosek. Trafił do: :who.', ['who' => $request->currentStage()['approver_label'] ?? '—']));

        return $this->redirectRoute('workflows.show', $request, navigate: true);
    }

    public function render()
    {
        return view('livewire.workflows.create');
    }
}
