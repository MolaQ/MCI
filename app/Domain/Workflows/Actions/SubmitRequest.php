<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\Media\Actions\StoreImage;
use App\Domain\Workflows\RequestFields;
use App\Domain\Workflows\WorkflowEngine;
use App\Enums\Feature;
use App\Enums\WorkflowStatus;
use App\Models\School;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use App\Support\Features;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Submits a request from a template: checks the fields and attachments,
 * freezes the stages, and sends it to the first stage's approvers.
 */
class SubmitRequest
{
    public function __construct(private WorkflowEngine $engine, private StoreImage $storeImage) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     */
    public function handle(User $actor, School $school, WorkflowTemplate $template, string $title, array $data, array $images = []): WorkflowRequest
    {
        if (app(Features::class)->disabled(Feature::Workflows, $school)) {
            throw new AuthorizationException(__('Obiegi są wyłączone w tej szkole.'));
        }
        if (! $actor->belongsToSchool($school)) {
            throw new AuthorizationException(__('Nie należysz do tej szkoły.'));
        }
        if (! WorkflowTemplate::query()->availableIn($school)->whereKey($template->id)->exists()) {
            throw ValidationException::withMessages(['template' => __('Ten szablon nie jest dostępny w tej szkole.')]);
        }

        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['title' => __('Podaj tytuł wniosku (do 200 znaków).')]);
        }
        $clean = RequestFields::clean($template->fields ?? [], $data);

        $max = config('mci.media.max_post_images');
        if (count($images) < $template->min_attachments) {
            throw ValidationException::withMessages(['images' => trans_choice('Dołącz co najmniej :count załącznik.|Dołącz co najmniej :count załączniki.|Dołącz co najmniej :count załączników.', $template->min_attachments)]);
        }
        if (count($images) > $max) {
            throw ValidationException::withMessages(['images' => __('Możesz dodać najwyżej :count obrazów.', ['count' => $max])]);
        }

        $stages = $template->stages()->get()->map->snapshot()->values()->all();
        $this->engine->assertRoutable($stages, $school, $actor);

        $stored = [];
        try {
            return DB::transaction(function () use ($actor, $school, $template, $title, $clean, $stages, $images, &$stored) {
                $request = WorkflowRequest::query()->create([
                    'template_id' => $template->id,
                    'school_id' => $school->id,
                    'requester_id' => $actor->id,
                    'title' => $title,
                    'template_name' => $template->name,
                    'data' => $clean,
                    'stages' => $stages,
                    'status' => WorkflowStatus::InProgress,
                    'submitted_at' => now(),
                ]);

                foreach (array_values($images) as $i => $file) {
                    $media = $this->storeImage->handle($file, 'image', $school->id, $actor, $request, false, 'images.'.$i);
                    $media->update(['position' => $i]);
                    $stored[] = $media->path;
                }

                WorkflowAction::query()->create(['request_id' => $request->id, 'user_id' => $actor->id, 'stage_position' => 0, 'action' => 'submitted']);
                $this->engine->enter($request, 0, $actor);
                $this->engine->audit($request, 'workflow.submitted', ['stages' => count($stages), 'attachments' => count($stored)], $actor);

                return $request;
            });
        } catch (\Throwable $e) {
            Storage::disk('media')->delete($stored);

            throw $e;
        }
    }
}
