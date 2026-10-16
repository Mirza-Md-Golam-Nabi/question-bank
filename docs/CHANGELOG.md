# Changelog

নতুন পরিবর্তন ওপরে। প্রতিটা লাইনে: কী বদলেছে, আর বিস্তারিত কোন ডকুমেন্টে।

এই ফাইল ২০২৬-১০-১০ থেকে রাখা হচ্ছে; তার আগের ইতিহাস Git-এ (`git log`)।

## ২০২৬-১০-১৬

- **একাধিক শ্রেণির plan-এ শুধু কমন বিষয়:** "বিষয় বাছবে" plan-এ এখন শুধু সেই বিষয় বাছা যায় যা plan-এর সব শ্রেণিতে পড়ানো হয়; কেনার সময় সার্ভারেও যাচাই। শ্রেণি বাছতে হলে আগে শ্রেণি, তারপর বিষয়। লোকাল সিডারের ট্রায়াল এখন ১ শ্রেণি + ১ বিষয়। Admin-এর plan-টেবিল: Edit/Delete আইকন, "Free plan"/"Trial" কলামের বদলে নামের নিচে লেখা। → [08-billing.md](08-billing.md)

- **লোকাল সিডারে বিষয় → শ্রেণি assign:** `SubjectSeeder` এখন প্রতিটা বিষয়কে তার শ্রেণিগুলোতে (৩–৫, ৬–৮, ৯–১০, ১১–১২) যোগ করে; SSC-র একপত্রের বিজ্ঞান/ব্যবসায়/মানবিক বিষয়ও যোগ হয়েছে (মোট ৬৭)। শুধু `APP_ENV=local`। → [03-database.md](03-database.md)

- **DRY পরিষ্কার:** একই কোড যেখানে একাধিক জায়গায় ছিল তা এক জায়গায় আনা হয়েছে — শ্রেণি/বিষয়ের তালিকা (`AcademicClass::options()`, `ClassSubject::subjectOptionsFor()`), ছয়টা পেজের হাতে-লেখা টেবিল (`<x-data-table>`), রেফারেল পুরস্কারের তালিকা (`<x-referral-rewards-table>`), service আটকালে notification (`NotifiesValidationFailures`), referral সেট ও উত্তোলন বন্ধের ভেতরের কাঠামো, ফোন-বোনাস। আচরণের একটা বদল: প্রোফাইলে আর আয়/wallet দেখায় না — Teacher/Student-এর "রেফারেল ও কমিশন" পেজে, Staff-এর "My Earnings"-এ (আগে দুই জায়গাতেই দেখাত)। → [08-billing.md](08-billing.md)
- **ধাপ ৩:**
  - **নেগেটিভ মার্কিং:** Teacher অনলাইন পরীক্ষা সেভের সময় চালু করেন; প্রতি ভুল উত্তরে কাটা নম্বর (শুরুতে Admin-এর `default_negative_marks`, ০.২৫), ফাঁকায় কাটে না, মোট ০-এর নিচে না; খাতার শিরোনামে লেখা থাকে। নতুন কলাম `exams.negative_marks`। → [06-exams.md](06-exams.md)
  - **প্রতি পরীক্ষায় সর্বোচ্চ অংশগ্রহণকারী:** plan-এর নতুন শর্ত; সেভের সময় `exams.max_participants`-এ কপি; পূর্ণ হলে নতুন কেউ ঢোকে না।
  - **Group plan:** "আসন" শর্ত; ক্রেতা Gmail দিয়ে সদস্য যোগ করেন (`subscription_members`), প্রত্যেকে নিজের একাউন্টে; কাউকে সরানো যায় `group_member_change_days`-এ একবার। → [08-billing.md](08-billing.md)
  - **ডিভাইস সীমা:** Teacher ৩, Student ২ (Admin-এর সেটিং); বেশি হলে সবচেয়ে পুরনো ডিভাইস লগআউট; প্রোফাইলে ডিভাইস-তালিকা।
  - **প্রশ্নপত্রের উৎস:** `boards.type` — বোর্ড, মেডিকেল, বিশ্ববিদ্যালয়, চাকরি, স্কুল-টেস্ট; Admin-এ "Paper Sources"। সাধারণ pool-এর সাথে না মেশানোর নিয়ম অপরিবর্তিত। → [05-question-bank.md](05-question-bank.md)
- **Student-ও কমিশন তোলে:** Teacher-এর মতো Student-ও নিজের পেইড subscription থাকলে কমিশন পায় (শতাংশ Admin-এর `student_reward_percent`), আর নিজের reserve (`student_reserve_amount`) রেখে বাকিটা তুলতে পারে (`student_min_withdrawal`)। উত্তোলন এখন দুই role-এর জন্য এক: `withdrawals` টেবিল, `WithdrawalService`, Admin-এর "Withdrawals" (Teacher/Student ফিল্টার, রেফার করা ক্রেতার নম্বরে টাকা চাইলে লাল চিহ্ন), দুই প্যানেলে "রেফারেল ও কমিশন"। Wallet-এর মেয়াদ সেটিং (`credit_expiry_months`) বাদ — wallet-এর টাকার মেয়াদ নেই। → [08-billing.md](08-billing.md), [09-admin-settings.md](09-admin-settings.md)
- **Referral ও Teacher-এর কমিশন** (ধাপ ২): একজন student একবারে একজনের referral-এ থাকে, Admin-এর দিন পর্যন্ত (ডিফল্ট ৯০) — Teacher-এর পরীক্ষার লিংক খুললে সাথে সাথে লক, নিজে লেখা কোড ১ দিন বদলানো যায়। Referral চলাকালীন প্রতিটা কেনায় ছাড় ও reward (Admin চাইলে শুধু প্রথমটায়)। Teacher-এর কমিশন আলাদা শতাংশে, শুধু পেইড subscription থাকলে, wallet-এ মেয়াদহীন; reserve রেখে বাকিটা request করে bKash/Nagad/Rocket-এ তোলা যায়, Admin "Withdrawals" থেকে পরিশোধ বা বাতিল করেন। Payment-এ কেনার মুহূর্তের referrer লেখা থাকে; রিপোর্ট ও জালিয়াতির সংকেত সেখান থেকে। নতুন: `student_referrals`, `withdrawals`, `payments.referrer_id`/`student_referral_id`, `referral_rewards.percent`, `wallet_transactions.withdrawal_id`, ৬টা Admin সেটিং। → [08-billing.md](08-billing.md), [09-admin-settings.md](09-admin-settings.md), [03-database.md](03-database.md)
- Admin-এর Subscription Plans তালিকায় All / Student / Teacher ট্যাব — পেজ রিলোড বা database query ছাড়াই ব্রাউজারে ছাঁকে (ডিফল্ট All)। → [08-billing.md](08-billing.md)
- **Subscription plan এখন পুরোপুরি ডায়নামিক** (ধাপ ১): Admin plan-এ মেয়াদ (দিন/মাস/বছর/নির্দিষ্ট তারিখ পর্যন্ত) আর শর্ত যোগ-বিয়োগ করেন — শ্রেণি, বিষয় (ক্রেতা বাছতে পারে), difficulty, সুবিধা (অনলাইন/প্রিন্ট), দিনে ও মাসে পরীক্ষা। Teacher-এর একবারের ট্রায়াল, Student-এর প্রোফাইলে শ্রেণি, self-practice শুধু নিজের শ্রেণি থেকে। Plan না থাকলে পরীক্ষা বানানো যায় না; প্রতিটা পরীক্ষা যে subscription-এ হলো তার সীমায় গোনা হয়। নতুন কলাম: `subscription_plans.duration_*`/`ends_on`/`conditions`/`is_trial`/`is_active`, `subscriptions.conditions`/`selections`, `payments.selections`, `exams.subscription_id`, `users.academic_class_id`। → [08-billing.md](08-billing.md), [03-database.md](03-database.md)
- **বন্ধ করা ফাঁক:** Teacher-এর পুরনো exam create/edit পেজ (`/teacher/exams/create`, `/{id}/edit`) মুছে ফেলা হয়েছে — ড্যাশবোর্ডের বাটন দিয়ে খোলা যেত আর সেখানে লিমিট বা প্রশ্নের যাচাই হতো না। এখন একমাত্র পথ "প্রশ্ন বাছাই"। → [06-exams.md](06-exams.md)
- **ডিফল্ট ভাষা বাংলা** (`config/app.php`, `.env.example`-এ `APP_LOCALE=bn`); ইংরেজিতে বদলানো যায়। লাইভের `.env`-এ `APP_LOCALE=bn` বসাতে হবে। → [02-architecture.md](02-architecture.md)
- "প্রশ্ন বাছাই"-এর ছাত্রদের ভুলের ব্যাজ ছোট করা হয়েছে: "৪০ জনে ২৮ জন ভুল" / "28 of 40 wrong"।
- **Teacher ড্যাশবোর্ডের "Recent exams"-এ ফলাফলের বাটন:** পরীক্ষার তালিকার একই দুটো বাটন (ফলাফল, শেয়ার-লিংক কপি) পাশাপাশি; দুটোই এখন `ExamsTable::resultsAction()` ও `copyShareLinkAction()` থেকে আসে। জায়গা বাঁচাতে তৈরির তারিখ শিরোনামের নিচে।
- **Admin প্যানেলের ডিফল্ট ভাষা ইংরেজি** (`AdminPanelProvider` → `PanelDefaults::apply(defaultLocale:)` → `SetLocale`); ভাষা বেছে নিলে সেটাই খাটে।
- প্যানেলের মেনুর গ্রুপের নাম ("Question Bank", "Exams" ইত্যাদি) এখন অনুবাদ হয় এবং ভাষা বদলালে বদলায় (`NavigationGroup`, `PanelDefaults`)।
- DEV-LOGIN-BYPASS: আগের user-এর session পুরো মুছে নতুন user হিসেবে লগইন করে (Admin থেকে অন্য user-এ গেলে লগআউট হয়ে যাচ্ছিল)।

## ২০২৬-১০-১৫

- **DEV-LOGIN-BYPASS-এর সব অংশ এক ফাইলে:** [../DEV-LOGIN-BYPASS.md](../DEV-LOGIN-BYPASS.md) (রিপোজিটরির মূলে) — কোথায় কী আছে, কীভাবে মুছতে হয়; Claude-কে "বাইপাস মুছে দাও" বললেই মুছে দেবে। আগের তালিকা `routes/dev.php` থেকে সরিয়ে এখানে আনা হয়েছে।
- কোডে কোনো আচরণ বদলায়নি। হিসাব-করা difficulty-র ফর্ম ব্রাউজারে যাচাই করা হয়েছে (ঘর লক থাকে, ব্যাখ্যা দেখায়, নতুন সংস্করণে মান টিকে থাকে)।

## ২০২৬-১০-১০

### ডকুমেন্টেশন
- `docs/` ফোল্ডার তৈরি (১২টা ফাইল)। পুরনো `System-Design.md` মুছে ফেলা হয়েছে; তার বিষয়গুলো এখানে হালনাগাদ হয়ে এসেছে।
- CLAUDE.md-এ নিয়ম যোগ: ফিচার বদলালে `docs/` ও এই ফাইল হালনাগাদ করতে হবে।

### ব্রাউজারে যাচাইয়ের ব্যবস্থা
- **DEV-LOGIN-BYPASS:** `/dev-login/{user}`, শুধু `APP_ENV=local`-এ। অংশগুলোর তালিকা পরে [../DEV-LOGIN-BYPASS.md](../DEV-LOGIN-BYPASS.md)-এ আনা হয়েছে। → [10-testing-and-deploy.md](10-testing-and-deploy.md)
- ব্রাউজারে চালিয়ে পাওয়া দুটো ত্রুটি ঠিক: JSON modal-এর "Copy" বাটন dark mode-এ অদৃশ্য ছিল; তালিকা নতুন করে আঁকা হলে সূত্র কাঁচা লেখা হয়ে যেত (`resources/js/katex-embeds.js`)।

### Student-এর ভুলের অনুশীলন ও Teacher-এর পরিসংখ্যান
- Student-এর ভুলের তালিকা, "আমার ভুলগুলো অনুশীলন" পেজ (`generation_mode = mistakes`), ড্যাশবোর্ডে "দুর্বল অধ্যায়"। Teacher-এর পরীক্ষার ভুল উত্তর প্রকাশের আগে অনুশীলনে আসে না। → [07-student-practice.md](07-student-practice.md)
- Teacher-এর "প্রশ্ন বাছাই" পেজে "শুধু যে প্রশ্নগুলো আমার ছাত্ররা ভুল করে" সুইচ ও প্রশ্নপ্রতি ব্যাজ। → [06-exams.md](06-exams.md)
- নতুন টেবিল `student_question_mistakes`, `teacher_question_stats`; `exam_attempts.statistics_recorded_at`; সেটিং `mistake_clear_streak`।

### Dynamic difficulty
- MCQ-র difficulty Teacher-এর পরীক্ষার উত্তর থেকে নিজে ঠিক হয়। → [05-question-bank.md](05-question-bank.md)
- নতুন কলাম `questions.answer_count`, `correct_answer_count`; `exam_attempts.difficulty_counted_at`; সেটিং `difficulty_min_answers`, `difficulty_hard_max_percent`, `difficulty_medium_max_percent`।

### প্রশ্ন ও পরীক্ষা
- Teacher নিজের pending প্রশ্ন নিজের পরীক্ষায় নিতে পারে (`Question::scopeUsableInExamBy()`); rejected প্রশ্ন নতুন পরীক্ষায় যায় না।
- Teacher/Staff approved প্রশ্ন, বা কোনো পরীক্ষায় থাকা প্রশ্ন, ডিলিট করতে পারে না। কোনো student পরীক্ষা শুরু করার পর pending/rejected প্রশ্ন এডিট করা যায় না।
- "প্রশ্ন বাছাই" পেজে "এই পাতার সব বাছুন" বাটন।
- Teacher-এর "আমার প্রশ্ন" এখন Admin/Staff-এর মতো class → subject → chapter কার্ড দিয়ে খোলে; কার্ডে শুধু নিজের প্রশ্নের সংখ্যা (Staff-এও)।

### JSON ইমপোর্ট
- JSON পেস্ট করে একসাথে প্রশ্ন যোগ (MCQ ও CQ, `$...$` সূত্রসহ), প্রিভিউ ধাপ, ফরম্যাট কপির বাটন। Teacher ও Admin-এর জন্য; Staff-এর না। → [05-question-bank.md](05-question-bank.md)
- সেটিং `question_import_max`, `teacher_question_import_enabled`।

### ড্যাশবোর্ড
- Teacher ও Staff-এর "Recent questions" আর Teacher-এর "Recent exams"-এ বিষয়ের সংক্ষিপ্ত নাম; "Recent exams"-এ শেয়ার-লিংকের জায়গায় কপি করার আইকন।
