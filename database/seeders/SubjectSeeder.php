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

        foreach ($this->subjects() as $subject) {
            Subject::updateOrCreate(
                ['name' => $subject['name']],
                ['name_bn' => $subject['name_bn'], 'short_name' => $subject['short_name']],
            );
        }
    }

    /**
     * @return array<int, array{name: string, name_bn: string, short_name: string}>
     */
    public function subjects(): array
    {
        return [
            // Common / primary-junior
            ...$this->single('Bangla', 'বাংলা', 'BAN'),
            ...$this->papers('Bangla', 'বাংলা', 'BAN'),
            ...$this->single('English', 'ইংরেজি', 'ENG'),
            ...$this->papers('English', 'ইংরেজি', 'ENG'),
            ...$this->single('Mathematics', 'গণিত', 'MATH'),
            ...$this->single('General Science', 'সাধারণ বিজ্ঞান', 'GSC'),
            ...$this->single('Bangladesh and Global Studies', 'বাংলাদেশ ও বিশ্বপরিচয়', 'BGS'),
            ...$this->single('Information and Communication Technology', 'তথ্য ও যোগাযোগ প্রযুক্তি', 'ICT'),
            ...$this->single('Religion and Moral Education', 'ধর্ম ও নৈতিক শিক্ষা', 'RME'),
            ...$this->single('Physical Education, Health and Sports', 'শারীরিক শিক্ষা, স্বাস্থ্যবিজ্ঞান ও খেলাধুলা', 'PEHS'),
            ...$this->single('Career Education', 'ক্যারিয়ার শিক্ষা', 'CE'),
            ...$this->single('Agriculture Studies', 'কৃষিশিক্ষা', 'AGS'),
            ...$this->single('Arts and Crafts', 'চারু ও কারুকলা', 'AC'),

            // Science group
            ...$this->papers('Physics', 'পদার্থবিজ্ঞান', 'PHY'),
            ...$this->papers('Chemistry', 'রসায়ন', 'CHEM'),
            ...$this->papers('Biology', 'জীববিজ্ঞান', 'BIO'),
            ...$this->papers('Higher Mathematics', 'উচ্চতর গণিত', 'HM'),

            // Business Studies (Commerce) group
            ...$this->papers('Accounting', 'হিসাববিজ্ঞান', 'ACC'),
            ...$this->papers('Business Organization and Management', 'ব্যবসায় সংগঠন ও ব্যবস্থাপনা', 'BOM'),
            ...$this->papers('Finance, Banking and Insurance', 'ফিন্যান্স, ব্যাংকিং ও বিমা', 'FBI'),
            ...$this->single('Business Entrepreneurship', 'ব্যবসায় উদ্যোগ', 'BE'),

            // Humanities (Arts) group
            ...$this->papers('History of Bangladesh and World Civilization', 'বাংলাদেশের ইতিহাস ও বিশ্বসভ্যতা', 'HIST'),
            ...$this->papers('Civics and Good Governance', 'পৌরনীতি ও সুশাসন', 'CGG'),
            ...$this->papers('Economics', 'অর্থনীতি', 'ECO'),
            ...$this->papers('Geography and Environment', 'ভূগোল ও পরিবেশ', 'GEO'),
            ...$this->papers('Sociology', 'সমাজবিজ্ঞান', 'SOC'),
            ...$this->papers('Social Work', 'সমাজকর্ম', 'SW'),
            ...$this->papers('Islamic History and Culture', 'ইসলামের ইতিহাস ও সংস্কৃতি', 'IHC'),
            ...$this->papers('Logic', 'যুক্তিবিদ্যা', 'LOG'),
            ...$this->papers('Home Science', 'গার্হস্থ্য বিজ্ঞান', 'HS'),

            // Optional / 4th subject (cross-group)
            ...$this->papers('Statistics', 'পরিসংখ্যান', 'STAT'),
            ...$this->papers('Psychology', 'মনোবিজ্ঞান', 'PSY'),
            ...$this->papers('Agricultural Education', 'কৃষিশিক্ষা', 'AGE'),
            ...$this->papers('Food and Nutrition', 'খাদ্য ও পুষ্টি', 'FN'),
        ];
    }

    /**
     * @return array<int, array{name: string, name_bn: string, short_name: string}>
     */
    protected function single(string $name, string $nameBn, string $shortName): array
    {
        return [
            ['name' => $name, 'name_bn' => $nameBn, 'short_name' => $shortName],
        ];
    }

    /**
     * Expands a subject into its "1st Paper" and "2nd Paper" variants.
     *
     * @return array<int, array{name: string, name_bn: string, short_name: string}>
     */
    protected function papers(string $name, string $nameBn, string $shortName): array
    {
        return [
            ['name' => "{$name} 1st Paper", 'name_bn' => "{$nameBn} ১ম পত্র", 'short_name' => "{$shortName}-1"],
            ['name' => "{$name} 2nd Paper", 'name_bn' => "{$nameBn} ২য় পত্র", 'short_name' => "{$shortName}-2"],
        ];
    }
}
