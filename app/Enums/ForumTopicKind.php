<?php

namespace App\Enums;

enum ForumTopicKind: string
{
    case Discussion = 'discussion';
    case ForSale = 'for_sale';
    case Wanted = 'wanted';
    case Free = 'free';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Discussion => __('Discussion'),
            self::ForSale => __('For sale'),
            self::Wanted => __('Wanted'),
            self::Free => __('Free'),
            self::Service => __('Services'),
        };
    }

    public function isClassified(): bool
    {
        return $this !== self::Discussion;
    }

    /**
     * @return list<self>
     */
    public static function classifieds(): array
    {
        return [self::ForSale, self::Wanted, self::Free, self::Service];
    }
}
