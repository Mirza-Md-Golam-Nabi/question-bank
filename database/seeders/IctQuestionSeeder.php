<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only sample content — only runs when APP_ENV=local, so it never
 * touches a staging/production database even if called directly. Adds
 * approved MCQ questions to three chapters of "Class 11 / Class 12" ICT so
 * the question picker and exam flow have something to work with.
 *
 * Safe to run again: a question already in its chapter is left alone.
 */
class IctQuestionSeeder extends Seeder
{
    private const CLASS_NAME = 'Class 11 / Class 12';

    private const SUBJECT_NAME = 'Information and Communication Technology';

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $class = AcademicClass::where('name', self::CLASS_NAME)->first();
        $subject = Subject::where('name', self::SUBJECT_NAME)->first();
        $author = User::whereIn('role', [UserRole::SuperAdmin, UserRole::Admin])->orderBy('id')->first();

        if (! $class || ! $subject || ! $author) {
            $this->command?->warn('IctQuestionSeeder skipped: the class, the subject or an admin user is missing — run the other seeders first.');

            return;
        }

        $classSubject = ClassSubject::firstOrCreate(
            ['academic_class_id' => $class->id, 'subject_id' => $subject->id],
            ['order_index' => (ClassSubject::where('academic_class_id', $class->id)->max('order_index') ?? 0) + 1],
        );

        foreach ($this->chapters() as $chapterData) {
            $chapter = Chapter::firstOrCreate(
                ['class_subject_id' => $classSubject->id, 'order_index' => $chapterData['order_index']],
                ['name' => $chapterData['name']],
            );

            foreach ($chapterData['questions'] as [$text, $options, $correctIndex]) {
                $this->seedQuestion($chapter, $author, $text, $options, $correctIndex);
            }
        }
    }

    /**
     * @param  array<int, string>  $options
     */
    private function seedQuestion(Chapter $chapter, User $author, string $text, array $options, int $correctIndex): void
    {
        // Stored as HTML, so plain text such as "<h1>" has to be escaped to
        // show up as written instead of being read as a tag.
        $questionText = $this->paragraph($text);

        if (Question::where('chapter_id', $chapter->id)->where('question_text', $questionText)->exists()) {
            return;
        }

        Question::create([
            'chapter_id' => $chapter->id,
            'question_type' => QuestionType::Mcq,
            'question_text' => $questionText,
            'options' => collect($options)
                ->map(fn (string $option, int $index): array => [
                    'option' => $this->paragraph($option),
                    'image' => null,
                    'is_correct' => $index === $correctIndex,
                ])
                ->all(),
            'marks' => 1,
            'difficulty' => Difficulty::Easy,
            'status' => QuestionStatus::Approved,
            'created_by' => $author->id,
            'approved_by' => $author->id,
        ]);
    }

    private function paragraph(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }

    /**
     * Each question is [text, the four options (ক, খ, গ, ঘ), index of the
     * correct option].
     *
     * @return array<int, array{order_index: int, name: string, questions: array<int, array{0: string, 1: array<int, string>, 2: int}>}>
     */
    private function chapters(): array
    {
        return [
            [
                'order_index' => 3,
                'name' => 'সংখ্যা পদ্ধতি ও ডিজিটাল ডিভাইস',
                'questions' => [
                    ['যেকোনো কিছুর গণনা বা হিসাব প্রকাশের জন্য কী প্রয়োজন?', ['সংখ্যা', 'কোড', 'গেইট', 'সিগন্যাল'], 0],
                    ['সংখ্যা প্রকাশের প্রতীককে কী বলে?', ['বিট', 'অঙ্ক', 'কোড', 'বেস'], 1],
                    ['সভ্যতার ক্রমবিকাশে সংখ্যার কোনটি পরিবর্তিত হয়েছে?', ['শুধু নাম', 'শুধু ধরন', 'নাম ও ধরন', 'কোনোটিই নয়'], 2],
                    ['সভ্যতার শুরুতে হিসাব-নিকাশে কোনটি ব্যবহৃত হতো না?', ['দাগ কাটা', 'নুড়িপাথর', 'রশিতে গিঁট', 'ক্যালকুলেটর'], 3],
                    ['গণনার ক্ষেত্রে সর্বপ্রথম কী যুক্ত হয়?', ['শূন্য', 'প্রতীক বা চিহ্ন', 'দশমিক বিন্দু', 'বাইনারি অঙ্ক'], 1],
                    ['হায়ারোগ্লিফিক্স কোন সময়ে প্রচলিত ছিল?', ['খ্রিষ্টপূর্ব ৩৪০০ সালে', 'খ্রিষ্টপূর্ব ১৪০০ সালে', '৩৪০০ খ্রিষ্টাব্দে', 'খ্রিষ্টপূর্ব ৫০০ সালে'], 0],
                    ['\'হায়ারোগ্লিফিক্স\' শব্দটি কোন ভাষা থেকে এসেছে?', ['ল্যাটিন', 'আরবি', 'গ্রিক', 'মিশরীয়'], 2],
                    ['\'হায়ারোগ্লিফোস\' শব্দের অর্থ কী?', ['প্রাচীন সংখ্যা', 'পবিত্র লিপি', 'চিত্রলিপি', 'রাজকীয় লেখা'], 1],
                    ['মিশরীয় সংখ্যা ব্যবস্থা কত ভিত্তিক ছিল?', ['2', '5', '10', '20'], 2],
                ],
            ],
            [
                'order_index' => 4,
                'name' => 'ওয়েব ডিজাইন পরিচিতি ও HTML',
                'questions' => [
                    ['HTML এর পূর্ণরূপ কী?', ['Hyper Text Markup Language', 'High Text Machine Language', 'Hyper Tool Markup Language', 'Home Text Markup Language'], 0],
                    ['সবচেয়ে বড় heading কোন ট্যাগ দিয়ে লেখা হয়?', ['<h6>', '<head>', '<h1>', '<heading>'], 2],
                    ['প্যারাগ্রাফ লেখার ট্যাগ কোনটি?', ['<para>', '<p>', '<pg>', '<text>'], 1],
                    ['লিংক তৈরি করতে কোন ট্যাগ ব্যবহৃত হয়?', ['<link>', '<a>', '<href>', '<url>'], 1],
                    ['<a> ট্যাগে লিংকের ঠিকানা কোন attribute-এ দেওয়া হয়?', ['src', 'link', 'href', 'alt'], 2],
                    ['ওয়েবপেজে ছবি দেখাতে কোন ট্যাগ লাগে?', ['<image>', '<pic>', '<img>', '<src>'], 2],
                    ['লাইন ব্রেক দিতে কোন ট্যাগ ব্যবহৃত হয়?', ['<lb>', '<br>', '<break>', '<hr>'], 1],
                    ['বুলেট দেওয়া (unordered) লিস্ট তৈরির ট্যাগ কোনটি?', ['<ol>', '<li>', '<ul>', '<list>'], 2],
                    ['টেবিলে একটি row তৈরি করতে কোন ট্যাগ লাগে?', ['<td>', '<th>', '<tr>', '<row>'], 2],
                    ['ব্রাউজারের ট্যাবে পেজের নাম দেখাতে কোন ট্যাগ ব্যবহৃত হয়?', ['<head>', '<title>', '<meta>', '<h1>'], 1],
                ],
            ],
            [
                'order_index' => 5,
                'name' => 'প্রোগ্রামিং ভাষা',
                'questions' => [
                    ['C প্রোগ্রামিং ভাষার জনক কে?', ['জেমস গসলিং', 'ডেনিস রিচি', 'বিয়ারনে স্ট্রুস্ট্রাপ', 'গুইডো ভ্যান রসাম'], 1],
                    ['প্রতিটি C প্রোগ্রাম কোন ফাংশন থেকে চলা শুরু করে?', ['start()', 'printf()', 'main()', 'begin()'], 2],
                    ['printf() ব্যবহার করতে কোন header file লাগে?', ['conio.h', 'math.h', 'stdio.h', 'string.h'], 2],
                    ['C-তে প্রতিটি স্টেটমেন্টের শেষে কী দিতে হয়?', [':', '.', ';', ','], 2],
                    ['integer মান প্রিন্ট করার format specifier কোনটি?', ['%f', '%c', '%d', '%s'], 2],
                    ['কোনটি বৈধ variable নাম?', ['2num', 'my-var', 'int', '_total'], 3],
                    ['ব্যবহারকারীর কাছ থেকে ইনপুট নিতে কোন ফাংশন ব্যবহৃত হয়?', ['printf()', 'scanf()', 'input()', 'get()'], 1],
                    ['int x = 5 / 2; হলে x এর মান কত?', ['2.5', '2', '3', '0'], 1],
                    ['10 % 3 এর মান কত?', ['3', '0', '1', '3.33'], 2],
                    ['নিচের কোনটি C-তে loop নয়?', ['for', 'while', 'do-while', 'switch'], 3],
                ],
            ],
        ];
    }
}
