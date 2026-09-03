<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Local-only convenience data — only runs when APP_ENV=local, so it never
 * touches a staging/production database even if called directly. Covers the
 * Bangladesh general-education line: common/primary-junior subjects plus the
 * Science, Business Studies (Commerce), and Humanities (Arts) groups used in
 * SSC/HSC.
 */
class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ($this->names() as $name) {
            Subject::firstOrCreate(['name' => $name]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function names(): array
    {
        return [
            // Common / primary-junior
            'Bangla',
            'Bangla 1st Paper',
            'Bangla 2nd Paper',
            'English',
            'English 1st Paper',
            'English 2nd Paper',
            'Mathematics',
            'General Science',
            'Bangladesh and Global Studies',
            'Information and Communication Technology',
            'Religion and Moral Education',
            'Physical Education, Health and Sports',
            'Career Education',
            'Agriculture Studies',
            'Arts and Crafts',

            // Science group
            'Physics 1st Paper',
            'Physics 2nd Paper',
            'Chemistry 1st Paper',
            'Chemistry 2nd Paper',
            'Biology 1st Paper',
            'Biology 2nd Paper',
            'Higher Mathematics 1st Paper',
            'Higher Mathematics 2nd Paper',

            // Business Studies (Commerce) group
            'Accounting 1st Paper',
            'Accounting 2nd Paper',
            'Business Organization and Management 1st Paper',
            'Business Organization and Management 2nd Paper',
            'Finance, Banking and Insurance 1st Paper',
            'Finance, Banking and Insurance 2nd Paper',
            'Business Entrepreneurship',

            // Humanities (Arts) group
            'History of Bangladesh and World Civilization 1st Paper',
            'History of Bangladesh and World Civilization 2nd Paper',
            'Civics and Good Governance 1st Paper',
            'Civics and Good Governance 2nd Paper',
            'Economics 1st Paper',
            'Economics 2nd Paper',
            'Geography and Environment 1st Paper',
            'Geography and Environment 2nd Paper',
            'Sociology 1st Paper',
            'Sociology 2nd Paper',
            'Social Work 1st Paper',
            'Social Work 2nd Paper',
            'Islamic History and Culture 1st Paper',
            'Islamic History and Culture 2nd Paper',
            'Logic 1st Paper',
            'Logic 2nd Paper',
            'Home Science 1st Paper',
            'Home Science 2nd Paper',

            // Optional / 4th subject (cross-group)
            'Statistics 1st Paper',
            'Statistics 2nd Paper',
            'Psychology 1st Paper',
            'Psychology 2nd Paper',
            'Agricultural Education 1st Paper',
            'Agricultural Education 2nd Paper',
            'Food and Nutrition 1st Paper',
            'Food and Nutrition 2nd Paper',
        ];
    }
}
