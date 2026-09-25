<?php

namespace App\Enums;

enum MeetingKind: string
{
    case Agm = 'agm';
    case Special = 'special';
    case Board = 'board';
    case Town = 'town';

    public function label(): string
    {
        return match ($this) {
            self::Agm => __('Annual general meeting'),
            self::Special => __('Special general meeting'),
            self::Board => __('Board meeting'),
            self::Town => __('Town hall'),
        };
    }

    /**
     * Whether owners meet and decide as owners: only then are owner units checked in, a quorum
     * of owners counted, and ballots attached. The board's own quorum is its directors.
     */
    public function isOwnersMeeting(): bool
    {
        return match ($this) {
            self::Agm, self::Special => true,
            self::Board, self::Town => false,
        };
    }

    /**
     * @return list<string>
     */
    public function defaultAgenda(): array
    {
        return match ($this) {
            self::Agm => [__('Call to order and quorum'), __('Approval of last year\'s minutes'), __('Financial statements'), __('Election of directors'), __('New business'), __('Adjournment')],
            self::Special => [__('Call to order and quorum'), __('Business for which the meeting was called'), __('Adjournment')],
            self::Board => [__('Call to order'), __('Approval of previous minutes'), __('Manager\'s report'), __('Financial report'), __('Old business'), __('New business'), __('Adjournment')],
            self::Town => [__('Welcome'), __('Updates from the board and management'), __('Questions from residents'), __('Close')],
        };
    }
}
