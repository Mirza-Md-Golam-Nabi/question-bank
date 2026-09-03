<?php

namespace Database\Factories;

use App\Models\AcademicClass;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSubject>
 */
class ClassSubjectFactory extends Factory
{
    protected $model = ClassSubject::class;

    public function definition(): array
    {
        return [
            'academic_class_id' => AcademicClass::factory(),
            'subject_id' => Subject::factory(),
            'order_index' => 0,
        ];
    }
}
