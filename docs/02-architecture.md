# ০২ — Architecture

## Tech stack (ইনস্টল করা সংস্করণ অনুযায়ী)

| স্তর | যা ব্যবহার হচ্ছে |
|---|---|
| ভাষা | PHP 8.3 |
| Framework | Laravel 13 (`laravel/framework ^13.17`) |
| প্যানেল | Filament 5 (Livewire-ভিত্তিক; ফর্ম ও লেআউট `Filament\Schemas`-এ) |
| Role | `spatie/laravel-permission` (role-এর নাম সংরক্ষণে; অনুমতির সিদ্ধান্ত Policy-তে) |
| Google login | `laravel/socialite` |
| Frontend | Vite 8, Tailwind CSS 4, Alpine (Filament-এর সাথে আসে) |
| প্রশ্নের এডিটর | CKEditor 5 + KaTeX (সূত্র রেন্ডার) + MathLive (সূত্র লেখার modal) |
| টেস্ট | Pest 4 |
| Database | লোকাল ও টেস্টে SQLite, লাইভে MySQL |

Payment gateway এখনো নেই; পেমেন্ট হাতে যাচাই হয় (bKash/Nagad/Rocket-এর transaction id দিয়ে)। → [08-billing.md](08-billing.md)

## ফোল্ডার কাঠামো

```
app/
  Enums/                    সব status/type-এর মান (নিচে তালিকা)
  Filament/
    Resources/              Admin প্যানেলের resource (Questions, Staffs, Payments, …)
    Pages/                  Admin প্যানেলের পেজ (BillingSettings, ReferralReport)
    Teacher/                Teacher প্যানেল: Pages, Resources (Questions, Exams), Widgets
    Staff/                  Staff প্যানেল: Pages, Resources (Questions), Widgets
    Student/                Student প্যানেল: Pages, Widgets
    Support/                চার প্যানেলের শেয়ার করা অংশ (নিচে)
    Forms/Components/       CkEditorField
  Http/
    Controllers/            Google login, Guest পরীক্ষা, প্রিন্ট, referral লিংক, ভাষা বদল
    Middleware/             EnsureStaffIsApproved, SetLocale
  Models/                   Eloquent model; Concerns/-এ শেয়ার করা trait
  Observers/                QuestionObserver, BoardQuestionPaperObserver
  Policies/                 QuestionPolicy, ExamPolicy, BoardQuestionPaperPolicy
  Providers/Filament/       চারটা PanelProvider
  Services/                 ব্যবসায়িক লজিক (নিচে)
database/migrations, seeders, factories
resources/
  css/                      app.css, question-display.css (qb-* ক্লাস), ckeditor-question-editor.css, filament/admin/theme.css
  js/                       ckeditor-question-editor.js, katex-embeds.js, katex-embed-renderer.js, exam-timer.js
  views/                    components/ (শেয়ার করা blade), filament/ (প্যানেলের পেজ), guest পেজ
routes/web.php              Google auth, Guest পরীক্ষা, referral লিংক, ভাষা
routes/dev.php              DEV-LOGIN-BYPASS (শুধু local) — দেখুন DEV-LOGIN-BYPASS.md
DEV-LOGIN-BYPASS.md         লোকাল login বাইপাসের সব অংশ ও মুছে ফেলার ধাপ
lang/bn.json                সব বাংলা অনুবাদ
tests/Feature/              প্রায় সব টেস্ট এখানে
docs/                       এই ডকুমেন্টেশন
```

Admin প্যানেলের resource `app/Filament/Resources/`-এ সরাসরি থাকে (`Admin/` নামে আলাদা ফোল্ডার নেই); বাকি তিন প্যানেলের নিজের নামে ফোল্ডার আছে।

## কোন নিয়ম কোথায় থাকে

মূলনীতি (CLAUDE.md, DRY ৫): একটা ব্যবসায়িক নিয়ম একটাই মেথডে। Controller, Livewire পেজ আর Blade শুধু সেটা ডাকে।

### Service (`app/Services/`)

| Service | দায়িত্ব |
|---|---|
| `TeacherExamBuilder` | Teacher-এর বাছাই থেকে পরীক্ষা তৈরি ও এডিট; প্রতিটা প্রশ্ন সার্ভারে আবার যাচাই |
| `SelfPracticeExamService` | Student-এর self-practice পরীক্ষা (Auto, Manual, Mistakes) |
| `SubscriptionLimitService` | user-এর সব plan মিলিয়ে কী পারে: শ্রেণি/বিষয়, difficulty, সুবিধা, দিনের ও মাসের সীমা; পরীক্ষা কোন plan-এ গোনা হবে (`PlanConditions`, `PlanGrant` সহ) |
| `SubscriptionPurchaseService` | পেমেন্টের পুরো জীবন: request → approve / reject / refund |
| `ReferralService` | কে কাকে refer করতে পারে, discount, reward (Teacher-এর কমিশনের শর্তসহ) |
| `StudentReferralService` | একজন student কখন, কার, কত দিনের referral-এ — শুরু, বদল, লক |
| `SubscriptionMemberService` | group plan-এর সদস্য যোগ/সরানো (আসন, role, কত দিনে একবার) |
| `DeviceSessionService` | একসাথে কয়টা ডিভাইসে লগইন; প্রোফাইলের ডিভাইস-তালিকা |
| `WithdrawalService` | Teacher/Student-এর কমিশন নগদে তোলা: reserve, request, paid/reject |
| `WalletService` | wallet credit যোগ, খরচ ও balance |
| `OtpService` | wallet credit খরচে ফোনের OTP (Admin চালু করলে) |
| `ExamResultSheet` | Teacher-এর ফলাফল-তালিকা ও পজিশন |
| `QuestionJsonImporter`, `QuestionImportText` | JSON থেকে প্রশ্ন ইমপোর্ট; `$...$` সূত্র যাচাই ও রূপান্তর |
| `QuestionDifficultyTracker` | উত্তর থেকে MCQ-র difficulty হিসাব |
| `StudentMistakeTracker` | student-এর ভুলের তালিকা |
| `TeacherQuestionStats` | Teacher-এর নিজের ছাত্রদের প্রশ্নভিত্তিক ভুলের সংখ্যা |
| `ReferralReport` | Admin-এর referral রিপোর্ট |

### Model-এ থাকা নিয়ম

| কোথায় | কী |
|---|---|
| `Question::scopeApprovedPool()` | approved + latest: অন্যের প্রশ্ন দেখা/ব্যবহারের একমাত্র শর্ত |
| `Question::scopeUsableInExamBy()` | Teacher নিজের পরীক্ষায় কী নিতে পারে (pool + নিজের pending) |
| `Question::createRevisionWith()` | approved প্রশ্ন এডিটে নতুন সংস্করণ |
| `Question::approve()` / `reject()` | status বদল, log, আর Staff-এর earning |
| `Question::isOnAnExam()`, `isFrozenByAttempts()` | ডিলিট/এডিট আটকানোর শর্ত |
| `Exam::isAcceptingAttempts()`, `showsAnswersToStudents()`, `cancel()`, `publish()` | শেয়ার-লিংক, উত্তর প্রকাশ, বাতিল |
| `ExamAttempt::enter()`, `submitAndAutoGrade()`, `recordStatistics()` | পরীক্ষায় ঢোকা, খাতা জমা ও গ্রেডিং, পরিসংখ্যানে তোলা |
| `StaffPayout::createFor()` | Staff-এর payout batch |
| `Models\Concerns\SyncsMarksFromParts` | CQ-র নম্বর = অংশগুলোর যোগফল (সাধারণ ও বোর্ড দুই জায়গায়) |
| `Models\Concerns\HasApprovalStatus` | approve/reject-এর status বদল (প্রশ্ন ও বোর্ড-পেপার) |
| `BillingSetting::current()` | Admin-এর সব সেটিং, অনুরোধপ্রতি একবার পড়া |

### Policy

- `QuestionPolicy` — কে প্রশ্ন দেখে, যোগ করে, ইমপোর্ট করে, এডিট/ডিলিট করে, approve করে।
- `ExamPolicy` — পরীক্ষার মালিকানা, আর publish/প্রিন্টে plan-এর অনলাইন/প্রিন্ট সুবিধা।
- `BoardQuestionPaperPolicy` — বোর্ড প্রশ্নপত্র।

### Visibility-র হার্ডকোড ফিল্টার

প্রতিটা প্যানেলের `QuestionResource::getEloquentQuery()` নিজে ঠিক করে ওই প্যানেলের user কোন প্রশ্নে পৌঁছাতে পারে: Admin সব, Teacher ও Staff শুধু নিজের (`ownedBy()`)। এটা শুধু UI লুকানো না; URL হাতে বদলালেও অন্যের প্রশ্নে পৌঁছানো যায় না (CLAUDE.md নিয়ম ২)।

## প্যানেলগুলোর শেয়ার করা অংশ (`app/Filament/Support/`)

চার প্যানেলের একই ধরনের পেজ একটা abstract base থেকে আসে; প্যানেলের নিজের ক্লাসে থাকে শুধু তার resource।

| কী | কোথায় |
|---|---|
| প্যানেলের অভিন্ন কনফিগ | `PanelDefaults::apply()` |
| প্রশ্নের resource, ফর্ম, টেবিল, View | `Resources\QuestionResourceBase`, `QuestionFormSchema`, `QuestionsTable`, `QuestionInfolist` |
| Class → Subject → Chapter কার্ড ও প্রশ্নের তালিকা | `Pages\Questions\Browse*Page`, `ListQuestionsByChapterPage` |
| প্রশ্ন তৈরি/এডিট | `Pages\Questions\CreateQuestionPage`, `EditQuestionPage`, `Concerns\HandlesQuestionForm` |
| JSON ইমপোর্টের বাটন | `QuestionJsonImportAction` |
| Class/Subject/Chapter/Topic select | `ContentHierarchySchema` |
| Subject-এর সংক্ষিপ্ত নামের কলাম | `SubjectColumn` |
| Self-practice পেজের base | `Pages\SelfPracticeExamPage` |
| প্রোফাইল ও subscription পেজ | `Pages\MyProfilePage`, `Pages\MySubscriptionPage` |
| Dashboard widget | `Widgets\DashboardStat`, `RecentQuestionsWidget`, `SuspensionNoticeWidget` |

পুরো তালিকা CLAUDE.md-এর "শেয়ার করা অংশগুলো" টেবিলে; নতুন কিছু লেখার আগে সেটা দেখে নিন।

## Enum-এর মান

| Enum | মান |
|---|---|
| `UserRole` | `super_admin`, `admin`, `teacher`, `staff`, `student` |
| `UserStatus` | `pending`, `active`, `suspended`, `permanent_suspend` |
| `QuestionType` | `mcq`, `cq` |
| `QuestionStatus` | `pending`, `approved`, `rejected` |
| `Difficulty` | `easy`, `medium`, `hard` |
| `CqPartType` | `knowledge`, `comprehension`, `application`, `higher_application` |
| `EditorMode` | `richtext`, `ckeditor` |
| `ExamType` | `teacher_exam`, `self_practice` |
| `GenerationMode` | `manual`, `auto`, `mistakes` |
| `ExamDeliveryMode` | `online`, `offline`, `both` |
| `ExamStatus` | `draft`, `published`, `ongoing`, `completed` |
| `ExamAttemptStatus` | `in_progress`, `submitted`, `auto_submitted` |
| `PaymentStatus` | `pending`, `success`, `failed`, `rejected`, `refunded` |
| `PaymentGateway` | `manual`, `sslcommerz`, `bkash`, `nagad` |
| `SubscriptionStatus` | `active`, `expired`, `cancelled` |
| `BillingCycle` | `free`, `monthly`, `yearly` |
| `WalletTransactionType` | `purchase`, `purchase_return` |
| `StaffEarningStatus` | `pending_payout`, `paid` |
| `StaffPayoutStatus` | `processing`, `paid`, `failed` |
| `ReactivationRequestStatus` | `pending`, `approved`, `declined` |

`ReferralRewardStatus` (`maturing`, `available`, `paid`, `cancelled`) কোনো কলামে সংরক্ষিত হয় না; reward-এর তারিখগুলো থেকে পড়া হয়।

## Frontend-এর বান্ডেল

- `ckeditor-question-editor.js` — Admin/Teacher/Staff প্যানেলে: পুরো CKEditor, সাথে `ckeditor-question-editor.css`।
- `katex-embed-renderer.js` — Student প্যানেল, Guest পেজ ও প্রিন্টে: শুধু সূত্র রেন্ডার।
- দুটোই `katex-embeds.js` ব্যবহার করে, যেটা পেজে থাকা `<span class="qb-katex-embed">latex</span>`-কে আঁকে এবং পেজ বদলালে আবার আঁকে।
- `question-display.css`-এর `qb-*` ক্লাসগুলো `app.css` ও Filament theme দুই জায়গাতেই যায়, তাই Guest পেজ আর প্যানেলে প্রশ্ন একই রকম দেখায়।

CSS বা JS বদলালে লাইভের জন্য build আলাদাভাবে আপলোড করতে হয়। → [10-testing-and-deploy.md](10-testing-and-deploy.md)

## ভাষা

বাংলা ও ইংরেজি। **ডিফল্ট বাংলা, শুধু Admin প্যানেলের ডিফল্ট ইংরেজি।** যে দর্শক এখনো ভাষা বাছেনি, সে Teacher/Staff/Student প্যানেল, হোম পেজ ও Guest-এর পরীক্ষা বাংলায় দেখে, আর Admin প্যানেল (লগইন পেজসহ) ইংরেজিতে। ভাষা বদলানোর বাটনে কিছু বাছলে সেটা ওই session-এ সব জায়গায় খাটে, Admin প্যানেলেও।

- প্যানেলভিত্তিক ডিফল্ট: `PanelDefaults::apply()`-এর `defaultLocale` (Admin-এ `AdminPanelProvider` দেয় `Locale::En`), যেটা `SetLocale` middleware-এ `SetLocale::class.':en'` হিসেবে যায়। অন্য কোনো প্যানেলের ডিফল্ট বদলাতে হলে ওই প্যানেলের provider-এ একই প্যারামিটার দিন।

- ডিফল্ট আসে `config/app.php`-এর `locale` থেকে, যেটা `.env`-এর `APP_LOCALE` পড়ে (না থাকলে `bn`)। লাইভের `.env`-এ `APP_LOCALE=bn` থাকতে হবে; `en` থাকলে ডিফল্ট ইংরেজি হয়ে যাবে।
- `fallback_locale` ইংরেজি: কোনো বাক্যের বাংলা না থাকলে ইংরেজিটাই দেখায়।
- কোডে সব লেখা ইংরেজিতে `__('...')` দিয়ে, অনুবাদ `lang/bn.json`-এ। `SetLocale` middleware session-এ রাখা পছন্দ প্রয়োগ করে; বদলানো হয় `/locale/{locale}` দিয়ে।
- প্যানেলের মেনুর গ্রুপের নাম (`NavigationGroup`) প্রতিটা পেজে দর্শকের ভাষায় নতুন করে পড়া হয় (`PanelDefaults`); Filament-কে enum সরাসরি দিলে নামগুলো প্যানেল চালু হওয়ার মুহূর্তের ভাষায় আটকে যেত।
- টেস্ট চলে ইংরেজিতে (`phpunit.xml`-এ `APP_LOCALE=en`), তাই টেস্টের বাক্যগুলো ইংরেজি। `LocaleSwitchTest` নিশ্চিত করে যে কোডে ব্যবহৃত প্রতিটা বাক্যের বাংলা আছে।
