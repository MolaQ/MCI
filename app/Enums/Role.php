<?php

namespace App\Enums;

/**
 * Global roles. Stored in Spatie's `roles` table; permissions per role are
 * seeded from defaultPermissions() and may be changed later by an admin.
 */
enum Role: string
{
    case Admin = 'admin';
    case Coordinator = 'coordinator';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Student = 'student';

    public function label(): string
    {
        return __("enums.role.{$this->value}");
    }

    /**
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Coordinator => [
                Permission::GroupsManage,
                Permission::InvitesCreate,
                Permission::AnnouncementsPublishAny,
                Permission::AnnouncementsPublishOwnGroups,
                Permission::AlertsSendDrill,
                Permission::AlertsViewAllStats,
                Permission::EventsCreate,
                Permission::EventsPublishPublic,
                Permission::TasksAssignAny,
                Permission::StatsView,
                Permission::AuditView,
                Permission::CategoriesManage,
                Permission::WorkflowsManage,
                Permission::ProjectsManage,
            ],
            self::Teacher => [
                Permission::AnnouncementsPublishOwnGroups,
                Permission::EventsCreate,
            ],
            self::Staff => [
                Permission::AnnouncementsPublishOwnGroups,
            ],
            // Students are opt-in in phase one and get no publishing rights.
            self::Student => [],
        };
    }

    /**
     * Roles whose holders count as school staff (e.g. for the "all staff" group).
     */
    public function isStaff(): bool
    {
        return $this !== self::Student;
    }
}
