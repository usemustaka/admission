<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\ScholarshipKind;
use App\Models\Registrant;
use Illuminate\Support\Str;

/**
 * One kind of file as a registration knows it: a built-in {@see DocumentType} or a custom file the ruleset asked for.
 * Custom kinds live only in the ruleset copy on the registration (key `custom-xxxxxxxx`), so they keep the name and
 * the number of files they had when the child registered.
 */
final readonly class DocumentKind
{
    public const CUSTOM_PREFIX = 'custom-';

    public function __construct(
        public string $key,
        public string $label,
        public int $maxFiles,
        public bool $supporting,
        public ?ScholarshipKind $scholarship = null,
        public ?DocumentType $builtin = null,
    ) {}

    public static function builtin(DocumentType $type): self
    {
        return new self($type->value, $type->label(), $type->maxFiles(), $type->isSupporting(), $type->scholarshipKind(), $type);
    }

    public static function newCustomKey(): string
    {
        return self::CUSTOM_PREFIX.Str::lower(Str::random(8));
    }

    public static function isCustomKey(string $key): bool
    {
        return str_starts_with($key, self::CUSTOM_PREFIX);
    }

    /**
     * The kind behind a key for this registration, or null when it is neither built in nor in its ruleset copy.
     */
    public static function resolve(Registrant $registrant, string $key): ?self
    {
        $type = DocumentType::tryFrom($key);

        if ($type !== null) {
            return self::builtin($type);
        }

        foreach ($registrant->ruleset_snapshot['documents'] ?? [] as $row) {
            if ($row['type'] === $key && self::isCustomKey($key)) {
                return new self($key, (string) ($row['label'] ?? 'Berkas'), max(1, (int) ($row['max_files'] ?? 1)), false);
            }
        }

        return null;
    }

    public function slotLabel(int $slot): string
    {
        return $this->builtin?->slotLabel($slot) ?? ($this->maxFiles === 1 ? $this->label : "Berkas {$slot}");
    }
}
