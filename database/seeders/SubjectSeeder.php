<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Local-only convenience data — only runs when APP_ENV=local, so it never
 * touches a staging/production database even if called directly. Covers the
 * Bangladesh general-education line: common/primary-junior subjects plus the
 * Science, Business Studies (Commerce), and Humanities (Arts) groups used in
 * SSC/HSC.
 *
 * Each subject also says which classes teach it, and is assigned to them
 * (class_subjects) in the order listed. The classes come from
 * AcademicClassSeeder; a class that does not exist is skipped. Safe to run
 * again: an assignment that already exists — including one an admin made —
 * is left alone.
 */
class SubjectSeeder extends Seeder
{
    private const PRIMARY = ['Class 3', 'Class 4', 'Class 5'];

    private const JUNIOR = ['Class 6', 'Class 7', 'Class 8'];

    private const SSC = ['Class 9 / Class 10'];

    private const HSC = ['Class 11 / Class 12'];

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $subjects = $this->subjects();

        foreach ($subjects as $index => $subject) {
            $subjects[$index]['id'] = Subject::updateOrCreate(
                ['name' => $subject['name']],
                ['name_bn' => $subject['name_bn'], 'short_name' => $subject['short_name']],
            )->id;
        }

        $this->assignToClasses($subjects);
    }

    /**
     * @param  array<int, array{id: int, classes: array<int, string>}>  $subjects
     */
    private function assignToClasses(array $subjects): void
    {
        $classIds = AcademicClass::pluck('id', 'name');
        $nextOrderIndex = [];

        foreach ($subjects as $subject) {
            foreach ($subject['classes'] as $className) {
                $classId = $classIds->get($className);

                if ($classId === null) {
                    continue;
                }

                $nextOrderIndex[$classId] = ($nextOrderIndex[$classId] ?? 0) + 1;

                ClassSubject::firstOrCreate(
                    ['academic_class_id' => $classId, 'subject_id' => $subject['id']],
                    ['order_index' => $nextOrderIndex[$classId]],
                );
            }
        }

        if ($classIds->isEmpty()) {
            $this->command?->warn('SubjectSeeder: no classes found, so subjects were not assigned to any — run AcademicClassSeeder first.');
        }
    }

    /**
     * @return array<int, array{name: string, name_bn: string, short_name: string, classes: array<int, string>}>
     */
    public function subjects(): array
    {
        return [
            // Common / primary-junior
            ...$this->single('Bangla', 'বাংলা', 'BAN', self::PRIMARY),
            ...$this->papers('Bangla', 'বাংলা', 'BAN', [...self::JUNIOR, ...self::SSC, ...self::HSC]),
            ...$this->single('English', 'ইংরেজি', 'ENG', self::PRIMARY),
            ...$this->papers('English', 'ইংরেজি', 'ENG', [...self::JUNIOR, ...self::SSC, ...self::HSC]),
            ...$this->single('Mathematics', 'গণিত', 'MATH', [...self::PRIMARY, ...self::JUNIOR, ...self::SSC]),
            ...$this->single('General Science', 'সাধারণ বিজ্ঞান', 'GSC', [...self::PRIMARY, ...self::JUNIOR, ...self::SSC]),
            ...$this->single('Bangladesh and Global Studies', 'বাংলাদেশ ও বিশ্বপরিচয়', 'BGS', [...self::PRIMARY, ...self::JUNIOR, ...self::SSC]),
            ...$this->single('Information and Communication Technology', 'তথ্য ও যোগাযোগ প্রযুক্তি', 'ICT', [...self::JUNIOR, ...self::SSC, ...self::HSC]),
            ...$this->single('Religion and Moral Education', 'ধর্ম ও নৈতিক শিক্ষা', 'RME', [...self::PRIMARY, ...self::JUNIOR, ...self::SSC]),
            ...$this->single('Physical Education, Health and Sports', 'শারীরিক শিক্ষা, স্বাস্থ্যবিজ্ঞান ও খেলাধুলা', 'PEHS', [...self::JUNIOR, ...self::SSC]),
            ...$this->single('Career Education', 'ক্যারিয়ার শিক্ষা', 'CE', self::SSC),
            ...$this->single('Agriculture Studies', 'কৃষিশিক্ষা', 'AGS', [...self::JUNIOR, ...self::SSC]),
            ...$this->single('Arts and Crafts', 'চারু ও কারুকলা', 'AC', [...self::JUNIOR, ...self::SSC]),
            ...$this->single('Home Science', 'গার্হস্থ্য বিজ্ঞান', 'HS', [...self::JUNIOR, ...self::SSC]),

            // Science group — one paper at SSC, two at HSC
            ...$this->single('Physics', 'পদার্থবিজ্ঞান', 'PHY', self::SSC),
            ...$this->papers('Physics', 'পদার্থবিজ্ঞান', 'PHY', self::HSC),
            ...$this->single('Chemistry', 'রসায়ন', 'CHEM', self::SSC),
            ...$this->papers('Chemistry', 'রসায়ন', 'CHEM', self::HSC),
            ...$this->single('Biology', 'জীববিজ্ঞান', 'BIO', self::SSC),
            ...$this->papers('Biology', 'জীববিজ্ঞান', 'BIO', self::HSC),
            ...$this->single('Higher Mathematics', 'উচ্চতর গণিত', 'HM', self::SSC),
            ...$this->papers('Higher Mathematics', 'উচ্চতর গণিত', 'HM', self::HSC),

            // Business Studies (Commerce) group
            ...$this->single('Accounting', 'হিসাববিজ্ঞান', 'ACC', self::SSC),
            ...$this->papers('Accounting', 'হিসাববিজ্ঞান', 'ACC', self::HSC),
            ...$this->single('Finance and Banking', 'ফিন্যান্স ও ব্যাংকিং', 'FB', self::SSC),
            ...$this->single('Business Entrepreneurship', 'ব্যবসায় উদ্যোগ', 'BE', self::SSC),
            ...$this->papers('Business Organization and Management', 'ব্যবসায় সংগঠন ও ব্যবস্থাপনা', 'BOM', self::HSC),
            ...$this->papers('Finance, Banking and Insurance', 'ফিন্যান্স, ব্যাংকিং ও বিমা', 'FBI', self::HSC),

            // Humanities (Arts) group
            ...$this->single('History of Bangladesh and World Civilization', 'বাংলাদেশের ইতিহাস ও বিশ্বসভ্যতা', 'HIST', self::SSC),
            ...$this->papers('History of Bangladesh and World Civilization', 'বাংলাদেশের ইতিহাস ও বিশ্বসভ্যতা', 'HIST', self::HSC),
            ...$this->single('Civics and Citizenship', 'পৌরনীতি ও নাগরিকতা', 'CIV', self::SSC),
            ...$this->papers('Civics and Good Governance', 'পৌরনীতি ও সুশাসন', 'CGG', self::HSC),
            ...$this->single('Economics', 'অর্থনীতি', 'ECO', self::SSC),
            ...$this->papers('Economics', 'অর্থনীতি', 'ECO', self::HSC),
            ...$this->single('Geography and Environment', 'ভূগোল ও পরিবেশ', 'GEO', self::SSC),
            ...$this->papers('Geography and Environment', 'ভূগোল ও পরিবেশ', 'GEO', self::HSC),
            ...$this->papers('Sociology', 'সমাজবিজ্ঞান', 'SOC', self::HSC),
            ...$this->papers('Social Work', 'সমাজকর্ম', 'SW', self::HSC),
            ...$this->papers('Islamic History and Culture', 'ইসলামের ইতিহাস ও সংস্কৃতি', 'IHC', self::HSC),
            ...$this->papers('Logic', 'যুক্তিবিদ্যা', 'LOG', self::HSC),
            ...$this->papers('Home Science', 'গার্হস্থ্য বিজ্ঞান', 'HS', self::HSC),

            // Optional / 4th subject (cross-group)
            ...$this->papers('Statistics', 'পরিসংখ্যান', 'STAT', self::HSC),
            ...$this->papers('Psychology', 'মনোবিজ্ঞান', 'PSY', self::HSC),
            ...$this->papers('Agricultural Education', 'কৃষিশিক্ষা', 'AGE', self::HSC),
            ...$this->papers('Food and Nutrition', 'খাদ্য ও পুষ্টি', 'FN', self::HSC),
        ];
    }

    /**
     * @param  array<int, string>  $classes
     * @return array<int, array{name: string, name_bn: string, short_name: string, classes: array<int, string>}>
     */
    protected function single(string $name, string $nameBn, string $shortName, array $classes): array
    {
        return [
            ['name' => $name, 'name_bn' => $nameBn, 'short_name' => $shortName, 'classes' => $classes],
        ];
    }

    /**
     * Expands a subject into its "1st Paper" and "2nd Paper" variants, both
     * taught in the same classes.
     *
     * @param  array<int, string>  $classes
     * @return array<int, array{name: string, name_bn: string, short_name: string, classes: array<int, string>}>
     */
    protected function papers(string $name, string $nameBn, string $shortName, array $classes): array
    {
        return [
            ['name' => "{$name} 1st Paper", 'name_bn' => "{$nameBn} ১ম পত্র", 'short_name' => "{$shortName}-1", 'classes' => $classes],
            ['name' => "{$name} 2nd Paper", 'name_bn' => "{$nameBn} ২য় পত্র", 'short_name' => "{$shortName}-2", 'classes' => $classes],
        ];
    }
}
