# ০৬ — পরীক্ষা

নিয়মের উৎস: CLAUDE.md নিয়ম ৭, ৮, ১০। Student-এর নিজের অনুশীলনের পরীক্ষা: [07-student-practice.md](07-student-practice.md)।

## পরীক্ষার ধরন

`exams.exam_type`:
- `teacher_exam` — Teacher বানায়, student ও Guest দেয়।
- `self_practice` — student নিজের জন্য বানায়।

`exams.delivery_mode` (Teacher-এর পরীক্ষায়):

| মোড | কোন প্রশ্ন | কীভাবে নেওয়া হয় |
|---|---|---|
| `online` | শুধু MCQ | শেয়ার-লিংক |
| `offline` | MCQ + CQ | প্রিন্ট / PDF |
| `both` | MCQ + CQ | প্রিন্টে সব প্রশ্ন; অনলাইনে শুধু MCQ |

CQ কখনো অনলাইনে নেওয়া হয় না, কারণ সিস্টেমে CQ-র উত্তর নেই। অনলাইনে কোন প্রশ্ন দেখাবে: `Exam::onlineQuestions()`।

## প্রশ্ন বাছাই পেজ (Teacher)

`App\Filament\Teacher\Pages\SelectQuestions` — পরীক্ষা তৈরি ও এডিট দুটোই এখান থেকে।

1. পরীক্ষার ধরন (delivery mode) বাছা।
2. Class → Subject → Chapter → Topic ফিল্টার। **এক পরীক্ষা = এক class + subject**; chapter যত খুশি মেশানো যায়। Class বা subject বদলাতে গেলে আগের বাছাই মুছে ফেলার অনুমতি চায়।
3. MCQ ও CQ-র জন্য আলাদা লক্ষ্য সংখ্যা Teacher নিজে দেয়; লক্ষ্য পূরণ হলে আর বাছা যায় না। সিস্টেমের নিজস্ব কোনো ব্যবসায়িক সীমা নেই, শুধু একটা কারিগরি সুরক্ষা-সীমা (`TeacherExamBuilder::MAX_QUESTIONS`)।
4. প্রশ্নের তালিকা (প্রতি পাতায় ২০টা) থেকে টিক। "এই পাতার সব বাছুন" বাটন পাতার প্রশ্নগুলো লক্ষ্য পর্যন্ত বাছে। **"এলোমেলোভাবে বাছুন"** ফিল্টারের সব পাতা মিলিয়ে (শুধু চোখের সামনের পাতা না) এলোমেলো প্রশ্ন বাছে — প্রতিটা ধরনের যতগুলো লক্ষ্য পূরণে বাকি, আগে বাছা প্রশ্ন বাদে; একটাই সার্ভার অনুরোধ (`SelectQuestions::randomQuestions()`, তালিকার একই query `filteredQuestions()` থেকে), ফল ব্রাউজারের বাছাইয়ে যোগ হয়, সেভের সময় আবার যাচাই। অন্য পাতায় গেলে পেজ তালিকার শুরুতে চলে যায়।
5. "Final view": বাছাই chapter ধরে একসাথে দেখা।
6. শিরোনাম ও সময় দিয়ে "Save as exam"।

**তালিকায় কী আসে:** approved pool, সাথে Teacher-এর নিজের pending প্রশ্ন ("আপনার — অনুমোদনের অপেক্ষায়" ব্যাজসহ)। অন্যের pending বা কারও rejected প্রশ্ন আসে না (`Question::scopeUsableInExamBy()`)। Topic ফিল্টার শুধু MCQ-তে খাটে।

**"শুধু যে প্রশ্নগুলো আমার ছাত্ররা ভুল করে" সুইচ:** তালিকাকে ওই Teacher-এর ছাত্ররা যেগুলো ভুল করেছে সেগুলোতে নামিয়ে আনে, সবচেয়ে বেশি ভুল হওয়াগুলো আগে। প্রতিটা প্রশ্নে ব্যাজ থাকে: "৪০ জনে ২৮ জন ভুল" (ইংরেজিতে "28 of 40 wrong"; ব্যাজের লোক-আইকন বোঝায় এটা Teacher-এর নিজের ছাত্রদের হিসাব)। হিসাবটা Teacher-এর সব পরীক্ষার সব অংশগ্রহণকারী (Guest-সহ) মিলিয়ে; আলাদা ব্যাচের হিসাব নেই। কোড: `App\Services\TeacherQuestionStats`।

**টিক দেওয়া পুরোপুরি ব্রাউজারে:** বাছাই `localStorage`-এ থাকে (শুধু প্রশ্নের id), গণনা ও সারাংশ Alpine-এ। সার্ভারে অনুরোধ যায় শুধু ফিল্টার বা পাতা বদলালে আর সেভের সময়। কারণ: অনেক Teacher একসাথে এই পেজ ব্যবহার করবে।

**ব্রাউজারকে বিশ্বাস করা হয় না:** সেভের সময় `TeacherExamBuilder` প্রতিটা id আবার যাচাই করে — Teacher-এর ব্যবহারযোগ্য, একই class-subject-এর, আর `online` হলে সব MCQ। একটাও না মিললে সেভ হয় না।

## বিগত সালের প্রশ্নপত্র পরীক্ষা হিসেবে (Teacher)

"বিগত সালের প্রশ্ন" পেজ থেকে Teacher একটা approved প্রশ্নপত্র পুরোটা পরীক্ষা হিসেবে নেন: ধরন (অনলাইন = MCQ, প্রিন্ট = MCQ + CQ, অথবা দুটোই), সময়, নেগেটিভ মার্ক। তৈরি হয় draft (`exams.board_question_paper_id`), তারপর এই তালিকা থেকে অন্য পরীক্ষার মতোই প্রকাশ, শেয়ার-লিংক, Guest, উত্তর প্রকাশ, ফলাফল, প্রিন্ট। প্রশ্ন বদলানো যায় না ("প্রশ্ন বাছাই"-এ খোলে না) — প্রশ্ন প্রশ্নপত্রের নিজের। Plan-এর "বিগত সালের প্রশ্ন" সীমায় গোনা হয়, সাধারণ পরীক্ষার সীমায় না। প্রশ্নের পাশে কোথা থেকে এসেছে ("ঢাবি 2023") দেখায়; শিরোনামে বিষয় না থাকলে উৎসের নাম (`Exam::heading()`)। লজিক `PastPaperExamService::createTeacherExam()`; বিস্তারিত [05-question-bank.md](05-question-bank.md)।

## এডিট ও বাতিল

- **এডিট:** একই পেজ (`?exam=`), একই যাচাই (plan-এর শ্রেণি/বিষয়/সুবিধা/difficulty সহ)। সীমায় আবার গোনা হয় না।
- **নেগেটিভ মার্কিং:** সেভের modal-এ (শিরোনাম ও সময়ের সাথে) টগল; চালু করলে প্রতি ভুল উত্তরে কাটা নম্বর, শুরুতে Admin-এর মান (`BillingSetting.default_negative_marks`, ডিফল্ট ০.২৫), Teacher বদলাতে পারেন। শুধু অনলাইনে নেওয়া পরীক্ষায় (প্রিন্টের খাতা হাতে দেখা হয়)। খাতার শিরোনামে student-কে জানানো হয় (`<x-exam-heading>`)। কেউ শুরু করার পর বদলানো যায় না (এডিটের নিয়মেই)।
- পরীক্ষা তৈরি ও এডিটের **একমাত্র** পথ এই পেজ: Teacher-এর `ExamResource`-এ কোনো create/edit ফর্ম-পেজ নেই (ড্যাশবোর্ডের "পরীক্ষা তৈরি করুন" আর "সাম্প্রতিক পরীক্ষা"-র সারিও এখানে আনে)।
- **কোনো student পরীক্ষা শুরু করার পর প্রশ্ন বদলানো যায় না** (`TeacherExamBuilder::canBeEdited()`), নইলে তার নম্বর আর প্রশ্নপত্র মিলবে না।
- **পরীক্ষা বাতিল** (`Exam::cancel()`): তখন একমাত্র পথ। সব attempt ও উত্তর স্থায়ীভাবে মুছে পরীক্ষাকে draft-এ ফেরায়; লিংক বন্ধ থাকে; পরে একই লিংকে আবার publish করা যায়। ফেরানো যায় না, তাই confirmation চায় ও log রাখে। ওই খাতাগুলো difficulty-র গণনা থেকেও বাদ যায়।

## Subscription লিমিট

পরীক্ষা **সেভ করার মুহূর্তে একবার** plan-এর সীমায় গোনা হয় (যে subscription অনুমতি দিল তার ঘরে — `exams.subscription_id`), অনলাইনে প্রকাশ হোক বা শুধু প্রিন্ট। সেভের সময় plan-এ পরীক্ষার শ্রেণি+বিষয়, ধরন অনুযায়ী সুবিধা (অনলাইন → শেয়ার-লিংক, অফলাইন → প্রিন্ট) আর প্রতিটা প্রশ্নের difficulty থাকতে হয়। পরে publish বা প্রিন্টের সময় শুধু দেখা হয় plan-এ এখনো সেই শ্রেণি+বিষয় ও সুবিধা আছে কি না (`ExamPolicy::publish()`/`print()` → `SubscriptionLimitService::allows()`), আবার গোনা হয় না। "প্রশ্ন বাছাই" পেজের শ্রেণি/বিষয়/প্রশ্নের তালিকাও plan অনুযায়ী ছাঁকা। বিস্তারিত: [08-billing.md](08-billing.md)।

## পরীক্ষার তালিকায় Teacher-এর কাজ

`App\Filament\Teacher\Resources\Exams\Tables\ExamsTable`:

| কাজ | কী করে |
|---|---|
| Publish | status `published` করে; প্রথমবার ৩২ অক্ষরের `share_token` তৈরি হয় |
| Copy share link | লিংক কপি |
| Set end time | `link_expires_at` — এর পর লিংক নতুন attempt নেয় না |
| View questions (চোখের আইকন) | পরীক্ষার সব প্রশ্ন একটা জানালায় — ছবি, উদ্দীপক, সঠিক উত্তর ও ব্যাখ্যাসহ; যেকোনো ধরনের পরীক্ষায় (অনলাইন পরীক্ষার প্রিন্ট নেই বলে এটাই দেখার পথ); Dashboard-এর "সাম্প্রতিক পরীক্ষা"-তেও। লেখা `filament.support.questions.question-list` (বিগত সালের প্রশ্নপত্র পড়ার জানালাও এটাই) |
| Results | ফলাফল-তালিকা |
| Release answers / Schedule answers / Hide answers | উত্তর প্রকাশ (নিচে) |
| Print | প্রিন্টের পেজ |
| Edit questions | প্রশ্ন বাছাই পেজে |
| Cancel exam | ওপরে |

## শেয়ার-লিংক: Login অথবা Guest

লিংক: `/exam/{share_token}`। এখানে student সবসময় দুটো পথ পায়; এটা বন্ধ করার কোনো সেটিং নেই।

- **Login করে:** `/exam/{token}/join` → দরকার হলে Google login → Student প্যানেলের পরীক্ষার পেজ।
- **Guest হিসেবে:** নাম + ফোন/ইমেইল দিয়ে। Guest-এর পেজগুলো Filament-এর বাইরে, সাধারণ রুট (`GuestExamController`)।

লিংক কাজ করে যতক্ষণ `Exam::isAcceptingAttempts()` সত্য: পরীক্ষা published, লিংক বন্ধ করা হয়নি, মেয়াদ পার হয়নি।

**Guest-এর পরিচয়** = নাম + ফোন/ইমেইল। `guest_contact` normalize করে রাখা হয় (ফাঁকা, হাইফেন, বন্ধনী বাদ; ছোট হাতের), নাম তুলনায় বড়-ছোট হাত ও বাড়তি ফাঁকা উপেক্ষা হয়। এই জোড়া দিয়েই Guest পরে ফিরে এসে নিজের ফলাফল খোঁজে, লিংক বন্ধ হয়ে গেলেও।

**Rate limit** (প্রতি মিনিটে): শুরু ৫, জমা ৫, ফলাফল খোঁজা ১০, পরীক্ষার পেজ ও উত্তর সেভ ৬০।

## পরীক্ষায় ঢোকা ও সময়

`ExamAttempt::enter()` — লগইন করা student ও Guest দুজনের জন্য একই নিয়ম:

- আগেই জমা দিয়ে থাকলে → সেই attempt, ফলাফল দেখানোর জন্য।
- চলমান attempt থাকলে → সেটাই, একই ঘড়ি ও সেভ করা উত্তরসহ; সময় শেষ হয়ে গিয়ে থাকলে যা সেভ ছিল তা-ই দিয়ে জমা হয়ে যায়।
- নইলে → নতুন attempt — তবে পরীক্ষাটা **পূর্ণ** হলে না (`Exam::isFull()`: `max_participants` জন আগেই শুরু করেছে)। তখন Guest শেয়ার-পেজে, লগইন করা student লিংক বা "পরীক্ষায় যোগ দিন" পেজে বার্তা দেখে। আগে যারা শুরু করেছে তারা ফিরতে পারে। সংখ্যাটা Teacher-এর plan-এর শর্ত থেকে ([08-billing.md](08-billing.md)); "পরীক্ষা বাতিল" attempt মুছে জায়গা খালি করে।

অর্থাৎ একজন একটা পরীক্ষা একবারই দেয়, একটাই ঘড়িতে; অন্য ডিভাইস থেকে ঢুকলেও নতুন করে শুরু হয় না। দুটো "শুরু" একসাথে এলে পরীক্ষার সারি lock করে পালা করানো হয়।

- প্রতিটা attempt প্রশ্নগুলো আলাদা ক্রমে দেখে (`shuffledQuestions()`, attempt-এর id থেকে নির্ধারিত, তাই reload-এ একই থাকে)। অপশনের ক্রম বদলায় না।
- সময় শেষ হলে পেজ নিজে উত্তর সেভ করে; এর পর ৩০ সেকেন্ডের ছাড় (`ANSWER_GRACE_SECONDS`) পর্যন্ত দেরিতে আসা উত্তর নেওয়া হয়।
- কোনো প্রশ্ন ফাঁকা রেখে জমা দিতে গেলে পেজ আগে জিজ্ঞেস করে।

Guest ও লগইন করা student হুবহু একই খাতা দেখে: `<x-exam-paper>` ও তার অংশগুলো দুই জায়গায় ব্যবহৃত।

## খাতা জমা ও গ্রেডিং

`ExamAttempt::submitAndAutoGrade()`:

1. প্রতিটা MCQ উত্তর সঠিক অপশনের সাথে মিলিয়ে `is_correct` ও `obtained_marks` বসে। উত্তর হিসেবে জমা থাকে অপশনের লেখা; শুধু-ছবির অপশনে (লেখা নেই) তার ছবির পথ — এই নিয়ম এক জায়গায় (`Concerns\HasMcqOptions::answerValueOf()`, `correctAnswerValue()`), খাতা, গ্রেডিং ও ফলাফল সবাই সেটাই পড়ে। **নেগেটিভ মার্কিং** থাকলে (`exams.negative_marks`) ভুল উত্তরে ততটা কাটা যায় (`obtained_marks` ঋণাত্মক); ফাঁকা উত্তরে কিছু কাটে না।
2. `total_score` = প্রাপ্ত নম্বরের যোগফল, কখনো ০-এর নিচে না; status `submitted`।
3. `QuestionDifficultyTracker` — প্রশ্নের difficulty-র গণনা (শুধু Teacher-এর পরীক্ষা)।
4. `recordStatistics()` — student-এর ভুলের তালিকা আর Teacher-এর পরিসংখ্যান।

প্রশ্ন যত বেশিই হোক, জমার query-সংখ্যা একই থাকে; একই খাতা দুইবার জমা পড়লেও হিসাবে একবারই ওঠে।

## উত্তর প্রকাশ

Teacher-এর পরীক্ষায় জমা দেওয়ার পর student **শুধু স্কোর** দেখে; প্রশ্ন, অপশন বা সঠিক উত্তর কিছুই না। কারণ: নইলে খালি খাতা জমা দিয়ে উত্তর জেনে আবার পরীক্ষা দেওয়া যায় (Guest-এর শুধু আরেকটা নাম লাগে)।

উত্তর দেখা যায় যখন (`Exam::showsAnswersToStudents()`):
- Teacher "Release answers" চাপে (`answers_released_at`), অথবা
- Teacher-এর ঠিক করা সময় আসে (`answers_release_at`) — কোনো scheduled job ছাড়া, ফলাফল দেখানোর মুহূর্তে সময় মিলিয়ে।

Self-practice-এ উত্তর সাথে সাথে দেখায়। Student নিজের পুরনো ফলাফলে ফেরে "My results" পেজ থেকে।

## ফলাফল (Teacher)

- **Results:** সবার তালিকা, নম্বর অনুযায়ী পজিশন। নিয়ম `App\Services\ExamResultSheet`-এ: একজন একবারই গোনা হয় (প্রথম জমা দেওয়া attempt); সমান নম্বরে একই পজিশন (১, ২, ২, ৪); একই পজিশনে যে আগে শেষ করেছে সে আগে।
- **Question analysis:** প্রশ্ন ধরে কতজন ভুল করেছে বা ফাঁকা রেখেছে, কারা।
- ফলাফল-তালিকা প্রিন্ট করা যায়।

## প্রিন্ট ও PDF

`/teacher/exams/{exam}/print` — প্রিন্ট-উপযোগী পেজ, "শুধু প্রশ্ন" ও "উত্তরসহ" দুই রূপে। PDF বানানো হয় ব্রাউজারের Print → "Save as PDF" দিয়ে; সার্ভারে PDF বানানো হয় না (বাংলা যুক্তাক্ষর ও সূত্র ভাঙে, সার্ভারে চাপ পড়ে)। শুধু পরীক্ষার মালিক দেখতে পারে।

## কোড ও টেস্ট

| কী | কোথায় |
|---|---|
| তৈরি/এডিটের যাচাই | `app/Services/TeacherExamBuilder.php` |
| পেজ | `app/Filament/Teacher/Pages/SelectQuestions.php`, `resources/views/filament/teacher/pages/select-questions.blade.php` |
| তালিকা ও কাজ | `app/Filament/Teacher/Resources/Exams/` |
| Model | `app/Models/Exam.php`, `ExamAttempt.php`, `AttemptAnswer.php` |
| অনুমতি | `app/Policies/ExamPolicy.php` |
| Guest | `app/Http/Controllers/GuestExamController.php`, `StudentSharedExamController.php` |
| Student-এর পেজ | `app/Filament/Student/Pages/TakeExamPage.php`, `ExamResultPage.php`, `MyResults.php`, `JoinExam.php` |
| ফলাফল | `app/Services/ExamResultSheet.php` |
| প্রিন্ট | `app/Http/Controllers/TeacherExamPrintController.php`, `TeacherExamResultsPrintController.php` |

টেস্ট: `TeacherSelectQuestionsTest`, `TeacherExamResourceTest`, `TeacherOwnPendingQuestionsOnExamTest`, `TeacherQuestionStatsTest`, `ExamModelTest`, `ExamAttemptGradingTest`, `ExamAnswerReleaseTest`, `GuestExamFlowTest`, `StudentExamFlowTest`, `TeacherExamResultsTest`, `TeacherExamPrintTest`, `QueryCountTest`, `SubscriptionPlanConditionsTest`।

## জানা ফাঁক

- CLAUDE.md নিয়ম ২.৪ বলে পরীক্ষায় প্রশ্ন যুক্ত করার সময় model-level Observer-এও যাচাই থাকবে। কোডে সেই Observer নেই; যাচাই আছে `TeacherExamBuilder`, `SelfPracticeExamService` আর ফর্মের relationship-এ। সরাসরি Eloquent দিয়ে `$exam->questions()->attach()` করলে কিছু আটকায় না।
