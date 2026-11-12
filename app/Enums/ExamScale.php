<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * How an exam aspect is graded. `number` is a score from 0 to 100; the others are a few named levels. Whatever the
 * scale, a result is stored as a whole number 0-100 (a level is its value), so totals, KKM and old data keep working:
 * the KKM of a level scale is the lowest level that still counts as passing.
 */
enum ExamScale: string implements HasLabel
{
    case Number = 'number';
    case GoodFair = 'good-fair';
    case GoodOnly = 'good-only';
    case MemorizedFair = 'memorized-fair';
    case MemorizedOnly = 'memorized-only';
    case AbleFair = 'able-fair';
    case AbleOnly = 'able-only';

    public function label(): string
    {
        return match ($this) {
            self::Number => 'Angka (0-100)',
            self::GoodFair => 'Baik / Kurang / Belum',
            self::GoodOnly => 'Baik / Belum',
            self::MemorizedFair => 'Hafal / Kurang / Belum',
            self::MemorizedOnly => 'Hafal / Belum',
            self::AbleFair => 'Bisa / Kurang / Belum',
            self::AbleOnly => 'Bisa / Belum',
        };
    }

    public function isNumeric(): bool
    {
        return $this === self::Number;
    }

    /**
     * The levels of a named scale from best to worst; empty for a number.
     *
     * @return list<array{value: int, label: string}>
     */
    public function levels(): array
    {
        $top = match ($this) {
            self::GoodFair, self::GoodOnly => 'Baik',
            self::MemorizedFair, self::MemorizedOnly => 'Hafal',
            self::AbleFair, self::AbleOnly => 'Bisa',
            self::Number => null,
        };

        if ($top === null) {
            return [];
        }

        return match ($this) {
            self::GoodFair, self::MemorizedFair, self::AbleFair => [['value' => 100, 'label' => $top], ['value' => 50, 'label' => 'Kurang'], ['value' => 0, 'label' => 'Belum']],
            default => [['value' => 100, 'label' => $top], ['value' => 0, 'label' => 'Belum']],
        };
    }

    /**
     * Levels that can be the KKM: a scale cannot require "Belum".
     *
     * @return list<int>
     */
    public function passingValues(): array
    {
        return array_values(array_filter(array_column($this->levels(), 'value'), fn (int $value): bool => $value > 0));
    }

    public function isValidScore(int $score): bool
    {
        return $this->isNumeric()
            ? $score >= 0 && $score <= 100
            : in_array($score, array_column($this->levels(), 'value'), true);
    }

    /**
     * A stored score as people read it: the number, or the name of the level.
     */
    public function display(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach ($this->levels() as $level) {
            if ($level['value'] === $score) {
                return $level['label'];
            }
        }

        return (string) $score;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $scale): array => ['value' => $scale->value, 'label' => $scale->label()], self::cases());
    }
}
