<?php

namespace Database\Factories;

use App\Models\ExamDay;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_day_id' => ExamDay::factory(),
            'label' => 'Sesi 1',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'quota' => 30,
        ];
    }
}
