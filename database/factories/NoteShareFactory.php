<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\NoteShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteShare>
 */
class NoteShareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'note_id' => Note::factory(),
            'shareable_type' => 'user',
            'shareable_id' => User::factory(),
        ];
    }
}
