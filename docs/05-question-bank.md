# ০৫ — প্রশ্ন ব্যাংক

নিয়মের উৎস: CLAUDE.md নিয়ম ২, ৩, ৪, ৫।

## Content hierarchy

`Class → Subject → Chapter → Topic → Question`। Class, subject, chapter ও topic শুধু Admin তৈরি করে; Teacher ও Staff শুধু বেছে নেয়।

প্রতিটা প্যানেলের "প্রশ্ন" মেনু একই ধাপে খোলে: class-এর কার্ড → subject-এর কার্ড → chapter-এর কার্ড → ওই chapter-এর প্রশ্নের তালিকা। Admin-এর কার্ডে যোগ/এডিট/ডিলিটের মেনু থাকে (আর chapter-এর topic ম্যানেজ করার পেজ); Teacher ও Staff শুধু দেখে।

কার্ডে দেখানো প্রশ্নের সংখ্যা ওই প্যানেলের user যা দেখতে পারে শুধু তা-ই গোনে: Admin সবার, Teacher/Staff নিজের (`QuestionResourceBase::scopeCountedQuestions()`)।

## দুই ধরনের প্রশ্ন

| | MCQ | CQ (সৃজনশীল) |
|---|---|---|
| `question_text` | প্রশ্ন | উদ্দীপক |
| উত্তর | `options`-এ ২ থেকে ৬টা অপশন, ঠিক একটা সঠিক | সিস্টেমে উত্তর নেই |
| অংশ | নেই | চারটা: জ্ঞান, অনুধাবন, প্রয়োগ, উচ্চতর দক্ষতা (`question_cq_parts`) |
| নম্বর | লেখক দেয় (ডিফল্ট ১) | অংশগুলোর যোগফল; নিজে থেকে বসে, হাতে দেওয়া যায় না |
| অনলাইন পরীক্ষা | হ্যাঁ | না, শুধু প্রিন্টে |

অন্য কোনো ধরন (true/false, সংক্ষিপ্ত, বর্ণনামূলক) নেই।

## এডিটর ও গণিত

- প্রশ্নের লেখা দুই এডিটরের একটায় লেখা যায়: Rich Text অথবা CKEditor (সূত্রসহ)। কোনটা ব্যবহার হলো `editor_mode`-এ থাকে, আর user-এর শেষ পছন্দ মনে রাখা হয়।
- সূত্র সংরক্ষিত হয় সাধারণ HTML হিসেবে: `<span class="qb-katex-embed">latex</span>`। দেখানোর সময় `katex-embeds.js` সেটাকে আঁকে।
- CKEditor-এ সূত্র লেখা হয় MathLive-এর একটা modal-এ।
- MCQ-র অপশনও CKEditor-এ (সূত্র চলে); **CQ-র অংশগুলো Rich Text-এ**, যেটা সূত্র বোঝে না।

## প্রশ্নের জীবনচক্র

```
তৈরি ──► pending ──► approved ──► (এডিট) ──► নতুন সংস্করণ: pending
              └────► rejected
```

- **কে তৈরি করলে কী status:** Admin-এর প্রশ্ন সরাসরি `approved`; Teacher ও Staff-এর `pending` (`QuestionObserver::creating()`)।
- **Approve / reject:** শুধু Admin, প্রশ্নের তালিকার বাটন থেকে। Reject-এ কারণ লিখতে হয়। প্রতিটা সিদ্ধান্ত `question_approval_logs`-এ থাকে।
- **Staff-এর প্রশ্ন approve হলে** সেই মুহূর্তে তার earning যোগ হয়। → [08-billing.md](08-billing.md)

> **এখনকার অবস্থা:** rejected প্রশ্ন এডিট করলে তার status নিজে থেকে `pending`-এ ফেরে না; `rejected`-ই থাকে। আবার review-এ পাঠানোর আলাদা কোনো ধাপ কোডে নেই।

## কে কোন প্রশ্ন দেখে

| কে | কী দেখে |
|---|---|
| Admin | সব প্রশ্ন |
| Teacher, "আমার প্রশ্ন" | শুধু নিজের, যেকোনো status-এ |
| Teacher, "প্রশ্ন বাছাই" | approved pool + নিজের pending |
| Staff | শুধু নিজের |
| Student | শুধু পরীক্ষার ভেতরে |

অন্যের প্রশ্ন দেখা বা ব্যবহারের একমাত্র শর্ত approved pool: `status = approved` এবং `is_latest = true` (`Question::scopeApprovedPool()`)। এই ফিল্টার প্রতিটা প্যানেলের `getEloquentQuery()`-তে আর `QuestionPolicy`-তে আছে; শুধু UI লুকানো না।

## Versioning

Approved প্রশ্ন কখনো সরাসরি বদলায় না (`Question::createRevisionWith()`):

- পুরনো সারি অক্ষত থাকে, শুধু `is_latest = false` হয়।
- নতুন সারি তৈরি হয়: `status = pending`, `version + 1`, `parent_id` = প্রথম সংস্করণের id।
- CQ হলে চার অংশ নতুন সারির জন্য নতুন করে তৈরি হয়।
- নতুন সংস্করণ আগেরটার difficulty-র গণনা নিয়ে শুরু করে।

ফলে যে পরীক্ষায় পুরনো সংস্করণ আছে, সেটা যেমন ছিল তেমনই থাকে। Pending বা rejected প্রশ্ন এডিট করলে সরাসরি সেই সারিই বদলায় (নতুন সংস্করণ হয় না)।

## ডিলিট ও এডিটের সীমা

`QuestionPolicy`-তে:

- **ডিলিট (soft delete):** Teacher/Staff নিজের প্রশ্ন ডিলিট করতে পারে শুধু যতক্ষণ সেটা approved না **এবং** কোনো পরীক্ষায় নেই। Admin সবসময় পারে। স্থায়ী ডিলিট শুধু Admin-এর।
- **এডিট:** কোনো student ওই প্রশ্নসহ পরীক্ষা শুরু করার পর pending/rejected প্রশ্ন কেউ এডিট করতে পারে না, Admin-ও না (`Question::isFrozenByAttempts()`)। Approved প্রশ্ন এডিট করা যায়, কারণ সেটা নতুন সংস্করণ বানায়।
- একসাথে অনেকগুলো ডিলিট করলে প্রতিটা আলাদা করে যাচাই হয়; যেগুলো ডিলিটের যোগ্য না সেগুলো থেকে যায়।

> **জানা ফাঁক:** Admin পরীক্ষায় থাকা প্রশ্নও ডিলিট করতে পারে। তখন প্রশ্নটা ওই পরীক্ষা থেকে অদৃশ্য হয়ে যায় (প্রশ্নপত্র, উত্তরপত্র), কিন্তু পূর্ণমান ও জমা পড়া স্কোর বদলায় না।

## JSON দিয়ে একসাথে প্রশ্ন যোগ

Chapter-এর প্রশ্নের তালিকায় "JSON দিয়ে যোগ করুন" বাটন; AI দিয়ে বানানো প্রশ্ন দ্রুত তোলার জন্য।

**কে পারে** (`QuestionPolicy::import()`): Admin সবসময়; Teacher যতক্ষণ Admin সেটিংসে চালু রাখে; Staff কখনো না (তারা প্রশ্নপ্রতি টাকা পায়)। সবাইকেই `active` হতে হয়।

**ধাপ:**
1. Topic (ঐচ্ছিক) ও ডিফল্ট difficulty বাছা, JSON পেস্ট। ফরম্যাটের পুরো বিবরণ ও নমুনা পেজেই আছে, কপি বাটনসহ।
2. প্রিভিউ: প্রশ্নগুলো যেভাবে সংরক্ষিত হবে সেভাবে, সূত্র রেন্ডার করা। কোনো সূত্র ব্রাউজারে রেন্ডার না হলে যোগ করা আটকায়।
3. যোগ: প্রশ্নগুলো সাধারণ পথেই তৈরি হয়, তাই status-এর নিয়ম একই।

**ফরম্যাট:**

```json
[
  { "type": "mcq", "question": "...", "options": ["...", "..."], "answer": 2, "difficulty": "easy", "marks": 1 },
  { "type": "cq", "question": "উদ্দীপক", "parts": [ {"text": "...", "marks": 1}, {"text": "...", "marks": 2}, {"text": "...", "marks": 3}, {"text": "...", "marks": 4} ] }
]
```

**নিয়ম:**
- একটা প্রশ্নে ভুল থাকলে কিছুই সেভ হয় না; সব ভুল প্রশ্ন নম্বরসহ একসাথে দেখায়।
- `answer` = সঠিক অপশনের ক্রমিক (১ থেকে)। `type` না দিলে `mcq`। `difficulty` ও MCQ-র `marks` ঐচ্ছিক; CQ-র প্রতিটা অংশের `marks` বাধ্যতামূলক।
- সূত্র `$...$`-এর মাঝে LaTeX; JSON-এ প্রতিটা ব্যাকস্ল্যাশ দুইবার। সার্ভার যাচাই করে: `$` জোড়ায় আছে, বন্ধনী মেলে, `\left`/`\right` ও `\begin`/`\end` মেলে, সূত্রের ভেতরে বাংলা নেই, এবং একবার-লেখা ব্যাকস্ল্যাশের চিহ্ন (অদৃশ্য নিয়ন্ত্রণ-অক্ষর) নেই।
- সব লেখা escape হয়ে ঢোকে; JSON দিয়ে HTML ঢোকানো যায় না। ছবি পরে এডিট করে যোগ করতে হয়।
- user ওই chapter-এ যে প্রশ্ন আগে থেকে দেখতে পায়, বা একই পেস্টে দুইবার থাকা প্রশ্ন, বাদ পড়ে।
- একবারে সর্বোচ্চ প্রশ্নসংখ্যা Admin-এর সেটিং (ডিফল্ট ১০০)।

কোড: `App\Services\QuestionJsonImporter`, `QuestionImportText`, `App\Filament\Support\QuestionJsonImportAction`।

## Dynamic difficulty

MCQ-র `difficulty` শুরুতে লেখকের বাছাই; যথেষ্ট student উত্তর দেওয়ার পর সিস্টেম সেটা সঠিক উত্তরের হার থেকে ঠিক করে।

- **কী গোনা হয়:** Teacher-এর পরীক্ষায় জমা পড়া প্রতিটা খাতা; ওই পরীক্ষার প্রতিটা MCQ-র `answer_count` বাড়ে, সঠিক হলে `correct_answer_count`-ও। ফাঁকা = ভুল।
- **কী গোনা হয় না:** self-practice, আর CQ।
- **নিয়ম (ডিফল্ট সংখ্যায়):** ৩০টা উত্তরের পর থেকে — সঠিকের হার ৩৩% পর্যন্ত Hard, ৬৬% পর্যন্ত Medium, তার বেশি Easy। তিনটা সংখ্যাই Admin-এর সেটিং।
- **ফর্মে:** হিসাব-করা হয়ে গেলে difficulty ঘরটা শুধু দেখা যায়, বদলানো যায় না।
- **এডিটে:** নতুন সংস্করণ গণনা ও difficulty নিয়ে শুরু করে।
- **পরীক্ষা বাতিলে:** ওই পরীক্ষার খাতাগুলো গণনা থেকে বাদ যায়।
- **পুরনো হিস্টোরি মুছলে:** গণনা থাকে, কারণ সেটা প্রশ্নের নিজের সারিতে।
- একই খাতা দুইবার গোনা হয় না (`exam_attempts.difficulty_counted_at`)।

কোড: `App\Services\QuestionDifficultyTracker` (গণনা ও নিয়মের একমাত্র জায়গা), `Question::hasCalculatedDifficulty()`।

## বোর্ড প্রশ্নপত্র

সম্পূর্ণ আলাদা সাবসিস্টেম; সাধারণ প্রশ্নের ভাণ্ডার, পরীক্ষা বা self-practice-এর সাথে কখনো মেশে না।

- একটা পেপার = একটা উৎস + class-subject + সাল; একই সমন্বয়ে দুটো পেপার হয় না।
- **উৎস** (`boards` টেবিল, Admin-এ "Paper Sources") শুধু শিক্ষা বোর্ড না: ধরন (`type`, `PaperSourceType`) হতে পারে শিক্ষা বোর্ড, মেডিকেল ভর্তি, বিশ্ববিদ্যালয় ভর্তি, চাকরির পরীক্ষা, বা স্কুল/কলেজের টেস্ট পরীক্ষা। ভর্তি পরীক্ষার পেপারের জন্য Admin একটা শ্রেণি (যেমন "Medical Admission") আর তার বিষয় বানিয়ে নেন — কাঠামো একই থাকে। পেপারের ফর্মে উৎস ধরন অনুযায়ী ভাগ করে দেখায়; তালিকায় ধরন দিয়ে ছাঁকা যায়।
- পেপারের ভেতরে MCQ ও CQ আলাদা টেবিলে; CQ-র নম্বর এখানেও অংশের যোগফল।
- পুরো পেপার একসাথে `pending`/`approved`/`rejected` হয়, প্রশ্ন ধরে না।
- এখন এটা শুধু Admin প্যানেলে (`BoardQuestionPaperResource`, `BoardResource`)।

## কোড ও টেস্ট

| কী | কোথায় |
|---|---|
| Model ও নিয়ম | `app/Models/Question.php`, `QuestionCqPart.php`, `Concerns/SyncsMarksFromParts.php`, `Concerns/HasApprovalStatus.php` |
| অনুমতি | `app/Policies/QuestionPolicy.php` |
| ফর্ম, টেবিল | `app/Filament/Support/QuestionFormSchema.php`, `QuestionsTable.php`, `McqOptionsSchema.php` |
| প্যানেলের resource | `app/Filament/Resources/Questions`, `Teacher/Resources/Questions`, `Staff/Resources/Questions` |
| বোর্ড | `app/Models/Board*.php`, `app/Filament/Resources/BoardQuestionPapers`, `Boards` |

টেস্ট: `QuestionModelTest`, `AdminQuestionResourceTest`, `TeacherStaffQuestionVisibilityTest`, `QuestionSoftDeleteTest`, `CqMarksSyncTest`, `QuestionBankCardBrowserTest`, `TeacherQuestionBankCardBrowserTest`, `StaffQuestionBankCardBrowserTest`, `TopicTest`, `TopicResourceTest`, `ChapterResourceTest`, `AcademicHierarchyResourcesTest`, `QuestionJsonImportTest`, `QuestionDynamicDifficultyTest`, `TeacherOwnPendingQuestionsOnExamTest`, `BoardQuestionPaperModelTest`, `BoardQuestionPaperResourceTest`।
