<?php

namespace App\Enums;

use App\Models\PlenaryMeeting;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PlenaryMeetingStatus: string implements HasColor, HasLabel
{
    case NeedsReview = 'needs_review';
    case ImportFailed = 'import_failed';
    case ImportProcessing = 'import_processing';
    case Scheduled = 'scheduled';
    case QuestionsAdded = 'questions_added';
    case Empty = 'empty';

    public static function fromMeeting(PlenaryMeeting $meeting): self
    {
        if ($meeting->imports_awaiting_review_count > 0) {
            return self::NeedsReview;
        }

        if ($meeting->imports_failed_count > 0) {
            return self::ImportFailed;
        }

        if ($meeting->imports_processing_count > 0) {
            return self::ImportProcessing;
        }

        if ($meeting->date->isAfter(today())) {
            return self::Scheduled;
        }

        if ($meeting->motions_count > 0) {
            return self::QuestionsAdded;
        }

        return self::Empty;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NeedsReview => 'Потрібна перевірка',
            self::ImportFailed => 'Помилка імпорту',
            self::ImportProcessing => 'Імпорт обробляється',
            self::Scheduled => 'Заплановане',
            self::QuestionsAdded => 'Є питання',
            self::Empty => 'Не наповнене',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NeedsReview => 'warning',
            self::ImportFailed => 'danger',
            self::ImportProcessing => 'info',
            self::Scheduled, self::Empty => 'gray',
            self::QuestionsAdded => 'success',
        };
    }
}
