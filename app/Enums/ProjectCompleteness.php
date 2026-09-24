<?php

namespace App\Enums;

enum ProjectCompleteness: string
{
    case Concept = 'concept';
    case Starter = 'starter';
    case Mvp = 'mvp';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Starter => 'Starter',
            self::Mvp => 'MVP',
            self::Complete => 'Complete',
        };
    }
}
