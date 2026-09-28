<?php

namespace App\Enums;

enum DataExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Queued'),
            self::Processing => __('Preparing'),
            self::Ready => __('Ready'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'amber',
            self::Ready => 'green',
            self::Failed => 'red',
        };
    }
}
