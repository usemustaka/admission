<?php

namespace App\Actions\Portal;

use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;

final readonly class RegisterChildData
{
    public function __construct(
        public string $name,
        public string $nik,
        public Gender $gender,
        public ?string $birthDate,
        public int $organizationId,
        public int $waveId,
        public ParentRelation $relation,
        public ?ParentKind $kind,
    ) {}
}
