<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\WorkflowStatus;
use App\Jobs\NotifyWorkflow;
use App\Models\WorkflowRequest;
use App\Support\Features;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hourly: requests whose stage deadline (SLA) has passed remind their
 * approvers once a day, in daytime hours only (Europe/Warsaw).
 */
#[Signature('mci:workflow-reminders')]
#[Description('Remind approvers about overdue workflow decisions')]
class SendWorkflowReminders extends Command
{
    public function handle(Features $features): int
    {
        [$from, $to] = config('mci.tasks.overdue_hours');
        if (now()->hour < $from || now()->hour >= $to || $features->disabled(Feature::Workflows, null)) {
            return self::SUCCESS;
        }
        $off = $features->schoolsWithout(Feature::Workflows)->all();
        $sent = 0;

        WorkflowRequest::query()->where('status', WorkflowStatus::InProgress->value)
            ->whereNotNull('stage_due_at')->where('stage_due_at', '<', now())
            ->whereNotIn('school_id', $off)
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->startOfDay()))
            ->each(function (WorkflowRequest $request) use (&$sent) {
                $request->forceFill(['reminded_at' => now()])->save();
                $ids = $request->approverIds();
                if ($ids->isNotEmpty()) {
                    NotifyWorkflow::dispatch($request->id, $ids->all(), 'overdue');
                    $sent++;
                }
            });

        $this->info("Reminded about {$sent} request(s).");

        return self::SUCCESS;
    }
}
