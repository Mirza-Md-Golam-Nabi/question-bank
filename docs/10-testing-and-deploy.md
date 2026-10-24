# ১০ — টেস্ট, ব্রাউজারে যাচাই ও Deploy

সেটআপের ধাপ (লোকাল ও cPanel): [../README.md](../README.md)। এখানে আছে কাজ শেষ করার আগে কী যাচাই করতে হয়, আর যে ফাঁদগুলোতে ইতিমধ্যে পড়া হয়েছে।

## একটা পরিবর্তন শেষ করার আগে

1. `vendor/bin/pint --dirty --format agent` — PHP-র ফরম্যাট।
2. সংশ্লিষ্ট টেস্ট: `php artisan test --compact tests/Feature/<File>.php`।
3. পর্দায় দেখা যায় এমন পরিবর্তন হলে **ব্রাউজারে চালিয়ে দেখা** (নিচে)।
4. পুরো স্যুট: `php artisan test --compact`।
5. সংশ্লিষ্ট `docs/` ফাইল ও [CHANGELOG.md](CHANGELOG.md)।

## টেস্ট

- Pest, প্রায় সবই `tests/Feature/`-এ। ডেটাবেজ: SQLite, মেমরিতে (`phpunit.xml`)।
- নতুন টেস্ট: `php artisan make:test --pest <Name>`; model বানাতে factory ব্যবহার করুন।
- Filament-এর পেজ `livewire()` দিয়ে পরীক্ষা হয়। Admin ছাড়া অন্য প্যানেলের custom পেজে আগে `Filament::setCurrentPanel(Filament::getPanel('teacher'))` লাগে।

### কোন টেস্ট কী পাহারা দেয়

| বিষয় | টেস্ট |
|---|---|
| লগইন, প্যানেলে ঢোকা, user-এর অবস্থা | `GoogleAuthTest`, `AdminPanelAccessTest`, `StaffPendingApprovalTest`, `TeacherSuspensionTest`, `ReactivationRequestTest`, `LogoutRedirectTest` |
| প্রশ্নের visibility ও নিয়ম | `TeacherStaffQuestionVisibilityTest`, `QuestionModelTest`, `QuestionSoftDeleteTest`, `CqMarksSyncTest`, `AdminQuestionResourceTest` |
| প্রশ্নের কার্ড পেজ | `QuestionBankCardBrowserTest`, `TeacherQuestionBankCardBrowserTest`, `StaffQuestionBankCardBrowserTest`, `TopicTest`, `TopicResourceTest`, `ChapterResourceTest`, `AcademicHierarchyResourcesTest` |
| JSON ইমপোর্ট | `QuestionJsonImportTest` |
| Dynamic difficulty | `QuestionDynamicDifficultyTest` |
| পরীক্ষা তৈরি ও নিজের pending প্রশ্ন | `TeacherSelectQuestionsTest`, `TeacherExamResourceTest`, `TeacherOwnPendingQuestionsOnExamTest` |
| পরীক্ষা দেওয়া, গ্রেডিং, উত্তর প্রকাশ | `GuestExamFlowTest`, `StudentExamFlowTest`, `ExamAttemptGradingTest`, `ExamAnswerReleaseTest`, `ExamModelTest` |
| ফলাফল ও প্রিন্ট | `TeacherExamResultsTest`, `TeacherExamPrintTest` |
| ভুলের অনুশীলন, Teacher-এর পরিসংখ্যান | `StudentMistakePracticeTest`, `TeacherQuestionStatsTest` |
| Subscription, পেমেন্ট, referral, wallet | `SubscriptionLimitServiceTest`, `SubscriptionPurchaseServiceTest`, `SubscriptionPurchaseFlowTest`, `SubscriptionResourcesTest`, `ReferralTest`, `StaffReferralTest`, `ReferralReportTest`, `WalletServiceTest`, `OtpServiceTest` |
| Staff-এর আয় | `StaffPayrollTest`, `StaffPayoutTest`, `StaffPayrollResourcesTest` |
| বোর্ড প্রশ্নপত্র | `BoardQuestionPaperModelTest`, `BoardQuestionPaperResourceTest` |
| ড্যাশবোর্ড | `TeacherDashboardWidgetsTest`, `StaffDashboardWidgetsTest` |
| N+1 ও query-সংখ্যা | `QueryCountTest`, `AggregateQueriesTest` (আর ফিচারের নিজের টেস্টে) |
| অনুবাদ | `LocaleSwitchTest` |
| লোকাল login বাইপাস | `DevLoginBypassTest` |

### Query-সংখ্যার নিয়ম

তালিকা বা খাতা জমার মতো কাজে query-সংখ্যা সারির সংখ্যার সাথে বাড়া চলবে না। প্রতি-সারির মান তালিকার query-তেই হিসাব করুন (`withCount`, `withSum`, `withExists`, subselect)। পাহারার ধরন: অল্প সারি ও বেশি সারিতে একই কাজ চালিয়ে query-সংখ্যা সমান কি না দেখা (`QueryCountTest`)। প্রথম অনুরোধের এককালীন query (যেমন সেটিংস একবার পড়া) তুলনার আগে চালিয়ে নিন।

### অনুবাদের নিয়ম

`LocaleSwitchTest` `app/` ও `resources/views`-এর প্রতিটা `__('...')` ও `trans_choice('...')` বাক্য কোডে **যেমন লেখা আছে তেমন** `lang/bn.json`-এ খোঁজে। তাই:

- প্রতিটা নতুন বাক্যের বাংলা একই পরিবর্তনে যোগ করুন।
- বাক্যের ভেতরে escape রাখবেন না (`\\\\`, `\'`); দরকার হলে placeholder ব্যবহার করুন: `__('… (:twice, not \frac)', ['twice' => '\\\\frac'])`।

## ব্রাউজারে যাচাই

টেস্ট পাস করা মানে পেজ ঠিক দেখাচ্ছে তা না। প্রথমবার ব্রাউজারে চালিয়েই দুটো ত্রুটি পাওয়া গেছে যা কোনো টেস্ট ধরেনি: dark mode-এ অদৃশ্য একটা বাটন, আর তালিকা নতুন করে আঁকার পর না-আঁকা সূত্র।

ব্যবস্থাটা দুই অংশে। এর প্রতিটা অংশ (রিপোজিটরির ভেতরে ও বাইরে) আর মুছে ফেলার ধাপ এক ফাইলে: [../DEV-LOGIN-BYPASS.md](../DEV-LOGIN-BYPASS.md)। মুছতে চাইলে Claude-কে লিখুন "বাইপাস মুছে দাও"।

**১. DEV-LOGIN-BYPASS (রিপোজিটরির ভেতরে)** — `/dev-login/{user}` Google ছাড়া লগইন করায়। শুধু `APP_ENV=local` হলে রুটটা তৈরি হয়।

**২. `D:\project\qb-browser-check\` (রিপোজিটরির বাইরে)** — headless Chrome চালানোর স্ক্রিপ্ট (`browser.mjs`), প্রতিটা ফ্লোর চেক (`checks/`), নিজস্ব SQLite ডেটাবেজ, স্ক্রিনশট। কীভাবে চালাতে হয় তার পুরো বিবরণ ওই ফোল্ডারের `NOTES.md`-এ।

সংক্ষেপে: আলাদা ডেটাবেজ নিয়ে পোর্ট ৮০০১-এ একটা সার্ভার চালানো হয়, চেকের স্ক্রিপ্ট `http://127.0.0.1:8001/dev-login/<id>` দিয়ে ঢুকে পেজে ক্লিক করে ও স্ক্রিনশট নেয়। Developer-এর নিজের ডেটাবেজে হাত পড়ে না।

**যা এভাবে যাচাই হয় না:** লাইভ সার্ভার (সেখানে বাইপাস নেই), আর আসল Google login-এর ধাপ।

## Deploy

`deploy.sh` (cPanel): `git pull` → `composer install` → maintenance mode → `migrate --force` → cache পরিষ্কার ও পুনর্গঠন → queue restart → maintenance mode বন্ধ।

### যা `deploy.sh` করে না

**Frontend build।** `public/build` Git-এ যায় না, আর স্ক্রিপ্ট `npm run build` চালায় না। তাই `resources/css` বা `resources/js`-এ কিছু বদলালে:

1. লোকালে `npm run build`।
2. পুরো `public/build` ফোল্ডার সার্ভারের একই জায়গায় আপলোড (পুরনোটা মুছে, `manifest.json`-সহ)।

না করলে লাইভে পুরনো CSS/JS চলবে। এটা একবার ঘটেছে: JSON ইমপোর্টের modal লোকালে ঠিক ছিল (কারণ `npm run dev` চলছিল), লাইভে ভাঙা।

### Deploy-এর আগে দেখে নিন

- **লাইভের `.env`-এ `APP_ENV=production`।** `local` থাকলে `/dev-login` রুট লাইভেও খুলে যাবে।
- **ডেটাবেজের ব্যাকআপ**, বিশেষ করে migration ডেটা বদলালে। দুটো migration পুরনো খাতা হিসাবে তোলে (`add_dynamic_difficulty_tracking`, `add_mistake_practice_and_teacher_question_stats`); খাতা অনেক হলে এগুলো সময় নেয়, আর ততক্ষণ সাইট maintenance mode-এ থাকে।
- **Google OAuth-এর redirect URI** লাইভের ডোমেইনে ঠিক আছে কি না (README দেখুন)।
- **Push-এর VAPID key** লাইভের `.env`-এ (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`): প্রথমবার `php artisan webpush:vapid`, তারপর কখনো বদলাবেন না। আর `public/images/pwa/`-এর icon তিনটা সার্ভারে আপলোড হয়েছে কিনা (আসল লোগো এলে একই নামে বদলে)। Push ও app-এর install শুধু HTTPS-এ চলে। → [13-app-and-offers.md](13-app-and-offers.md)

## জানা ফাঁদ

| ফাঁদ | কী মনে রাখতে হবে |
|---|---|
| SQLite বনাম MySQL | লোকাল ও টেস্ট SQLite, লাইভ MySQL। তারিখ দিয়ে grouping, `LIKE`-এর escape, raw SQL, একই টেবিল থেকে subquery নিয়ে `UPDATE` — এগুলো লাইভে প্রথমবার চলে। যতটা সম্ভব query builder ব্যবহার করুন। |
| `php artisan serve` ও পরিবেশ-চলক | এটা `.env` আবার পড়ে; শেলে দেওয়া `DB_DATABASE` উপেক্ষা হতে পারে। ব্রাউজার-চেকের সার্ভার তাই `php -S` দিয়ে চালানো হয়। |
| Livewire-এর পুনরায় আঁকা | Livewire একটা তালিকা নতুন করে আঁকলে উপাদানগুলো রেখে ভেতরের লেখা বদলায়। ব্রাউজারে যোগ করা "হয়ে গেছে" ধরনের class-এ ভরসা করা যায় না; `katex-embeds.js` তাই সূত্র আঁকা হয়েছে কি না তার ভেতরের উপাদান দেখে ঠিক করে। |
| Filament-এ enum-এর select | `options(Enum::class)` দেওয়া ঘর `$get()`-এ enum নিজেই ফেরত দেয়, তার `->value` না; দুটোই সামলাতে হয়। |
| Filament-এর disabled ঘর | Disabled ঘরের মান ফর্ম থেকে আসে না। (difficulty ঘর disabled হলে নতুন সংস্করণ তাই আগেরটার difficulty নেয়।) |
| Dark mode | প্যানেল dark mode-এ চলে; নিজের `qb-*` ক্লাসে হালকা background দিলে তার ওপর প্যানেলের বাটনের লেখা অদৃশ্য হতে পারে। |
| Windows-এ OpenSSL | `webpush:vapid` ও push পাঠানো (প্রতিটা বার্তা নতুন key বানায়) Windows-এর PHP-তে "Unable to create the key" দিতে পারে: `OPENSSL_CONF` চলকে PHP-র `openssl.cnf`-এর পথ দিন (Laragon-এ `D:/laragon/bin/php/php-8.3/extras/ssl/openssl.cnf`)। Linux সার্ভারে লাগে না। |
| `Collection::each()` | Callback `false` ফেরত দিলে লুপ থেমে যায়; `&&` দিয়ে এক লাইনে লিখলে এটা অজান্তে ঘটে। |

## CLAUDE.md-এর সাথে কোডের অমিল

ডকুমেন্টেশন লেখার সময় (২০২৬-১০-১০) কোড মিলিয়ে এগুলো পাওয়া গেছে। এই ফোল্ডারের ফাইলগুলো কোডের বর্তমান অবস্থা লিখেছে; CLAUDE.md এখনো বদলানো হয়নি।

| CLAUDE.md যা বলে | কোডে যা আছে |
|---|---|
| Laravel 11 | Laravel 13 |
| Filament Shield, `maatwebsite/laravel-excel`, `spatie/laravel-pdf`/DomPDF ব্যবহার হচ্ছে | কোনোটাই ইনস্টল করা নেই |
| `users.password_hash` | কলামের নাম `password` |
| পরীক্ষায় প্রশ্ন attach-এ model-level Observer যাচাই (নিয়ম ২.৪) | এমন Observer নেই |
| Versioning-এর লজিক `QuestionObserver`-এ (নিয়ম ৪) | `Question::createRevisionWith()`-এ |
| CQ-র নম্বর `QuestionObserver` ও `BoardCqQuestionObserver`-এ sync হয় | `SyncsMarksFromParts` trait-এ; `BoardCqQuestionObserver` নেই |
| Super Admin `AdminResource` থেকে নতুন Admin বানায় | `AdminResource` নেই |
| Admin-এর resource `app/Filament/Admin/Resources/`-এ; Admin-এর `ExamResource` আছে | `app/Filament/Resources/`-এ; Admin-এর `ExamResource` নেই |
| "প্রস্তাবিত" ফোল্ডার কাঠামো | আসল কাঠামো [02-architecture.md](02-architecture.md)-এ |

আরেকটা আচরণগত ফাঁক: rejected প্রশ্ন এডিট করলে তার status `pending`-এ ফেরে না ([05-question-bank.md](05-question-bank.md))।
