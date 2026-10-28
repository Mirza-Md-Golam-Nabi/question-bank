# Changelog

নতুন পরিবর্তন ওপরে। প্রতিটা লাইনে: কী বদলেছে, আর বিস্তারিত কোন ডকুমেন্টে।

এই ফাইল ২০২৬-১০-১০ থেকে রাখা হচ্ছে; তার আগের ইতিহাস Git-এ (`git log`)।

## ২০২৬-১০-২৯

- **ব্যাখ্যার এডিটর মনে রাখা:** নতুন প্রশ্নের ব্যাখ্যা আর সবসময় Rich Text-এ শুরু হয় না — প্রশ্নের লেখার মতোই user শেষ যে এডিটরে ব্যাখ্যা লিখেছেন সেটা দিয়ে (আলাদা করে মনে রাখা; ফাঁকা ব্যাখ্যায় কিছু না, উদ্দীপকের গ্রুপে শেষ লেখা ব্যাখ্যার এডিটর)। → [05-question-bank.md](05-question-bank.md)

- **ব্যাখ্যায় দুই এডিটর:** MCQ-র ব্যাখ্যা এখন Rich Text (ডিফল্ট) বা CKEditor (সূত্রসহ) — "ব্যাখ্যা (ঐচ্ছিক)"-এর নিচে এক লাইনে বাছাই, নতুন কলাম `explanation_editor_mode` (প্রশ্নভাণ্ডার ও প্রশ্নপত্র; আগের ব্যাখ্যা CKEditor)। ফাঁকা এডিটরের ব্যাখ্যা = ব্যাখ্যা নেই। → [05-question-bank.md](05-question-bank.md), [03-database.md](03-database.md)
- **টেবিল:** দুই এডিটরেই টেবিল (CKEditor-এ নতুন, ঘরে সূত্রসহ), দেখানোর স্টাইলসহ; JSON ইমপোর্টে Markdown টেবিল (`| ক | খ |` + `|---|---|`), AI-এর ফরম্যাট গাইড ও নমুনা হালনাগাদ। → [05-question-bank.md](05-question-bank.md)
- **ডুপ্লিকেট যাচাইয়ের লুকানো বাগ:** `DuplicateQuestionFinder` প্রশ্নের হিসাব object-id দিয়ে মনে রাখত, PHP সেই id পরের নতুন object-কে দিয়ে দিলে ভুল প্রশ্নের হিসাব ব্যবহার হতে পারত — এখন `WeakMap`।

## ২০২৬-১০-২৮

- **Teacher/Staff/School-এর অধ্যায়ের প্রশ্নও কার্ড আকারে:** Admin-এর মতো কার্ড ↔ টেবিল দুই আকার এখন সব প্যানেলে (একই `ListQuestionsByChapterPage`); নিজের প্রশ্নে লেখকের নাম দেখায় না। → [05-question-bank.md](05-question-bank.md)

## ২০২৬-১০-২৭

- **JSON ইমপোর্টে ব্যাখ্যা পুরোপুরি ঐচ্ছিক:** MCQ-র `"explanation": ""` (ফাঁকা) আগে "the explanation is missing" বলে পুরো JSON আটকাত; এখন না দেওয়া/`null`/ফাঁকা সবই "ব্যাখ্যা নেই" — প্রশ্নভাণ্ডার (Admin/Teacher/School) ও প্রশ্নপত্র (Admin/Staff) সব জায়গায় (`QuestionJsonReader`)। প্রশ্নপত্রের CQ-অংশের ফাঁকা `answer`-ও একইভাবে। → [05-question-bank.md](05-question-bank.md)

## ২০২৬-১০-২৪

- **App install চালু:** `config/pwa.php`-এ dummy icon-এর লিংকের বদলে Next Fusion-এর লোগো (`public/images/pwa/`, ১৯২/৫১২/maskable), splash-এর রঙ লোগোর কালো — icon না থাকায় Chrome "Install" দেখাচ্ছিল না। → [13-app-and-offers.md](13-app-and-offers.md)

- **Admin-এর অধ্যায়ের প্রশ্ন কার্ড আকারে:** অধ্যায়ের প্রশ্ন-তালিকা এখন শুরুতে "প্রশ্ন বাছাই"-এর মতো পুরো প্রশ্নের কার্ড (উত্তর, ব্যাখ্যা, status, লেখক, approve/reject বাটনসহ); "টেবিল আকারে" বাটনে আগের টেবিল। সার্চ দুই আকারেই — প্রশ্নের লেখা, উদ্দীপক ও টপিক দিয়ে (সব প্যানেলের অধ্যায়ের তালিকায়; আগে শুধু টপিকের নাম মেলাত)। → [05-question-bank.md](05-question-bank.md)
- **শ্রেণিতে বিষয় যোগের খোঁজ:** Admin-এর "Attach subject" পপআপে এখন শর্ট নাম (আর অন্য ভাষার নাম) দিয়েও বিষয় খোঁজা যায় — আগে শুধু দেখানো নামে খুঁজত (`Subject::matching()`)।

## ২০২৬-১০-২৩

- **শুধু-ছবির MCQ অপশন:** অপশনে লেখা না দিয়ে শুধু ছবি দিলে এখন তৈরি ও এডিট দুটোই হয় (লেখা লাগে শুধু ছবি না থাকলে)। "দেখুন" জানালায় অপশনের ছবি দেখায় — আর প্রশ্ন/উদ্দীপক/CQ অংশের ছবিও, যা আগে ভাঙা দেখাত (ছবি খোঁজা হতো ভুল ডিস্কে)। ডুপ্লিকেট-যাচাই অপশনের ছবিও মেলায়। চার প্যানেলেই। → [05-question-bank.md](05-question-bank.md)
- **পরীক্ষার লিংকে ভাষা বদলালে তথ্য হারায় না:** গেস্ট/স্কুলের ফর্মে লেখা তথ্য, আর পরীক্ষার খাতায় টিক দেওয়া উত্তর (গেস্ট ও লগইন করা ছাত্র দুজনেরই) ভাষা বদলের পর আবার বসে (`<x-language-switcher>`, `window.keepExamAnswers()`)। → [06-exams.md](06-exams.md)
- **আবার পরীক্ষা নিন:** পরীক্ষার তালিকায় কপির বাটন (Teacher ও School) — একই প্রশ্নে অন্য ছাত্রদের জন্য নতুন লিংকসহ নতুন ড্রাফট পরীক্ষা, পুরনোটা ও তার ফলাফল অক্ষত; প্রশ্নের সর্বশেষ approved সংস্করণ যায়, reject/অপেক্ষমাণ বাদ; বিগত সালের পেপারও; plan-এর সীমায় নতুন পরীক্ষা হিসেবে গোনা (`ExamRepeater`)। "সাহায্য"-এ নতুন গাইড `take-an-exam-again`। → [06-exams.md](06-exams.md)
- **গাইড:** Admin-এর "গাইড" পেজ (বাংলা ও ইংরেজি, ছবি, YouTube ভিডিও, কাদের জন্য, কোন পেজে); Student/Teacher/Staff/School-এর মেনুতে "সাহায্য" (বিভাগ, খোঁজা); কয়েকটা পেজে "কীভাবে করবেন?" বাটন (পাশে জানালায় গাইড); ড্যাশবোর্ডে "শুরু করুন" তালিকা (নিজে টিক পড়ে, বন্ধ করা যায়, Admin-এর সেটিং)। ২৬টা শুরুর গাইড স্ক্রিনশটসহ (`GuideSeeder`)। নতুন টেবিল `guides`, কলাম `users.onboarding_dismissed_at`, সেটিং `onboarding_checklist_enabled`। → [14-guides.md](14-guides.md)
- **পরীক্ষার লিংকের বাটনের রঙ:** "হোম পেজ" indigo, "লগইন করে পরীক্ষা দিন" সবুজ, "আমার ফলাফল দেখুন" নীল (`qb-btn--success`, `qb-btn--info`)।

## ২০২৬-১০-২২

- **পরীক্ষার লিংক বাংলায় খোলে ও হোম পেজের বাটন:** আগে একই ব্রাউজারে প্যানেলে English বাছা থাকলে শেয়ার-লিংকের পরীক্ষার পাতাও English-এ খুলত। এখন পরীক্ষার পাতার ভাষার পছন্দ আলাদা — ডিফল্ট সাইটের ভাষা (বাংলা), পরীক্ষার পাতায় বদলালে শুধু সেখানেই খাটে। পরীক্ষার পাতায় (পরীক্ষা চলার পাতা বাদে) "হোম পেজ" বাটন। → [02-architecture.md](02-architecture.md), [06-exams.md](06-exams.md)

- **App (Next Fusion), অফার ও push নোটিফিকেশন:** ফোনে install করা যায় এমন একটাই app (`/manifest.webmanifest`, খোলে `/app`-এ — লগইন থাকলে নিজের ড্যাশবোর্ড, না থাকলে প্যানেল বাছাই; icon-এর লিংক `config/pwa.php`-এ বসাতে হবে)। হোম পেজে School-এর কার্ড। Admin-এর "অফার" পেজ: role ও Student-এর শ্রেণি বেছে, সময়সহ; হোম পেজে ও ড্যাশবোর্ডের ব্যানারে দেখায় ("×" চাপলে আর না), "পাঠান" চাপলে বেলে ও/অথবা ফোনে push — একবারই। নতুন package `laravel-notification-channels/webpush`, নতুন টেবিল `offers`, `offer_dismissals`, `push_subscriptions`। → [13-app-and-offers.md](13-app-and-offers.md)

- **স্কুলের নিজের প্রশ্ন (ধাপ ৪):** School প্যানেলে "আমার প্রশ্ন" (Teacher-এর একই পেজ, JSON import-সহ)। School-এর লেখা প্রশ্ন শুধু ওই School-এর (`questions.is_private`): pool, অন্য Teacher, Admin-এর তালিকা/রিভিউ/ডুপ্লিকেট-যাচাইয়ে আসে না, approve হয় না; ছাত্ররা পরীক্ষা দেওয়ার পর এডিট করলে নতুন সংস্করণ। ছাত্ররা স্কুলের software-এর Student প্যানেলে "স্কুলের প্রশ্ন"-এ উত্তর ও ব্যাখ্যাসহ দেখে — প্রশ্নের সব পরীক্ষা শেষ হওয়ার পরের দিন থেকে, শেষ সময় না থাকলে Admin-এর দিন (`school_question_release_days`, ডিফল্ট ৬০) পরে; স্কুলের software সই করা অল্প সময়ের লিংকে Question Bank-এর পেজ iframe-এ দেখায়। → [12-school-link.md](12-school-link.md), [09-admin-settings.md](09-admin-settings.md)

- **স্কুলের রেজাল্টে নম্বর পাঠানো (ধাপ ৩):** School-এর অনলাইন পরীক্ষার "স্কুলের রেজাল্টে পাঠান" — ক্লাস → স্কুলের পরীক্ষা → বিষয়, ক্লাস + সেকশন + রোল দিয়ে মেলানো, নাম না মিললে approve, এক রোলে একাধিক খাতা হলে বাছাই, খাতা নেই = অনুপস্থিত, পূর্ণমান না মিললে "স্কুলের সফটওয়্যারে ঠিক করুন" বা "অনুপাতে বসান", আগের নম্বর বদলাতে অনুমতি, প্রকাশিত রেজাল্ট বদলায় না। স্কুলের software-এ তিনটা নতুন সই করা API। নতুন টেবিল `school_result_pushes`। → [12-school-link.md](12-school-link.md)

- **স্কুলের পরীক্ষা (ধাপ ২):** School প্যানেলে Teacher-এর একই পরীক্ষার পেজ (প্রশ্ন বাছাই, তালিকা, ফলাফল, প্রিন্ট — প্যানেল অনুযায়ী লিংক)। স্কুলের পরীক্ষার লিংকে Login/Guest-এর বদলে স্কুলের ফর্ম: ক্লাস → সেকশন → রোল → নাম, ক্লাস/সেকশন স্কুলের software থেকে (সই করা API, cache ও শেষ তালিকা); ফলাফল খোঁজা ক্লাস + রোল দিয়ে; ফলাফল-তালিকায় "ক্লাস · সেকশন · রোল"। নতুন কলাম `exam_attempts.school_*`। → [12-school-link.md](12-school-link.md)

- **School একাউন্ট ও school management software-এর সংযোগ (ধাপ ১):** নতুন role School আর `/school` প্যানেল (Google লগইন, Admin approve করলে চালু, অপেক্ষার সময় স্কুলের তথ্য দেয়); Admin-এর "Schools" পেজ; সংযোগ কোড + secret, স্কুলের software-এর সই করা `POST /api/school/connect`; স্কুলের software-এর মেনুতে প্রশ্নব্যাংকের লিংক। Staff-এর অপেক্ষার middleware এখন অভিন্ন (`EnsureAccountIsApproved`)। নতুন টেবিল `school_profiles`। → [12-school-link.md](12-school-link.md)

## ২০২৬-১০-২১

- **Admin-এর প্রশ্ন রিভিউ দ্রুত:** অধ্যায়ের তালিকায় একসাথে অনেক প্রশ্ন approve (সম্ভাব্য ডুপ্লিকেট বাদ থাকে, কয়টা বাদ বলে দেয়) ও reject (এক কারণে); "দেখুন" জানালায় Approve / Reject / পরের প্রশ্ন — চাপলেই তালিকার পরের pending প্রশ্ন, আবার চোখে চাপতে হয় না। কোড `Support\QuestionReviewActions`। → [05-question-bank.md](05-question-bank.md)

- **Student-এর "নিজের প্র্যাকটিস পরীক্ষা বানান" এখন Teacher-এর "প্রশ্ন বাছাই"-এর মতো:** ড্রপডাউনে খোঁজার বদলে বিষয় → অধ্যায় → টপিক ধরে টিক, কয়টা প্রশ্ন, একাধিক অধ্যায়, এলোমেলো বাছাই, ফাইনাল ভিউ থেকে "পরীক্ষা শুরু করুন"; বাছার সময় উত্তর দেখায় না, শুধু MCQ (অনলাইন)। দুই পেজ এখন এক picker-এর ওপর (`QuestionPickerPage`, ভিউ `filament.support.pages.question-picker`); Self-practice-এর সব মোডে শুধু MCQ; একটাও প্রশ্ন না টিকলে খালি পরীক্ষা আর হয় না। → [07-student-practice.md](07-student-practice.md), [06-exams.md](06-exams.md)

- **Self-practice-এর সময় প্রশ্নের সংখ্যা থেকে:** আগে সবসময় ৩০ মিনিট ছিল। এখন প্রশ্নপ্রতি মিনিট × প্রশ্ন, ৫-এর গুণিতকে ওপরে তোলা (১০টা → ১০ মিনিট, ১১–১৫ → ১৫, ১৬–২০ → ২০)। দুটো সংখ্যাই Admin-এর সেটিং (`practice_minutes_per_question`, `practice_minutes_step`), তিন মোডেই। → [07-student-practice.md](07-student-practice.md), [09-admin-settings.md](09-admin-settings.md)

- **Self-practice-এ বিষয় খোঁজা তিন নামেই:** ইংরেজি, বাংলা বা সংক্ষিপ্ত নাম (যেমন "physics", "পদার্থ", "PHY") — যেটাই লেখা হোক, বিষয় খুঁজে পায়; আগে শুধু দেখানো নামে খুঁজত। → [07-student-practice.md](07-student-practice.md)

- **পারফরম্যান্স বিশ্লেষণ (Student ও Teacher, শুধু পেইড plan):** plan-এর নতুন সুবিধা "পারফরম্যান্স বিশ্লেষণ"। Student-এর dashboard-এ স্কোরের ট্রেন্ড, মাসের গড়, টানা দিন, বিষয় ও কঠিনতা অনুযায়ী হার আর কভারেজ (উৎসের ফিল্টারসহ)। Teacher-এর dashboard-এ ক্লাসের ট্রেন্ড, দুর্বল অধ্যায়, "মনোযোগ দরকার", ছাত্রের তালিকা, আর প্রত্যেক ছাত্রের বিস্তারিত পেজ (শুধু নিজের পরীক্ষা থেকে)। Plan না থাকলে লক করা preview দেখায়। নতুন সেটিং `performance_*`, নতুন index `exam_attempts(student_id, submitted_at)`। "উত্তর প্রকাশ হয়েছে কি না" SQL-এ এখন এক জায়গায় (`Exam::whereShowsAnswersToStudents()`)। → [11-performance-analytics.md](11-performance-analytics.md), [09-admin-settings.md](09-admin-settings.md)

- **"প্রশ্ন বাছাই"-এ "এলোমেলোভাবে বাছুন":** ফিল্টারের সব পাতা থেকে লক্ষ্য পর্যন্ত এলোমেলো প্রশ্ন; আর অন্য পাতায় গেলে পেজ তালিকার শুরুতে যায়, নিচে আটকে থাকে না। → [06-exams.md](06-exams.md)

- **Teacher প্যানেলে "একাউন্ট" মেনু-গ্রুপ:** আমার প্রোফাইল, আমার সাবস্ক্রিপশন, রেফারেল ও কমিশন — Student প্যানেলের মতোই; গ্রুপ ও ক্রম এখন তিন পেজের base class-এ (`MyProfilePage`, `MySubscriptionPage`, `ReferralsAndCommissionPage`), Staff-এর প্রোফাইল আগের মতো গ্রুপের বাইরে।

- **টেবিলের তারিখ পুরোটা বাংলায়:** বাংলা বাছলে মাসের সাথে অঙ্কও বাংলায় ("অক্টো ২০, ২০২৬ ২৩:২১:০৭"), ইংরেজিতে ইংরেজি — সব প্যানেলের সব তারিখ কলামে (`->localDate()` / `->localDateTime()`)।

- **Payments টেবিলে "ব্যবহার" কলাম:** প্রতিটা পেমেন্টের subscription-এ কয়টা পরীক্ষা, self-practice ও প্রশ্নপত্র — refund-এর সময় পার হলেও দেখা যায়। → [08-billing.md](08-billing.md)

## ২০২৬-১০-২০

- **Refund শুধু ব্যবহার না করা subscription-এ:** সময়সীমার ভেতরেও কিছু ব্যবহার হয়ে থাকলে refund হয় না; Admin-এর Refund জানালায় দেখায় এই subscription-এ কয়টা পরীক্ষা, self-practice ও প্রশ্নপত্র হয়েছে। → [08-billing.md](08-billing.md)

- **"দুর্বল অধ্যায়"-এ শতাংশ:** ভুলের সংখ্যার পাশে "ভুল (%)" — অধ্যায়ের যত প্রশ্ন পরীক্ষায় পেয়েছে তার কত শতাংশ এখনো ভুল (৫-এ ৩ = ৬০%), রঙে মাত্রা। → [07-student-practice.md](07-student-practice.md)

- **Student-এর পরীক্ষার পাতায় "পরীক্ষা" / "Take Exam Page" শিরোনাম নেই:** খাতা নিজেই শিরোনাম দেখায় (Guest-এর পাতার মতো); ব্রাউজারের ট্যাবে পরীক্ষার নাম।

- **Teacher-এর পরীক্ষার প্রশ্ন দেখা:** পরীক্ষার তালিকায় (আর Dashboard-এর সাম্প্রতিক পরীক্ষায়) চোখের আইকন — চাপলে পরীক্ষার সব প্রশ্ন ছবি, সঠিক উত্তর ও ব্যাখ্যাসহ। প্রশ্নপত্র পড়ার জানালার partial-টা সাধারণ নামে সরানো হয়েছে (`filament.support.questions.question-list`), দুই জায়গাতেই সেটা। → [06-exams.md](06-exams.md#পরীক্ষার-তালিকায়-teacher-এর-কাজ)

- **পরীক্ষার খাতায় ছবি, ছোট আকারে:** খাতা (Guest ও Student) আগে প্রশ্ন ও অপশনের ছবি দেখাতই না — এখন দেখায়। সব জায়গার ছবি এক component-এ (`<x-question-image>`), আকার সীমিত (প্রশ্নের ছবি সর্বোচ্চ ১০rem উঁচু, অপশনের ছবি ৫rem)। শুধু-ছবির অপশন বাছলে আগে উত্তর "ফাঁকা" ধরা হতো — এখন ছবি দিয়েই চেনা ও গ্রেড হয় (`HasMcqOptions`)। → [06-exams.md](06-exams.md#খাতা-জমা-ও-গ্রেডিং)

- **নোটিফিকেশন বেল (চার প্যানেলে) ও কেনার নোটিফিকেশন:** প্রতিটা প্যানেলের উপরে বেল (প্রতি ৬০ সেকেন্ডে খোঁজে)। Teacher/Student plan কিনতে চাইলে Admin-রা জানেন (টাকা, নম্বর, TrxID, Payments পেজের বোতাম); ক্রেতা জানেন পেমেন্ট পৌঁছেছে, চালু হয়েছে, reject (কারণসহ) বা refund হয়েছে। কোন Admin-রা পাবেন — Admin সেটিংসে। ফ্রি ট্রায়ালে কিছু না। নতুন টেবিল `notifications`, সেটিং `admin_notification_recipients`। → [08-billing.md](08-billing.md#বেলের-নোটিফিকেশন), [09-admin-settings.md](09-admin-settings.md)

- **JSON-এর সাথে ছবি:** "Add from JSON"-এ এখন ছবিও একসাথে — JSON-এ ফাইলের নাম (`"image": "q12.png"`; প্রশ্ন, অপশন, উদ্দীপক, CQ-র অংশ), আর জানালায় একটা ZIP বা ছবির ফাইলগুলো। কোনো প্রশ্নের ছবি না পেলে বা নেওয়া না গেলে নামসহ তালিকা দেখিয়ে যোগ আটকায়; অতিরিক্ত ছবির নামও তালিকায়। AI-কে দেওয়ার format/sample-এ ছবির নিয়ম ও উদাহরণ। অধ্যায় ও প্রশ্নপত্র দুই জায়গাতেই; নতুন সেটিং `question_import_image_max_kb`, `question_import_images_max_mb`। → [05-question-bank.md](05-question-bank.md#json-দিয়ে-একসাথে-প্রশ্ন-যোগ), [09-admin-settings.md](09-admin-settings.md)

## ২০২৬-১০-১৯

- **"View question"-এ ব্যাখ্যা ও উদ্দীপক:** MCQ-র ব্যাখ্যা (না থাকলে "ব্যাখ্যা নেই") আর গ্রুপের প্রশ্নের উদ্দীপক এখন View জানালায় দেখায় (`QuestionInfolist`) — আগে বাদ পড়েছিল।

- **সম্ভাব্য ডুপ্লিকেটের তালিকা থেকে প্রশ্নে যাওয়া:** "তবুও যোগ করবেন" তালিকায় "Question 101 — 91% alike"-এর পাশে ⬆ বাটন; চাপলে প্রিভিউ সেই প্রশ্নে চলে যায় ও প্রশ্নটা এক মুহূর্ত জ্বলে ওঠে। → [05-question-bank.md](05-question-bank.md#ডুপ্লিকেট-প্রশ্ন)

- **ডুপ্লিকেটের বাক্সে মিলে যাওয়া প্রশ্ন দেখার তীর:** "… উপরের ৪৩ নম্বর প্রশ্নের সাথে" বাক্সের ডানে ▾ — চাপলে সেই প্রশ্নটা সেখানেই খোলে (স্ক্রল করতে হয় না), আবার চাপলে বন্ধ। আগে থেকে থাকা প্রশ্নের বাক্সেও একই তীর, সেটা খোলা অবস্থায় শুরু। → [05-question-bank.md](05-question-bank.md#ডুপ্লিকেট-প্রশ্ন)

- **বিষয় ও অধ্যায়ের কার্ডে খোঁজা:** চার প্যানেলের প্রশ্নভাণ্ডারে বিষয় ও অধ্যায়ের পেজে খোঁজার ঘর, ব্রাউজারেই খোঁজে (সার্ভারে কিছু যায় না); বিষয় ইংরেজি/বাংলা/সংক্ষিপ্ত যেকোনো নামে। বিষয়ের কার্ড, শিরোনাম ও breadcrumb এখন বাংলায় বাংলা নাম দেখায়। → [05-question-bank.md](05-question-bank.md)

- **Google-এর ছবি দেখা যায়:** ছবির লিংক আগেও সেভ হতো কিন্তু কোথাও দেখানো হতো না। এখন Teacher/Staff/Student-এর প্যানেলের উপরের মেনুতে আর "আমার প্রোফাইল"-এ নাম-email-এর পাশে দেখায় (`User` এখন Filament-এর `HasAvatar`); ছবি না থাকলে (Admin) আগের মতো নামের অক্ষর। → [03-database.md](03-database.md)

- **ডুপ্লিকেট প্রশ্ন ধরা:** আগে শুধু হুবহু একই লেখা বাদ পড়ত (অপশন না দেখে, এক কমা আলাদা হলেই আবার ঢুকত)। এখন লেখা ও অপশন দুটো মিলিয়ে: হুবহু একই (যতিচিহ্ন/ফাঁকা/অঙ্কের রূপ/অপশনের ক্রম বাদে) কখনো যোগ হয় না; Admin-এর % (ডিফল্ট ৮৫) মিললে "সম্ভাব্য ডুপ্লিকেট" — JSON প্রিভিউতে আগের প্রশ্নের পাশে দেখিয়ে টিক বা "সবগুলো যোগ করুন" দিয়ে, ফর্মে "তবুও সেভ করুন" দিয়ে যোগ হয়; Admin approve-এর জানালায় দেখেন। সংখ্যা, সূত্র বা না-বাচক শব্দ আলাদা হলে কখনো ডুপ্লিকেট না। অধ্যায় ও প্রশ্নপত্র দুই জায়গাতেই; নতুন সেটিং `duplicate_similarity_percent`। → [05-question-bank.md](05-question-bank.md#ডুপ্লিকেট-প্রশ্ন), [09-admin-settings.md](09-admin-settings.md)

- **প্রশ্নভাণ্ডারের MCQ-তে ঐচ্ছিক ব্যাখ্যা:** `questions.explanation` — সাধারণ ফর্মে, উদ্দীপকের গ্রুপে ও JSON-এ (`"explanation"`); ঘরটা এখন একটাই (`QuestionFormSchema::mcqComponents()`), প্রশ্নপত্রও সেটাই ব্যবহার করে। এডিটে নতুন সংস্করণে কপি হয়। দেখায় শুধু সঠিক উত্তরের সাথে: self-practice-এর ফলাফলে সাথে সাথে, Teacher-এর পরীক্ষায় উত্তর প্রকাশের পর, প্রিন্টের "উত্তরসহ" কপিতে; পরীক্ষা চলার সময় কখনো না। CQ-তে নেই। → [05-question-bank.md](05-question-bank.md), [03-database.md](03-database.md)

## ২০২৬-১০-১৮

- **উদ্দীপকভিত্তিক MCQ:** একটা উদ্দীপকের নিচে ২–৬টা MCQ, প্রশ্নভাণ্ডারে ও বিগত সালের প্রশ্নপত্রে — ফর্মে, JSON-এ (`"stimulus"` + `"questions"`)। গ্রুপ একসাথে approve/reject ও এডিট (approved হলে পুরো গ্রুপের নতুন সংস্করণ)। পরীক্ষা, ফলাফল ও প্রিন্টে উদ্দীপক একবার, "নিচের উদ্দীপকটি পড়ে … নম্বর প্রশ্নের উত্তর দাও" শিরোনাম ক্রম দেখে নিজে বসে; এলোমেলো করার সময় গ্রুপ এক ব্লক। Teacher গ্রুপের যেকোনো কয়টা নিতে পারেন। → [05-question-bank.md](05-question-bank.md)

- **বিগত সালের প্রশ্নপত্রে "Add from JSON":** MCQ ও CQ তালিকার "New … question"-এর পাশে; প্রশ্নভাণ্ডারের মতোই format/sample কপি, প্রিভিউ ও যোগ। প্রতিটা তালিকা শুধু নিজের ধরন নেয়; MCQ-তে ঐচ্ছিক ব্যাখ্যা, CQ-তে ঐচ্ছিক উত্তর। Staff শুধু Admin চালু করলে (`staff_paper_import_enabled`)। JSON পড়া ও যাচাই এখন এক জায়গায় (`QuestionJsonReader`), প্রশ্নভাণ্ডারের ইমপোর্টও তা ব্যবহার করে — আচরণ অপরিবর্তিত। → [05-question-bank.md](05-question-bank.md), [09-admin-settings.md](09-admin-settings.md)

## ২০২৬-১০-১৭

- **বিগত সালের প্রশ্নপত্র (বোর্ড, মেডিকেল, বিশ্ববিদ্যালয়…):** উৎসের বাংলা/ইংরেজি পূর্ণ ও সংক্ষিপ্ত নাম; ভর্তি পরীক্ষার প্রশ্নপত্র = উৎস + সাল + ঐচ্ছিক ইউনিট, শ্রেণি/বিষয় ছাড়া; প্রশ্ন একটা একটা করে প্রশ্নভাণ্ডারের এডিটর দিয়ে তোলা, MCQ-তে ব্যাখ্যা ও CQ-তে উত্তর (ঐচ্ছিক)। Staff-ও তুলতে পারেন, approve হলে ধরন অনুযায়ী পেপারপ্রতি টাকা (`paper_rates`)। Student পুরো প্রশ্নপত্রে পরীক্ষা দেয় (সাথে সাথে উত্তর ও ব্যাখ্যা), Teacher পুরোটা পরীক্ষা হিসেবে নেন (অনলাইন/প্রিন্ট/দুটোই)। Plan-এ নতুন সুবিধা ও "বিগত সালের প্রশ্ন" শর্ত — ধরন, উৎস, কত বছরের পুরনো, প্রতি N দিনে X টা (চলমান গণনা, অন্য সীমা থেকে আলাদা)। প্রশ্নের পাশে উৎস ও সাল দেখায়। → [05-question-bank.md](05-question-bank.md), [08-billing.md](08-billing.md)
- **DRY:** প্রশ্নের ফর্মের অংশ (`QuestionFormSchema::textImageAndPreview()`, `editorModeRadio()`, `mcqComponents()`, `cqComponents()`), ফর্ম-ডেটার রূপান্তর (`QuestionFormData`), এডিটর পছন্দ মনে রাখা (`EditorModePreference::rememberFromForm()`), Admin কি না (`UserRole::isAdmin()`) — এক জায়গায়।

## ২০২৬-১০-১৬

- **Student প্যানেলের মেনু গ্রুপ:** পরীক্ষা (যোগ দিন, ফলাফল), অনুশীলন (অটো, নিজে বানানো, ভুলগুলো), একাউন্ট (প্রোফাইল, সাবস্ক্রিপশন, রেফারেল ও কমিশন) — গ্রুপগুলো `Support\NavigationGroup`-এ।

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
