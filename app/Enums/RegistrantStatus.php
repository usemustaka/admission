<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum RegistrantStatus: string implements HasLabel
{
    /** The parent is still filling in the data. */
    case Draft = 'draft';

    /** Submitted and waiting for the committee. */
    case Review = 'review';

    /** The committee asked for corrections. */
    case Revision = 'revision';

    /** The data was accepted. */
    case Validated = 'validated';

    /** The result was announced. */
    case Announced = 'announced';

    /** Placed into an academic year as a student. */
    case Placed = 'placed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Review => 'Sedang diperiksa',
            self::Revision => 'Perlu diperbaiki',
            self::Validated => 'Data valid',
            self::Announced => 'Sudah diumumkan',
            self::Placed => 'Sudah ditempatkan',
        };
    }

    /**
     * Whether parents may still change the data.
     */
    public function isEditableByParent(): bool
    {
        return $this === self::Draft || $this === self::Revision;
    }

    /**
     * Where the normal flow can go from here. Bypassing the flow is a separate, noted action.
     *
     * @return list<self>
     */
    public function nextInFlow(): array
    {
        return match ($this) {
            self::Draft, self::Revision => [self::Review],
            self::Review => [self::Validated, self::Revision],
            self::Validated => [self::Announced],
            self::Announced => [self::Placed],
            self::Placed => [],
        };
    }
}
