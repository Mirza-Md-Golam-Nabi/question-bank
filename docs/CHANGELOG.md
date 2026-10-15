# Changelog

নতুন পরিবর্তন ওপরে। প্রতিটা লাইনে: কী বদলেছে, আর বিস্তারিত কোন ডকুমেন্টে।

এই ফাইল ২০২৬-১০-১০ থেকে রাখা হচ্ছে; তার আগের ইতিহাস Git-এ (`git log`)।

## ২০২৬-১০-১৬

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
