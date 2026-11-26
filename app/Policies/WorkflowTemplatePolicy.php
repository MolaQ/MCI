<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Support\CurrentSchool;

/** System templates: site admins. School templates: workflows.manage in that school. */
class WorkflowTemplatePolicy
{
    public function manage(User $actor, WorkflowTemplate $template): bool
    {
        return $template->school_id === null
            ? (bool) $actor->is_site_admin
            : app(CurrentSchool::class)->id() === $template->school_id && $actor->can(Permission::WorkflowsManage->value);
    }
}
