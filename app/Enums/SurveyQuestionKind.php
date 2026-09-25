<?php

namespace App\Enums;

enum SurveyQuestionKind: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Rating = 'rating';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => __('One choice'),
            self::MultipleChoice => __('Several choices'),
            self::Rating => __('Rating 1–5'),
            self::Text => __('Written answer'),
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::SingleChoice || $this === self::MultipleChoice;
    }
}
