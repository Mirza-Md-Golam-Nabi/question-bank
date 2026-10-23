<?php

namespace Database\Seeders;

use App\Enums\GuideCategory;
use App\Enums\GuidePage;
use App\Models\Guide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The first how-to guides (CLAUDE.md rule 14), in Bangla and English, for
 * every role — drafts an Admin then edits on the "Guides" page. Each is
 * created once by its slug: seeding again neither doubles nor overwrites
 * one. A guide's picture, when `guide-images/{slug}.png` is there, is
 * copied to the public disk and shown above its steps.
 */
class GuideSeeder extends Seeder
{
    private const IMAGES = __DIR__.'/guide-images';

    public function run(): void
    {
        foreach ($this->guides() as $order => $guide) {
            if (Guide::query()->where('slug', $guide['slug'])->exists()) {
                continue;
            }

            $picture = $this->picture($guide['slug']);

            Guide::create([
                'slug' => $guide['slug'],
                'category' => $guide['category'],
                'target_roles' => $guide['roles'],
                'page_keys' => array_map(fn (GuidePage $page): string => $page->value, $guide['pages'] ?? []),
                'sort_order' => $order,
                'is_active' => true,
                'title_bn' => $guide['title_bn'],
                'title_en' => $guide['title_en'],
                'body_bn' => $picture.$guide['body_bn'],
                'body_en' => $picture.$guide['body_en'],
            ]);
        }
    }

    private function picture(string $slug): string
    {
        $source = self::IMAGES."/{$slug}.png";

        if (! File::exists($source)) {
            return '';
        }

        Storage::disk('public')->put("guides/{$slug}.png", File::get($source));

        return "<p><img src=\"/storage/guides/{$slug}.png\" alt=\"\"></p>";
    }

    /**
     * @return array<int, array{slug: string, category: GuideCategory, roles: array<int, string>, pages?: array<int, GuidePage>, title_bn: string, title_en: string, body_bn: string, body_en: string}>
     */
    private function guides(): array
    {
        return [
            // ── Getting started ────────────────────────────────────────
            [
                'slug' => 'student-getting-started',
                'category' => GuideCategory::GettingStarted,
                'roles' => ['student'],
                'title_bn' => 'শুরু করবেন কীভাবে',
                'title_en' => 'How to get started',
                'body_bn' => <<<'HTML'
                    <p>স্বাগতম! প্রথম দিনে এই কয়টা কাজ করে নিন:</p>
                    <ol>
                        <li><strong>প্রোফাইলে শ্রেণি ঠিক করুন।</strong> ওপরে ডানে নিজের ছবিতে চেপে প্রোফাইল খুলুন, নিজের শ্রেণি বেছে সেভ করুন। প্র্যাকটিস পরীক্ষা সবসময় এই শ্রেণি থেকেই হয়।</li>
                        <li><strong>প্রথম প্র্যাকটিস পরীক্ষা দিন।</strong> মেনু থেকে "অটো-জেনারেট প্র্যাকটিস পরীক্ষা" খুলে বিষয় ও প্রশ্নের সংখ্যা বেছে "পরীক্ষা জেনারেট করুন" চাপুন।</li>
                        <li><strong>একটা plan বেছে নিন।</strong> "আমার সাবস্ক্রিপশন" থেকে দেখুন কোন plan-এ কী আছে।</li>
                    </ol>
                    <p>ড্যাশবোর্ডের "শুরু করুন" তালিকায় এই ধাপগুলো নিজে থেকেই টিক পড়বে। কোথাও আটকে গেলে পেজের ওপরের "কীভাবে করবেন?" বাটন বা মেনুর "সাহায্য" দেখুন।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <p>Welcome! On your first day, do these few things:</p>
                    <ol>
                        <li><strong>Set your class on your profile.</strong> Press your picture at the top right, open your profile, choose your class and save. Practice exams always come from this class.</li>
                        <li><strong>Take your first practice exam.</strong> Open "Auto-Generate Practice Exam" from the menu, choose a subject and the number of questions, and press "Generate exam".</li>
                        <li><strong>Choose a plan.</strong> See what each plan gives on "My subscription".</li>
                    </ol>
                    <p>The "Get started" list on your dashboard ticks these off by itself. Stuck anywhere? Use the "How do I…?" button at the top of a page, or "Help" in the menu.</p>
                    HTML,
            ],
            [
                'slug' => 'teacher-getting-started',
                'category' => GuideCategory::GettingStarted,
                'roles' => ['teacher'],
                'title_bn' => 'শিক্ষক হিসেবে শুরু করবেন কীভাবে',
                'title_en' => 'Getting started as a teacher',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li><strong>ফ্রি ট্রায়াল শুরু করুন বা plan কিনুন</strong> — "আমার সাবস্ক্রিপশন" থেকে। plan ছাড়া পরীক্ষা বানানো যায় না।</li>
                        <li><strong>প্রশ্ন লিখুন</strong> — "আমার প্রশ্ন" থেকে শ্রেণি → বিষয় → অধ্যায় বেছে। নিজের প্রশ্ন Admin approve করার আগেই নিজের পরীক্ষায় দেওয়া যায়।</li>
                        <li><strong>পরীক্ষা বানান</strong> — "প্রশ্ন তৈরি করুন" পেজে প্রশ্নভাণ্ডার থেকে প্রশ্ন টিক দিয়ে বাছুন, তারপর "পরীক্ষা হিসেবে সেভ করুন"।</li>
                        <li><strong>লিংক শেয়ার করুন</strong> — "পরীক্ষা" তালিকা থেকে "শেয়ার লিংক কপি করুন" চেপে ছাত্রদের পাঠান।</li>
                    </ol>
                    <p>ড্যাশবোর্ডের "শুরু করুন" তালিকায় কাজগুলো নিজে থেকেই টিক পড়বে।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li><strong>Start your free trial or buy a plan</strong> on "My subscription". You need a plan to build exams.</li>
                        <li><strong>Write questions</strong> on "My questions": choose the class → subject → chapter. Your own questions can go on your own exams even before an Admin approves them.</li>
                        <li><strong>Build an exam</strong> on "Select questions": tick questions from the question bank, then "Save as exam".</li>
                        <li><strong>Share the link</strong>: on "Exams", press "Copy share link" and send it to your students.</li>
                    </ol>
                    <p>The "Get started" list on your dashboard ticks these off by itself.</p>
                    HTML,
            ],
            [
                'slug' => 'staff-getting-started',
                'category' => GuideCategory::GettingStarted,
                'roles' => ['staff'],
                'title_bn' => 'Staff হিসেবে শুরু: অনুমোদন ও প্রথম কাজ',
                'title_en' => 'Starting as Staff: approval and first steps',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li><strong>অনুমোদনের অপেক্ষা।</strong> Google দিয়ে প্রথম লগইনের পর একাউন্ট "অনুমোদনের অপেক্ষায়" থাকে। Admin approve করলে পুরো প্যানেল খুলে যায়।</li>
                        <li><strong>টাকা পাওয়ার তথ্য দিন।</strong> প্রোফাইলে bKash/Nagad নম্বর বা ব্যাংকের তথ্য দিন — এগুলো গোপন (encrypted) রাখা হয়।</li>
                        <li><strong>প্রশ্ন লিখুন।</strong> "আমার প্রশ্ন" থেকে শ্রেণি → বিষয় → অধ্যায় বেছে প্রশ্ন যোগ করুন।</li>
                        <li><strong>approve হলেই আয়।</strong> প্রতিটা approved প্রশ্নের টাকা সাথে সাথে আপনার আয়ে যোগ হয়।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li><strong>Wait for approval.</strong> After your first Google sign-in the account waits for an Admin. Once approved, the whole panel opens.</li>
                        <li><strong>Add how you want to be paid.</strong> Give your bKash/Nagad number or bank details on your profile — they are stored encrypted.</li>
                        <li><strong>Write questions</strong> on "My questions": choose the class → subject → chapter.</li>
                        <li><strong>Earn on approval.</strong> Every question an Admin approves is added to your earnings at once.</li>
                    </ol>
                    HTML,
            ],
            [
                'slug' => 'school-getting-started',
                'category' => GuideCategory::GettingStarted,
                'roles' => ['school'],
                'title_bn' => 'School একাউন্ট দিয়ে শুরু',
                'title_en' => 'Getting started with the School account',
                'body_bn' => <<<'HTML'
                    <p>প্রতিটা স্কুলের একটাই School একাউন্ট, যেটা স্কুলের কম্পিউটার অপারেটর চালান।</p>
                    <ol>
                        <li><strong>স্কুলের তথ্য দিন ও অনুমোদনের অপেক্ষা করুন।</strong> নাম, EIIN, ঠিকানা ও ফোন দিন; Admin approve করলে প্যানেল খোলে।</li>
                        <li><strong>স্কুলের সফটওয়্যার যুক্ত করুন</strong> — "স্কুল সফটওয়্যারের সংযোগ" পেজ দেখুন।</li>
                        <li><strong>প্রশ্ন লিখুন ও পরীক্ষা বানান</strong> — Teacher-এর মতোই "আমার প্রশ্ন" ও "প্রশ্ন তৈরি করুন" দিয়ে।</li>
                        <li><strong>নম্বর পাঠান</strong> — অনলাইন পরীক্ষার পর "স্কুলের রেজাল্টে পাঠান"।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <p>Each school has one School account, run by the school's computer operator.</p>
                    <ol>
                        <li><strong>Give the school's details and wait for approval.</strong> Name, EIIN, address and phone; the panel opens once an Admin approves.</li>
                        <li><strong>Connect the school software</strong> — see the "School software connection" page.</li>
                        <li><strong>Write questions and build exams</strong> — with "My questions" and "Select questions", as a teacher does.</li>
                        <li><strong>Send the marks</strong> — after an online exam, "Send to school results".</li>
                    </ol>
                    HTML,
            ],

            // ── Plans and payment ───────────────────────────────────────
            [
                'slug' => 'buy-a-plan',
                'category' => GuideCategory::Subscription,
                'roles' => ['student', 'teacher', 'school'],
                'pages' => [GuidePage::Subscription],
                'title_bn' => 'plan কিনবেন কীভাবে (bKash / Nagad / Rocket)',
                'title_en' => 'How to buy a plan (bKash / Nagad / Rocket)',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনু থেকে <strong>"আমার সাবস্ক্রিপশন"</strong> খুলুন। প্রতিটা plan-এ কী আছে (শ্রেণি, বিষয়, দিনে/মাসে কয়টা পরীক্ষা, মেয়াদ, দাম) সেখানে লেখা থাকে।</li>
                        <li>পছন্দের plan-এর <strong>"কিনুন"</strong> বাটন চাপুন। plan-এ বাছার সুযোগ থাকলে শ্রেণি ও বিষয় বেছে নিন।</li>
                        <li>জানালায় দেখানো <strong>bKash / Nagad / Rocket নম্বরে</strong> দাম পাঠিয়ে দিন (Send Money)।</li>
                        <li>জানালায় লিখুন: <strong>"যে মাধ্যমে পাঠানো"</strong>, <strong>"যে নম্বর থেকে টাকা পাঠিয়েছেন"</strong> আর পাঠানোর পর পাওয়া <strong>"ট্রানজ্যাকশন আইডি"</strong>। তারপর <strong>"জমা দিন"</strong>।</li>
                        <li>Admin টাকা মিলিয়ে approve করলেই plan চালু হয়, বেলে নোটিফিকেশন আসে।</li>
                    </ol>
                    <p><strong>Wallet-এ কমিশন থাকলে</strong> "আমার wallet-এর টাকা ব্যবহার করুন" টিক দিয়ে দামের কিছু বা পুরোটা সেখান থেকে দেওয়া যায় (ফোনে আসা কোড লাগতে পারে)।</p>
                    <p>কিছুই ব্যবহার না করলে Admin-এর ঠিক করা সময়ের মধ্যে পুরো টাকা ফেরত চাওয়া যায়।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"My subscription"</strong> from the menu. Each plan shows what it gives (classes, subjects, exams a day/month, how long, price).</li>
                        <li>Press <strong>"Buy"</strong> on the plan you want. If the plan lets you choose, pick the class and subjects.</li>
                        <li>Send the price to the <strong>bKash / Nagad / Rocket number</strong> shown in the window (Send Money).</li>
                        <li>In the window, fill in <strong>"Sent with"</strong>, <strong>"Number you sent the payment from"</strong> and the <strong>"Transaction ID"</strong> you got, then press <strong>"Submit"</strong>.</li>
                        <li>As soon as an Admin checks and approves the payment, the plan starts and your bell tells you.</li>
                    </ol>
                    <p><strong>Have commission in your wallet?</strong> Tick "Use my wallet credit" to pay part or all of the price from it (a code sent to your phone may be needed).</p>
                    <p>If you use nothing from a plan, you can ask for the whole amount back within the time the Admin sets.</p>
                    HTML,
            ],
            [
                'slug' => 'teacher-free-trial',
                'category' => GuideCategory::Subscription,
                'roles' => ['teacher'],
                'pages' => [GuidePage::Subscription],
                'title_bn' => 'ফ্রি ট্রায়াল',
                'title_en' => 'The free trial',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>"আমার সাবস্ক্রিপশন"-এ ট্রায়াল plan-এর <strong>"ফ্রি ট্রায়াল শুরু করুন"</strong> চাপুন — টাকা লাগে না, সাথে সাথে চালু।</li>
                        <li>ট্রায়াল একাউন্টপ্রতি <strong>একবারই</strong> নেওয়া যায়।</li>
                        <li>মেয়াদ শেষ হলে বানানো পরীক্ষা থেকে যায়; নতুন পরীক্ষা বানাতে plan কিনতে হয়।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>On "My subscription", press <strong>"Start free trial"</strong> on the trial plan — no payment, it starts at once.</li>
                        <li>A trial can be taken <strong>once</strong> per account.</li>
                        <li>When it ends, the exams you built stay; to build new ones, buy a plan.</li>
                    </ol>
                    HTML,
            ],
            [
                'slug' => 'group-plan-members',
                'category' => GuideCategory::Subscription,
                'roles' => ['teacher', 'student'],
                'pages' => [GuidePage::Subscription],
                'title_bn' => 'Group plan-এ অন্যদের যোগ করা',
                'title_en' => 'Adding people to a group plan',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>যে plan-এ "আসন" আছে, কেনার পর "আমার সাবস্ক্রিপশন"-এ সেই plan-এ <strong>"কাউকে যোগ করুন"</strong> বাটন আসে।</li>
                        <li>যাকে দেবেন তার <strong>Gmail ঠিকানা</strong> লিখুন। তিনি নিজের Google একাউন্টে লগইন করলেই plan পেয়ে যান।</li>
                        <li>প্রত্যেকের দিনের/মাসের পরীক্ষার হিসাব আলাদা।</li>
                        <li>কাউকে সরানো যায় Admin-এর ঠিক করা দিন পরপর একবার। আপনার plan শেষ হলে সবারটাও শেষ।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Once you buy a plan with seats, "My subscription" shows <strong>"Add someone"</strong> on it.</li>
                        <li>Enter their <strong>Gmail address</strong>. They get the plan as soon as they sign in with their own Google account.</li>
                        <li>Everyone's exams a day/month are counted separately.</li>
                        <li>You can take someone off once every few days (set by the Admin). When your plan ends, theirs ends too.</li>
                    </ol>
                    HTML,
            ],

            // ── Writing questions ───────────────────────────────────────
            [
                'slug' => 'write-a-question',
                'category' => GuideCategory::Questions,
                'roles' => ['teacher', 'staff', 'school'],
                'pages' => [GuidePage::WriteQuestions],
                'title_bn' => 'প্রশ্ন লিখবেন কীভাবে (MCQ ও সৃজনশীল)',
                'title_en' => 'How to write a question (MCQ and creative)',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনু থেকে <strong>"আমার প্রশ্ন"</strong> খুলুন, তারপর <strong>শ্রেণি → বিষয় → অধ্যায়</strong> কার্ডে চাপুন।</li>
                        <li>অধ্যায়ের তালিকায় ওপরে <strong>নতুন প্রশ্নের</strong> বাটন চাপুন।</li>
                        <li><strong>প্রশ্নের ধরন</strong> বাছুন: MCQ অথবা CQ (সৃজনশীল)।</li>
                        <li><strong>প্রশ্ন লিখুন।</strong> গণিতের সূত্র এডিটরের সূত্রের বাটন দিয়ে বসান; ছবি থাকলে "ছবি" ঘরে দিন। নিচে preview-তে দেখে নিন কেমন দেখাবে।</li>
                        <li><strong>MCQ হলে</strong> অপশনগুলো লিখে ঠিক একটায় "সঠিক" টিক দিন; চাইলে ব্যাখ্যা লিখুন (ছাত্ররা শুধু উত্তর দেখানোর সময় দেখে)।</li>
                        <li><strong>CQ হলে</strong> উদ্দীপক লিখে চারটা অংশ (জ্ঞান, অনুধাবন, প্রয়োগ, উচ্চতর দক্ষতা) ও তাদের নম্বর দিন — মোট নম্বর নিজে থেকেই যোগ হয়।</li>
                        <li><strong>সেভ করুন।</strong> একই রকম প্রশ্ন আগে থেকে থাকলে সতর্কবার্তা আসে; তবুও রাখতে চাইলে "তবুও সেভ করুন"।</li>
                    </ol>
                    <p><strong>Teacher ও Staff:</strong> প্রশ্ন Admin approve করার আগ পর্যন্ত শুধু আপনি দেখেন। approved প্রশ্ন এডিট করলে নতুন সংস্করণ হয়, আবার approve লাগে।</p>
                    <p><strong>School:</strong> স্কুলের প্রশ্ন শুধু স্কুলের — কেউ approve করে না, অন্য কেউ দেখে না।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"My questions"</strong> from the menu and press the <strong>class → subject → chapter</strong> cards.</li>
                        <li>On the chapter's list, press the <strong>new question</strong> button at the top.</li>
                        <li>Choose the <strong>question type</strong>: MCQ or CQ (creative).</li>
                        <li><strong>Write the question.</strong> Put formulas in with the editor's formula button; add a picture in the "image" field. Check the preview below.</li>
                        <li><strong>For an MCQ</strong>, write the options and tick exactly one as correct; add an explanation if you like (students see it only where answers are shown).</li>
                        <li><strong>For a CQ</strong>, write the stimulus and the four parts (knowledge, comprehension, application, higher ability) with their marks — the total adds itself up.</li>
                        <li><strong>Save.</strong> If a very similar question is already there, you are warned; to keep it anyway, "Save anyway".</li>
                    </ol>
                    <p><strong>Teachers and Staff:</strong> until an Admin approves a question only you see it. Editing an approved question makes a new version that needs approving again.</p>
                    <p><strong>School:</strong> the school's questions are its own — nobody approves them and nobody else sees them.</p>
                    HTML,
            ],
            [
                'slug' => 'stimulus-mcqs',
                'category' => GuideCategory::Questions,
                'roles' => ['teacher', 'staff', 'school'],
                'pages' => [GuidePage::WriteQuestions],
                'title_bn' => 'উদ্দীপকসহ MCQ (এক উদ্দীপকে কয়েকটা প্রশ্ন)',
                'title_en' => 'MCQs on a shared stimulus',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>অধ্যায়ের প্রশ্নের তালিকায় <strong>"উদ্দীপকসহ MCQ"</strong> বাটন চাপুন।</li>
                        <li>ওপরে উদ্দীপক (লেখা বা ছবি) দিন, নিচে ২ থেকে ৬টা MCQ যোগ করুন।</li>
                        <li>প্রতিটা MCQ আলাদা প্রশ্ন হিসেবে গোনা হয়; পরীক্ষায় এগুলো সবসময় পাশাপাশি বসে, আর "নিচের উদ্দীপকটি পড়ে … নম্বর প্রশ্নের উত্তর দাও" নিজে থেকেই লেখা হয়।</li>
                        <li>গ্রুপটা একসাথে approve/reject হয়; এডিটও একসাথে।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>On a chapter's question list, press <strong>"New stimulus with MCQs"</strong>.</li>
                        <li>Give the stimulus (text or picture) at the top and add 2 to 6 MCQs below it.</li>
                        <li>Each MCQ counts as its own question; on an exam they always sit together, and "Read the stimulus below and answer questions …" is written by itself.</li>
                        <li>The group is approved or rejected together, and edited together.</li>
                    </ol>
                    HTML,
            ],
            [
                'slug' => 'json-import',
                'category' => GuideCategory::Questions,
                'roles' => ['teacher', 'school'],
                'pages' => [GuidePage::WriteQuestions],
                'title_bn' => 'JSON দিয়ে একসাথে অনেক প্রশ্ন যোগ করা',
                'title_en' => 'Adding many questions at once with JSON',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>অধ্যায়ের প্রশ্নের তালিকায় <strong>"JSON দিয়ে যোগ করুন"</strong> চাপুন।</li>
                        <li>জানালায় নমুনা (sample) দেখুন ও কপি করুন — প্রশ্ন, অপশন, সঠিক উত্তর, ব্যাখ্যা কীভাবে লিখতে হয় সেখানে আছে। গণিতের সূত্র <code>$...$</code>-এর ভেতরে লিখুন।</li>
                        <li>নিজের প্রশ্নগুলো একই ধাঁচে লিখে পেস্ট করুন; ছবি থাকলে ছবির ফাইল বা ZIP দিন।</li>
                        <li>যাচাইয়ের পর তালিকা দেখায়: কোনটা ঠিক, কোনটায় ভুল, কোনটা সম্ভাব্য ডুপ্লিকেট। যেগুলো রাখবেন টিক দিয়ে সেভ করুন।</li>
                    </ol>
                    <p>একবারে কয়টা প্রশ্ন দেওয়া যায় তা Admin ঠিক করেন।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>On a chapter's question list, press <strong>"Add from JSON"</strong>.</li>
                        <li>See and copy the sample in the window — it shows how to write the question, options, correct answer and explanation. Write formulas inside <code>$...$</code>.</li>
                        <li>Write your questions the same way and paste them; give the picture files or a ZIP if there are pictures.</li>
                        <li>After checking, the list shows which are fine, which have mistakes and which may be duplicates. Tick the ones to keep and save.</li>
                    </ol>
                    <p>How many questions fit in one go is set by the Admin.</p>
                    HTML,
            ],
            [
                'slug' => 'staff-rejected-question',
                'category' => GuideCategory::Questions,
                'roles' => ['staff', 'teacher'],
                'pages' => [GuidePage::WriteQuestions],
                'title_bn' => 'প্রশ্ন reject হলে কী করবেন',
                'title_en' => 'When a question is rejected',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>"আমার প্রশ্ন"-এর তালিকায় প্রশ্নের অবস্থা দেখুন: অপেক্ষমাণ, approved বা rejected।</li>
                        <li>Rejected প্রশ্নের সাথে Admin-এর লেখা <strong>কারণ</strong> থাকে।</li>
                        <li>প্রশ্নটা খুলে কারণ অনুযায়ী ঠিক করে সেভ করুন — আবার রিভিউতে যায়।</li>
                        <li>Staff-এর টাকা শুধু approved প্রশ্নে; rejected প্রশ্নে কিছু যোগ হয় না।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>See each question's status on "My questions": pending, approved or rejected.</li>
                        <li>A rejected question carries the Admin's <strong>reason</strong>.</li>
                        <li>Open it, fix it as the reason says and save — it goes back for review.</li>
                        <li>Staff are paid only for approved questions; a rejected one adds nothing.</li>
                    </ol>
                    HTML,
            ],

            // ── Exams ───────────────────────────────────────────────────
            [
                'slug' => 'build-an-exam',
                'category' => GuideCategory::Exams,
                'roles' => ['teacher', 'school'],
                'pages' => [GuidePage::BuildExam],
                'title_bn' => 'পরীক্ষা বানাবেন কীভাবে',
                'title_en' => 'How to build an exam',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনু থেকে <strong>"প্রশ্ন তৈরি করুন"</strong> খুলুন।</li>
                        <li>আগে <strong>পরীক্ষার ধরন</strong> বাছুন: অনলাইন (শুধু MCQ, শেয়ার-লিংকে), প্রিন্ট (MCQ ও CQ), অথবা দুটোই।</li>
                        <li><strong>শ্রেণি ও বিষয়</strong> বাছুন, তারপর অধ্যায় (আর চাইলে টপিক)। এক পরীক্ষায় একই বিষয়ের যত খুশি অধ্যায় মেশানো যায়।</li>
                        <li>কয়টা MCQ / CQ লাগবে লিখুন, তারপর তালিকা থেকে <strong>টিক দিয়ে</strong> প্রশ্ন বাছুন। চাইলে এলোমেলোভাবে বাছার বাটনও আছে।</li>
                        <li>বাছাই শেষে <strong>ফাইনাল ভিউ</strong>-তে অধ্যায় ধরে সব দেখুন, অপ্রয়োজনীয়টা সরান।</li>
                        <li><strong>"পরীক্ষা হিসেবে সেভ করুন"</strong> চেপে শিরোনাম, সময় (মিনিট), চাইলে নেগেটিভ মার্কিং ও শেষ সময় দিন।</li>
                    </ol>
                    <p>পরীক্ষা সেভ করার সময় plan-এর সীমায় একবার গোনা হয়। কেউ পরীক্ষা শুরু করার পর প্রশ্ন বদলানো যায় না।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"Select questions"</strong> from the menu.</li>
                        <li>First choose <strong>how the exam is taken</strong>: online (MCQ only, by share link), print (MCQ and CQ), or both.</li>
                        <li>Choose the <strong>class and subject</strong>, then chapters (and topics, if you like). One exam can mix any chapters of the same subject.</li>
                        <li>Enter how many MCQs / CQs you need, then <strong>tick</strong> questions from the list. There is also a button to pick at random.</li>
                        <li>When done, check everything chapter by chapter in the <strong>final view</strong> and remove what you don't want.</li>
                        <li>Press <strong>"Save as exam"</strong> and give the title, duration (minutes), and, if you like, negative marking and an end time.</li>
                    </ol>
                    <p>An exam is counted against your plan once, when it is saved. After a student has started it, its questions can't change.</p>
                    HTML,
            ],
            [
                'slug' => 'share-an-exam',
                'category' => GuideCategory::Exams,
                'roles' => ['teacher', 'school'],
                'pages' => [GuidePage::MyExams],
                'title_bn' => 'পরীক্ষা শেয়ার, শেষ সময়, উত্তর প্রকাশ ও ফলাফল',
                'title_en' => 'Sharing an exam, its end time, releasing answers and results',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনু থেকে <strong>"পরীক্ষা"</strong> খুলুন।</li>
                        <li><strong>"শেয়ার লিংক কপি করুন"</strong> চেপে লিংক WhatsApp/Messenger-এ ছাত্রদের পাঠান। ছাত্র লগইন করে অথবা গেস্ট হিসেবে (নাম ও ফোন) পরীক্ষা দিতে পারে।</li>
                        <li><strong>"শেষ সময় ঠিক করুন"</strong> দিয়ে লিংক কখন বন্ধ হবে ঠিক করুন; যারা শুরু করেছে তারা শেষ করতে পারে।</li>
                        <li>জমা দেওয়ার পর ছাত্র শুধু নম্বর দেখে। সবাই শেষ করলে <strong>"উত্তর প্রকাশ করুন"</strong> চাপুন, অথবা নির্দিষ্ট সময়ে নিজে থেকে প্রকাশের সময় দিন।</li>
                        <li><strong>ফলাফল</strong> থেকে সবার নম্বর ও পজিশন দেখুন, প্রিন্টও করা যায়।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"Exams"</strong> from the menu.</li>
                        <li>Press <strong>"Copy share link"</strong> and send the link to your students on WhatsApp/Messenger. A student can sign in or join as a guest (name and phone).</li>
                        <li>With <strong>"Set end time"</strong>, choose when the link stops; those who have started can finish.</li>
                        <li>After handing in, a student sees only their score. Once everyone has finished, press <strong>"Release answers"</strong>, or set a time to release them by themselves.</li>
                        <li>See everyone's marks and positions on <strong>Results</strong>; you can print it too.</li>
                    </ol>
                    HTML,
            ],
            [
                'slug' => 'print-an-exam',
                'category' => GuideCategory::Exams,
                'roles' => ['teacher', 'school'],
                'pages' => [GuidePage::MyExams],
                'title_bn' => 'প্রশ্নপত্র প্রিন্ট বা PDF করা',
                'title_en' => 'Printing the paper or saving it as PDF',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>প্রিন্ট বা "দুটোই" ধরনের পরীক্ষায় তালিকা থেকে প্রিন্টের বাটন চাপুন।</li>
                        <li>"শুধু প্রশ্ন" অথবা "উত্তরসহ" কপি বাছুন।</li>
                        <li>ব্রাউজারের Print খুলবে; প্রিন্টার বাছুন, অথবা PDF চাইলে <strong>"Save as PDF"</strong> বাছুন।</li>
                    </ol>
                    <p>বাংলা যুক্তাক্ষর ও গণিতের সূত্র ঠিক রাখতে PDF ব্রাউজার থেকেই বানানো হয়।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>For a print or "both" exam, press the print button on the list.</li>
                        <li>Choose the "questions only" or the "with answers" copy.</li>
                        <li>The browser's Print opens: choose a printer, or <strong>"Save as PDF"</strong> for a PDF.</li>
                    </ol>
                    <p>The PDF is made by the browser so Bangla conjuncts and formulas come out right.</p>
                    HTML,
            ],
            [
                'slug' => 'take-an-exam-again',
                'category' => GuideCategory::Exams,
                'roles' => ['teacher', 'school'],
                'pages' => [GuidePage::MyExams],
                'title_bn' => 'একই প্রশ্নে আবার পরীক্ষা নেওয়া (অন্য ছাত্রদের জন্য)',
                'title_en' => 'Taking the same exam again (for other students)',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>"পরীক্ষা" তালিকায় পুরনো পরীক্ষার পাশে <strong>"আবার পরীক্ষা নিন"</strong> বাটন (কপির চিহ্ন) চাপুন।</li>
                        <li>নতুন পরীক্ষার নাম, সময় আর চাইলে শেষ সময় দিন, তারপর <strong>"নতুন পরীক্ষা তৈরি করুন"</strong>।</li>
                        <li>একই প্রশ্ন দিয়ে নতুন পরীক্ষা ড্রাফট হিসেবে তৈরি হয়, নিজের নতুন লিংকসহ। <strong>Publish</strong> করে নতুন ছাত্রদের লিংক পাঠান।</li>
                    </ol>
                    <p>পুরনো পরীক্ষা, তার লিংক, ফলাফল আর প্রকাশিত উত্তর যেমন ছিল তেমনই থাকে। নতুন পরীক্ষার উত্তর আলাদাভাবে প্রকাশ করতে হয়।</p>
                    <p>কোনো প্রশ্ন পরে সংশোধন হয়ে থাকলে নতুন পরীক্ষায় সংশোধিত রূপটা যায়; যেটা reject হয়েছে বা আবার approve-এর অপেক্ষায়, সেটা বাদ যায় (জানালায় লেখা থাকে কয়টা)। নতুন পরীক্ষাটা plan-এ নতুন পরীক্ষা হিসেবে গোনা হয়।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>On "Exams", press <strong>"Take again"</strong> (the copy icon) beside the old exam.</li>
                        <li>Give the new exam's title, duration and, if you like, an end time, then <strong>"Create the new exam"</strong>.</li>
                        <li>A new exam with the same questions is saved as a draft, with its own new link. <strong>Publish</strong> it and send the link to the new students.</li>
                    </ol>
                    <p>The old exam, its link, its results and its released answers stay as they were. The new exam's answers are released separately.</p>
                    <p>A question corrected since goes on as its corrected version; one rejected, or waiting to be approved again, is left out (the window says how many). The new exam counts as a new exam on your plan.</p>
                    HTML,
            ],
            [
                'slug' => 'take-a-shared-exam',
                'category' => GuideCategory::Exams,
                'roles' => ['student'],
                'title_bn' => 'শিক্ষকের পাঠানো লিংকে পরীক্ষা দেওয়া',
                'title_en' => 'Taking an exam from your teacher\'s link',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>শিক্ষকের পাঠানো <strong>লিংকে</strong> চাপুন।</li>
                        <li><strong>"লগইন করে পরীক্ষা দিন"</strong> চাপুন (ফলাফল আপনার একাউন্টে থাকবে), অথবা <strong>"গেস্ট হিসেবে চালিয়ে যান"</strong> — নাম ও ফোন/ইমেইল দিয়ে।</li>
                        <li>ওপরে সময় চলতে থাকে; উত্তর নিজে থেকে সেভ হয়। সময় শেষ হলে খাতা নিজে থেকেই জমা হয়।</li>
                        <li>শেষ হলে <strong>"পরীক্ষা জমা দিন"</strong> চাপুন।</li>
                        <li>প্রথমে শুধু নম্বর দেখায়; শিক্ষক উত্তর প্রকাশ করলে সঠিক উত্তর ও ব্যাখ্যা দেখা যায়।</li>
                    </ol>
                    <p><strong>গেস্ট হিসেবে দিলে</strong> পরে একই লিংকে গিয়ে একই নাম ও ফোন দিয়ে <strong>"আমার ফলাফল দেখুন"</strong> চাপলে ফলাফল পাবেন।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open the <strong>link</strong> your teacher sent.</li>
                        <li>Press <strong>"Login to attempt"</strong> (the result stays in your account), or <strong>"Continue as guest"</strong> with your name and phone/email.</li>
                        <li>The time runs at the top; your answers save by themselves. When time is up, the paper is handed in by itself.</li>
                        <li>When you finish, press <strong>"Submit exam"</strong>.</li>
                        <li>At first only your score shows; once the teacher releases the answers you can see the correct answers and explanations.</li>
                    </ol>
                    <p><strong>Sat it as a guest?</strong> Open the same link later and press <strong>"See my result"</strong> with the same name and phone.</p>
                    HTML,
            ],
            [
                'slug' => 'my-results',
                'category' => GuideCategory::Exams,
                'roles' => ['student'],
                'title_bn' => 'নিজের ফলাফল ও পারফরম্যান্স দেখা',
                'title_en' => 'Seeing your results and performance',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনুর <strong>"আমার ফলাফল"</strong>-এ আপনার দেওয়া সব পরীক্ষার নম্বর থাকে।</li>
                        <li>ড্যাশবোর্ডে দুর্বল অধ্যায় দেখায় — সেখান থেকে অনুশীলন করুন।</li>
                        <li>যে plan-এ "পারফরম্যান্স বিশ্লেষণ" আছে, তাতে ড্যাশবোর্ডে বিষয়, অধ্যায় ও কঠিনতা অনুযায়ী আপনার হার আর স্কোরের ধারা দেখায়।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li><strong>"My results"</strong> in the menu has the marks of every exam you sat.</li>
                        <li>Your dashboard shows your weak chapters — practise from there.</li>
                        <li>With a plan that includes "Performance analytics", the dashboard shows how you do by subject, chapter and difficulty, and your score trend.</li>
                    </ol>
                    HTML,
            ],

            // ── Practice ────────────────────────────────────────────────
            [
                'slug' => 'practice-auto',
                'category' => GuideCategory::Practice,
                'roles' => ['student'],
                'pages' => [GuidePage::Practice],
                'title_bn' => 'অটো-জেনারেট প্র্যাকটিস পরীক্ষা',
                'title_en' => 'Auto-generated practice exam',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনুর <strong>"অটো-জেনারেট প্র্যাকটিস পরীক্ষা"</strong> খুলুন।</li>
                        <li>বিষয় বাছুন; চাইলে অধ্যায় বাছুন (না বাছলে সব অধ্যায়), কঠিনতা আর প্রশ্নের সংখ্যা দিন।</li>
                        <li><strong>"পরীক্ষা জেনারেট করুন"</strong> চাপুন — সিস্টেম প্রশ্ন বেছে দেয়। সময় প্রশ্নের সংখ্যা থেকে ঠিক হয়।</li>
                        <li>জমা দিলেই সঠিক উত্তর ও ব্যাখ্যা দেখায়।</li>
                    </ol>
                    <p>দিনে/মাসে কয়টা প্র্যাকটিস পরীক্ষা দেওয়া যায় তা আপনার plan ঠিক করে।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"Auto-Generate Practice Exam"</strong> from the menu.</li>
                        <li>Choose a subject; choose chapters if you like (none means all), the difficulty and the number of questions.</li>
                        <li>Press <strong>"Generate exam"</strong> — the questions are picked for you. The time comes from the number of questions.</li>
                        <li>As soon as you hand it in, the correct answers and explanations show.</li>
                    </ol>
                    <p>How many practice exams a day/month you can take is set by your plan.</p>
                    HTML,
            ],
            [
                'slug' => 'practice-build',
                'category' => GuideCategory::Practice,
                'roles' => ['student'],
                'pages' => [GuidePage::Practice],
                'title_bn' => 'নিজের প্র্যাকটিস পরীক্ষা বানানো',
                'title_en' => 'Building your own practice exam',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনুর <strong>"নিজের প্র্যাকটিস পরীক্ষা বানান"</strong> খুলুন।</li>
                        <li>বিষয় → অধ্যায় → টপিক বেছে প্রশ্ন দেখুন, যেগুলো দিতে চান <strong>টিক দিন</strong> — একাধিক অধ্যায় থেকেও।</li>
                        <li>ফাইনাল ভিউ দেখে <strong>পরীক্ষা শুরু করুন</strong>।</li>
                    </ol>
                    <p>বাছার সময় সঠিক উত্তর দেখায় না; জমা দেওয়ার পর দেখায়।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"Build Your Own Practice Exam"</strong> from the menu.</li>
                        <li>Choose subject → chapter → topic, and <strong>tick</strong> the questions you want — from several chapters if you like.</li>
                        <li>Check the final view and <strong>start the exam</strong>.</li>
                    </ol>
                    <p>The correct answers don't show while you pick; they show after you hand it in.</p>
                    HTML,
            ],
            [
                'slug' => 'practice-mistakes',
                'category' => GuideCategory::Practice,
                'roles' => ['student'],
                'pages' => [GuidePage::Practice],
                'title_bn' => 'আমার ভুলগুলো অনুশীলন',
                'title_en' => 'Practising your mistakes',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>যেকোনো পরীক্ষায় ভুল বা ফাঁকা রাখা MCQ আপনার ভুলের তালিকায় জমে।</li>
                        <li>মেনুর <strong>"আমার ভুলগুলো অনুশীলন"</strong> থেকে সেগুলো দিয়ে পরীক্ষা বানান।</li>
                        <li>পরপর কয়েকবার ঠিক উত্তর দিলে প্রশ্নটা তালিকা থেকে চলে যায়।</li>
                    </ol>
                    <p>শিক্ষকের পরীক্ষার ভুল আসে শুধু শিক্ষক উত্তর প্রকাশ করার পরে।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Every MCQ you got wrong or left blank, on any exam, goes on your mistakes list.</li>
                        <li>Build an exam from them on <strong>"Practise my mistakes"</strong>.</li>
                        <li>Answer one right a few times in a row and it leaves the list.</li>
                    </ol>
                    <p>Mistakes from a teacher's exam come in only after the teacher releases its answers.</p>
                    HTML,
            ],
            [
                'slug' => 'past-papers',
                'category' => GuideCategory::Practice,
                'roles' => ['student', 'teacher'],
                'pages' => [GuidePage::PastPapers],
                'title_bn' => 'বিগত সালের প্রশ্নপত্র',
                'title_en' => 'Past papers',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনুর <strong>"বিগত সালের প্রশ্ন"</strong> খুলুন — শিক্ষা বোর্ড, ভর্তি পরীক্ষা, চাকরির পরীক্ষা ইত্যাদির পুরো প্রশ্নপত্র।</li>
                        <li>ধরন, উৎস ও সাল বেছে প্রশ্নপত্র খুঁজুন।</li>
                        <li><strong>ছাত্র:</strong> পুরো প্রশ্নপত্রটা পরীক্ষা হিসেবে দিন; জমা দিলেই উত্তর ও ব্যাখ্যা।</li>
                        <li><strong>শিক্ষক:</strong> পুরো প্রশ্নপত্রটা নিজের পরীক্ষা হিসেবে নিন — অনলাইনে MCQ, প্রিন্টে MCQ ও CQ।</li>
                    </ol>
                    <p>কোন প্রশ্নপত্র, কয়টা পাওয়া যাবে তা আপনার plan ঠিক করে।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Open <strong>"Past papers"</strong> from the menu — whole papers of education boards, admission tests, job tests and more.</li>
                        <li>Find a paper by type, source and year.</li>
                        <li><strong>Students:</strong> sit the whole paper as an exam; answers and explanations show as soon as you hand it in.</li>
                        <li><strong>Teachers:</strong> take the whole paper as your own exam — MCQ online, MCQ and CQ in print.</li>
                    </ol>
                    <p>Which papers, and how many, are set by your plan.</p>
                    HTML,
            ],

            // ── Earnings and withdrawals ────────────────────────────────
            [
                'slug' => 'referral-and-withdrawal',
                'category' => GuideCategory::Earnings,
                'roles' => ['teacher', 'student'],
                'pages' => [GuidePage::Commission],
                'title_bn' => 'রেফারেল, কমিশন ও টাকা তোলা',
                'title_en' => 'Referrals, commission and taking money out',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>মেনুর <strong>"রেফারেল ও কমিশন"</strong>-এ আপনার রেফারেল কোড ও লিংক আছে। অন্যদের সাথে শেয়ার করুন।</li>
                        <li>কেউ আপনার কোড দিয়ে (শিক্ষকের ক্ষেত্রে আপনার পরীক্ষার লিংক খুলে) এলে কিছু দিনের জন্য আপনার রেফারেলে থাকে; সেই সময়ে সে plan কিনলে আপনি কমিশন পান।</li>
                        <li>কমিশন পেতে <strong>আপনার নিজের পেইড plan চালু থাকতে হয়</strong>। Refund-এর সময় পার হলে কমিশন পাকা হয়ে wallet-এ জমে।</li>
                        <li><strong>টাকা তুলতে</strong> "নগদে তুলুন" চাপুন, bKash/Nagad নম্বর ও পরিমাণ দিন। Admin পরিশোধ করলে টাকা নম্বরে চলে যায়।</li>
                    </ol>
                    <p>Wallet-এ একটা অংশ সবসময় থেকে যায় (পরের plan কেনার জন্য) — সেটা তোলা যায় না, শুধু plan কেনায় খরচ করা যায়। একসাথে একটাই অনুরোধ থাকতে পারে।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li><strong>"Referrals &amp; commission"</strong> in the menu has your referral code and link. Share them.</li>
                        <li>Someone who comes with your code (for a teacher, by opening your exam link) stays under your referral for a while; if they buy a plan then, you earn commission.</li>
                        <li>To earn, <strong>your own paid plan must be on</strong>. Commission becomes final after the refund time and goes into your wallet.</li>
                        <li><strong>To take money out</strong>, press "Take out cash", give your bKash/Nagad number and the amount. Once an Admin pays it, the money is sent to that number.</li>
                    </ol>
                    <p>A part of the wallet always stays (for your next plan) — it can't be taken out, only spent on a plan. Only one request can wait at a time.</p>
                    HTML,
            ],
            [
                'slug' => 'staff-earnings-and-payout',
                'category' => GuideCategory::Earnings,
                'roles' => ['staff'],
                'pages' => [GuidePage::StaffEarnings],
                'title_bn' => 'Staff-এর আয় ও টাকা পাওয়া',
                'title_en' => 'Staff earnings and getting paid',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>প্রতিটা প্রশ্ন <strong>approve হওয়ার মুহূর্তে</strong> সেই বিষয়ের রেট অনুযায়ী টাকা আপনার আয়ে যোগ হয়; পরে রেট বদলালেও আগের আয় বদলায় না।</li>
                        <li>বিগত সালের প্রশ্নপত্র তুললে <strong>পুরো পেপার approve হলে পেপারপ্রতি</strong> টাকা।</li>
                        <li>প্রোফাইলে <strong>পেমেন্ট পদ্ধতি</strong> (bKash/Nagad নম্বর বা ব্যাংক) দিন।</li>
                        <li>Admin একসাথে payout করেন; "পেআউটের ইতিহাস"-এ কবে কত পেয়েছেন দেখুন।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Each question earns its subject's rate <strong>the moment it is approved</strong>; a later change of rate doesn't change what you already earned.</li>
                        <li>For a past paper, you are paid <strong>per paper once the whole paper is approved</strong>.</li>
                        <li>Give your <strong>payment method</strong> (bKash/Nagad number or bank) on your profile.</li>
                        <li>Admins pay out in batches; see what you got and when on "Payout History".</li>
                    </ol>
                    HTML,
            ],

            // ── School ──────────────────────────────────────────────────
            [
                'slug' => 'school-connect-software',
                'category' => GuideCategory::School,
                'roles' => ['school'],
                'pages' => [GuidePage::SchoolConnection],
                'title_bn' => 'স্কুলের সফটওয়্যার যুক্ত করা',
                'title_en' => 'Connecting the school software',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>এখানে <strong>"স্কুল সফটওয়্যারের সংযোগ"</strong> পেজে তিনটা তথ্য আছে: Question Bank-এর ঠিকানা, সংযোগ কোড ও সিক্রেট।</li>
                        <li>স্কুলের সফটওয়্যারে Admin হিসেবে লগইন করে <strong>School Settings → Question Bank</strong> খুলুন।</li>
                        <li>তিনটা তথ্য কপি করে বসান, তারপর <strong>"সেভ করে যুক্ত করুন"</strong>।</li>
                        <li>দুই জায়গাতেই "যুক্ত আছে" দেখালে কাজ শেষ। এরপর স্কুলের সফটওয়্যারের মেনুতে Question Bank-এর লিংক আসে।</li>
                    </ol>
                    <p>সিক্রেট কাউকে দেবেন না। ফাঁস হলে "নতুন সিক্রেট" বানিয়ে আবার বসান।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>The <strong>"School software connection"</strong> page here has three things: the Question Bank's address, the connection code and the secret.</li>
                        <li>In the school software, sign in as Admin and open <strong>School Settings → Question Bank</strong>.</li>
                        <li>Copy the three in, then press <strong>"Save and connect"</strong>.</li>
                        <li>When both sides say "Connected", you are done. The school software's menus then show a Question Bank link.</li>
                    </ol>
                    <p>Never share the secret. If it leaks, make a new one and put it in again.</p>
                    HTML,
            ],
            [
                'slug' => 'school-exams',
                'category' => GuideCategory::School,
                'roles' => ['school'],
                'pages' => [GuidePage::BuildExam, GuidePage::MyExams],
                'title_bn' => 'স্কুলের ছাত্রদের পরীক্ষা',
                'title_en' => 'Exams for the school\'s students',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>পরীক্ষা বানানো Teacher-এর মতোই — "প্রশ্ন তৈরি করুন" থেকে।</li>
                        <li>লিংক শেয়ার করলে ছাত্ররা লগইন করে না; লিংকে <strong>ক্লাস → সেকশন → রোল → নাম</strong> দিয়ে ঢোকে। ক্লাস ও সেকশন আসে স্কুলের সফটওয়্যার থেকে।</li>
                        <li>ফলাফল দেখতে ছাত্ররা একই লিংকে ক্লাস, সেকশন ও রোল দেয়।</li>
                        <li>ছাত্রদের কোনো subscription লাগে না।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Build exams as a teacher does — from "Select questions".</li>
                        <li>Students don't sign in on the link; they enter with <strong>class → section → roll → name</strong>. Classes and sections come from the school software.</li>
                        <li>To see their result, students give their class, section and roll on the same link.</li>
                        <li>Students need no subscription.</li>
                    </ol>
                    HTML,
            ],
            [
                'slug' => 'school-send-results',
                'category' => GuideCategory::School,
                'roles' => ['school'],
                'pages' => [GuidePage::SchoolResults, GuidePage::MyExams],
                'title_bn' => 'স্কুলের রেজাল্টে নম্বর পাঠানো',
                'title_en' => 'Sending marks to the school results',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>"পরীক্ষা" তালিকায় অনলাইন পরীক্ষার পাশে <strong>"স্কুলের রেজাল্টে পাঠান"</strong> চাপুন।</li>
                        <li>ক্লাস → স্কুলের সফটওয়্যারের পরীক্ষা → বিষয় বেছে <strong>"ছাত্র-তালিকা আনুন"</strong>।</li>
                        <li>পূর্ণমান না মিললে জানতে চায়: স্কুলের সফটওয়্যারে ঠিক করবেন, নাকি <strong>"অনুপাতে বসান"</strong>।</li>
                        <li>তালিকায় দেখুন: মিলেছে (নিজে থেকেই যাবে), নাম মেলেনি (approve করলে যাবে), এক রোলে একাধিক খাতা (একটা বাছুন), খাতা নেই (অনুপস্থিত)।</li>
                        <li>আগে থেকে নম্বর থাকলে বদলাতে চাইলে টিক দিন, তারপর <strong>"স্কুলের রেজাল্টে পাঠান"</strong>।</li>
                    </ol>
                    <p>রেজাল্ট প্রকাশ হয়ে গেলে আর বদলানো যায় না। লিখিত/ব্যবহারিক নম্বর কখনো বদলায় না।</p>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>On "Exams", press <strong>"Send to school results"</strong> beside an online exam.</li>
                        <li>Choose the class → the school software's exam → the subject, and <strong>"Load the student list"</strong>.</li>
                        <li>If the full marks differ, you are asked: fix it in the school software, or <strong>"Put them in proportion"</strong>.</li>
                        <li>Check the list: matched (goes by itself), name doesn't match (goes once approved), roll used more than once (pick one), no paper (absent).</li>
                        <li>Tick to replace marks already there if you want, then <strong>"Send to school results"</strong>.</li>
                    </ol>
                    <p>A published result can't change. Written and practical marks are never touched.</p>
                    HTML,
            ],
            [
                'slug' => 'school-own-questions',
                'category' => GuideCategory::School,
                'roles' => ['school'],
                'pages' => [GuidePage::WriteQuestions],
                'title_bn' => 'স্কুলের নিজের প্রশ্ন ও ছাত্ররা কবে দেখে',
                'title_en' => 'The school\'s own questions and when students see them',
                'body_bn' => <<<'HTML'
                    <ol>
                        <li>School-এর লেখা প্রশ্ন শুধু স্কুলের — সাধারণ প্রশ্নভাণ্ডারে যায় না, কেউ approve করে না।</li>
                        <li>প্রশ্নটা যে পরীক্ষাগুলোয় আছে সেগুলো শেষ হওয়ার <strong>পরের দিন থেকে</strong> স্কুলের ছাত্ররা স্কুলের সফটওয়্যারের Student প্যানেলে উত্তরসহ দেখতে পায়।</li>
                        <li>পরীক্ষায় শেষ সময় না থাকলে (বা প্রশ্ন কোনো পরীক্ষায় না থাকলে) লেখার কিছু দিন পরে খোলে — দিনটা Admin ঠিক করেন।</li>
                        <li>ছাত্ররা পরীক্ষা দেওয়ার পরও প্রশ্ন ঠিক করা যায়; তখন নতুন সংস্করণ হয়, দেওয়া পরীক্ষায় আগেরটাই থাকে।</li>
                    </ol>
                    HTML,
                'body_en' => <<<'HTML'
                    <ol>
                        <li>Questions the School writes are its own — they never go into the shared question bank and nobody approves them.</li>
                        <li>From <strong>the day after</strong> every exam a question is on has ended, the school's students can read it with its answer in the school software's Student panel.</li>
                        <li>When an exam has no end time (or the question is on none), it opens some days after it was written — the Admin sets how many.</li>
                        <li>You can still correct a question after students sat it; it becomes a new version, and the exams they sat keep the old one.</li>
                    </ol>
                    HTML,
            ],
        ];
    }
}
