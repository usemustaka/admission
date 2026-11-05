<?php

namespace Database\Factories;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Models\Note;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'noteable_type' => 'person',
            'noteable_id' => Person::factory(),
            'person_id' => fn (array $attributes) => $attributes['noteable_id'],
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
            'urgency' => NoteUrgency::Normal,
            'visibility' => NoteVisibility::Private,
            'shared_with_family' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (): array => ['visibility' => NoteVisibility::Public]);
    }

    public function sharedWithFamily(): static
    {
        return $this->state(fn (): array => ['shared_with_family' => true]);
    }
}
