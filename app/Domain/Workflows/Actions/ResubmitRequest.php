<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\RequestFields;
use App\Domain\Workflows\WorkflowEngine;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowRequest;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** After "Zwróć do poprawy": the requester corrects the fields and sends it back to the same stage. */
class ResubmitRequest
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, WorkflowRequest $request, string $title, array $data, ?string $comment = null): WorkflowRequest
    {
        if (Gate::forUser($actor)->denies('resubmit', $request)) {
            throw new AuthorizationException(__('Tego wniosku nie można teraz poprawić.'));
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['title' => __('Podaj tytuł wniosku (do 200 znaków).')]);
        }
        $clean = RequestFields::clean($request->template?->fields ?? [], $data);
        $comment = RichText::sanitize((string) $comment);

        DB::transaction(function () use ($actor, $request, $title, $clean, $comment) {
            $request->forceFill(['title' => $title, 'data' => $clean])->save();
            WorkflowAction::query()->create([
                'request_id' => $request->id, 'user_id' => $actor->id, 'stage_position' => $request->current_position,
                'action' => 'resubmitted', 'comment' => RichText::isBlank($comment) ? null : $comment,
            ]);
            $this->engine->enter($request, $request->current_position, $actor);
            $this->engine->audit($request, 'workflow.resubmitted', ['stage' => $request->currentStage()['name'] ?? null], $actor);
        });

        return $request->refresh();
    }
}
