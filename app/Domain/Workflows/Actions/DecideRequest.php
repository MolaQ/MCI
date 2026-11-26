<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\Media\Actions\StoreImage;
use App\Domain\Workflows\WorkflowEngine;
use App\Enums\Feature;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Jobs\NotifyWorkflow;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowRequest;
use App\Support\Features;
use App\Support\Mentions;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Zatwierdź / Odrzuć / Zwróć do poprawy at the current stage, with an
 * optional comment (@mentions) and images. Rejecting or returning needs a
 * comment, so the requester knows why. Approving the last stage completes
 * the request.
 */
class DecideRequest
{
    public const DECISIONS = ['approve', 'reject', 'return'];

    public function __construct(private WorkflowEngine $engine, private StoreImage $storeImage) {}

    /**
     * @param  list<UploadedFile>  $images
     */
    public function handle(User $actor, WorkflowRequest $request, string $decision, ?string $comment = null, array $images = []): WorkflowRequest
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => __('Nieznana decyzja.')]);
        }
        if (Gate::forUser($actor)->denies('decide', $request)) {
            throw new AuthorizationException(__('Ten wniosek nie czeka na Twoją decyzję.'));
        }

        $school = $request->school;
        $comment = RichText::sanitize((string) $comment);
        $comment = RichText::isBlank($comment) ? null : $comment;
        if ($decision !== 'approve' && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('Napisz krótko, dlaczego — wnioskodawca zobaczy ten komentarz.')]);
        }
        $mentions = ['html' => $comment, 'users' => [], 'groups' => []];
        if ($comment !== null) {
            $mentions = app(Features::class)->enabled(Feature::Mentions, $school) ? Mentions::normalize($comment, $school) : ['html' => Mentions::strip($comment), 'users' => [], 'groups' => []];
        }
        if (count($images) > config('mci.media.max_post_images')) {
            throw ValidationException::withMessages(['images' => __('Możesz dodać najwyżej :count obrazów.', ['count' => config('mci.media.max_post_images')])]);
        }

        $stored = [];
        try {
            DB::transaction(function () use ($actor, $request, $decision, $mentions, $images, $school, &$stored) {
                $position = $request->current_position;
                $stage = $request->stages[$position];
                $action = WorkflowAction::query()->create([
                    'request_id' => $request->id, 'user_id' => $actor->id, 'stage_position' => $position,
                    'action' => ['approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned'][$decision],
                    'comment' => $mentions['html'],
                ]);
                foreach (array_values($images) as $i => $file) {
                    $media = $this->storeImage->handle($file, 'image', $request->school_id, $actor, $action, false, 'images.'.$i);
                    $media->update(['position' => $i]);
                    $stored[] = $media->path;
                }
                Mentions::sync($action, $mentions['users'], [], $school, $actor);

                $this->engine->closeStageTasks($request, $decision === 'approve' ? TaskStatus::Done : TaskStatus::Cancelled, $actor);
                $this->engine->audit($request, 'workflow.'.$action->action, ['stage' => $stage['name'], 'stage_position' => $position + 1], $actor);

                if ($decision === 'approve') {
                    $this->engine->followUp($request, $stage, $actor);
                    if (isset($request->stages[$position + 1])) {
                        $this->engine->enter($request, $position + 1, $actor);
                        $this->engine->audit($request, 'workflow.advanced', ['stage' => $request->stages[$position + 1]['name'], 'stage_position' => $position + 2], $actor);
                    } else {
                        $this->finish($request, WorkflowStatus::Approved, $actor);
                    }
                } elseif ($decision === 'reject') {
                    $this->finish($request, WorkflowStatus::Rejected, $actor);
                } else {
                    $request->forceFill(['status' => WorkflowStatus::Returned, 'stage_due_at' => null])->save();
                    $this->notifyRequester($request, 'returned', $actor);
                }

                $mentioned = collect($mentions['users'])->reject(fn ($id) => $id === $actor->id || $id === $request->requester_id)->values();
                if ($mentioned->isNotEmpty()) {
                    NotifyWorkflow::dispatch($request->id, $mentioned->all(), 'mention', $actor->id)->afterCommit();
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('media')->delete($stored);

            throw $e;
        }

        return $request->refresh();
    }

    private function finish(WorkflowRequest $request, WorkflowStatus $status, User $actor): void
    {
        $request->forceFill(['status' => $status, 'completed_at' => now(), 'stage_due_at' => null])->save();
        $this->notifyRequester($request, $status === WorkflowStatus::Approved ? 'approved' : 'rejected', $actor);
    }

    private function notifyRequester(WorkflowRequest $request, string $kind, User $actor): void
    {
        if ($request->requester_id && $request->requester_id !== $actor->id) {
            NotifyWorkflow::dispatch($request->id, [$request->requester_id], $kind, $actor->id)->afterCommit();
        }
    }
}
