<?php

namespace App\Enums;

/**
 * Every switchable feature (docs/ARCHITECTURE.md §8.2). Two levels:
 *  - site:   on/off for the whole portal, plus whether schools may change it;
 *  - school: a school's own on/off, only where the site allows overrides.
 * Effective = site on AND (no override allowed OR school value ?? true).
 * Resolved by App\Support\Features; enforced in routes (`feature:` middleware),
 * actions and views (`@feature`).
 */
enum Feature: string
{
    // Communication
    case Announcements = 'announcements';
    case Mentions = 'announcements.mentions';
    case AnnouncementImages = 'announcements.images';
    case AnnouncementConfirmation = 'announcements.confirmation';
    case AnnouncementScheduling = 'announcements.scheduling';
    case PublicAnnouncements = 'announcements.public';

    // Events
    case Events = 'events';
    case PublicEvents = 'events.public';
    case EventCovers = 'events.covers';
    case EventIcs = 'events.ics';

    // Public front and screens
    case PublicFront = 'public.front';
    case PublicOffers = 'public.offers';
    case TvMode = 'public.tv';

    // Groups and onboarding
    case GroupSelfJoin = 'groups.self_join';
    case GroupLogos = 'groups.logos';
    case Invites = 'invites';
    case InviteQr = 'invites.qr';

    // Image editor
    case BackgroundRemoval = 'media.background_removal';

    public function label(): string
    {
        return __('features.items.'.$this->langKey().'.label');
    }

    public function description(): string
    {
        return __('features.items.'.$this->langKey().'.description');
    }

    /** Translation keys cannot contain dots, so `events.ics` → `events_ics`. */
    private function langKey(): string
    {
        return str_replace('.', '_', $this->value);
    }

    public function category(): string
    {
        return match ($this) {
            self::Announcements, self::Mentions, self::AnnouncementImages, self::AnnouncementConfirmation,
            self::AnnouncementScheduling, self::PublicAnnouncements => 'communication',
            self::Events, self::PublicEvents, self::EventCovers, self::EventIcs => 'events',
            self::PublicFront, self::PublicOffers, self::TvMode => 'public',
            self::GroupSelfJoin, self::GroupLogos, self::Invites, self::InviteQr => 'groups',
            self::BackgroundRemoval => 'media',
        };
    }

    /** A sub-feature is off whenever its parent module is off. */
    public function parent(): ?self
    {
        return match ($this) {
            self::Mentions, self::AnnouncementImages, self::AnnouncementConfirmation,
            self::AnnouncementScheduling, self::PublicAnnouncements => self::Announcements,
            self::PublicEvents, self::EventCovers, self::EventIcs => self::Events,
            self::PublicOffers => self::PublicFront,
            self::InviteQr => self::Invites,
            default => null,
        };
    }

    /** Whether schools may switch it for themselves by default (site admin can change). */
    public function schoolOverridableByDefault(): bool
    {
        return $this !== self::TvMode;
    }

    /**
     * @return array<string, list<self>>
     */
    public static function byCategory(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->category()][] = $case;
        }

        return $out;
    }
}
