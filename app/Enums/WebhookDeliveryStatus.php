<?php

namespace App\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Retrying'),
            self::Succeeded => __('Delivered'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Succeeded => 'green',
            self::Failed => 'red',
        };
    }
}
