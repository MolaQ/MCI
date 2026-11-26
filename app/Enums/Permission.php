<?php

namespace App\Enums;

/**
 * Every permission the application checks. Seeded into Spatie's
 * `permissions` table. Any permission can also be granted directly to a
 * single user (e.g. alerts.send for the school safety officer).
 */
enum Permission: string
{
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case GroupsManage = 'groups.manage';
    case InvitesCreate = 'invites.create';
    case AnnouncementsPublishAny = 'announcements.publish.any';
    case AnnouncementsPublishOwnGroups = 'announcements.publish.own_groups';
    case AlertsSend = 'alerts.send';
    case AlertsSendDrill = 'alerts.send.drill';
    case AlertsViewAllStats = 'alerts.view_all_stats';
    case EventsCreate = 'events.create';
    case EventsPublishPublic = 'events.publish_public';
    case TasksAssignAny = 'tasks.assign.any';
    case StatsView = 'stats.view';
    case AuditView = 'audit.view';
    case SettingsManage = 'settings.manage';
    case CategoriesManage = 'categories.manage';
    case WorkflowsManage = 'workflows.manage';
    case ProjectsManage = 'projects.manage';

    public function label(): string
    {
        return __('enums.permission.'.str_replace('.', '_', $this->value));
    }

    /**
     * Permissions that should be granted to individuals with care.
     */
    public function isSensitive(): bool
    {
        return in_array($this, [self::UsersManage, self::RolesManage, self::AlertsSend, self::SettingsManage], true);
    }
}
