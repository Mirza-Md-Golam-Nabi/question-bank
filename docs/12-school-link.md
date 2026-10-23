# ১২ — School একাউন্ট ও School Management Software-এর সংযোগ

যে স্কুল আমাদের school management software নেয়, তার জন্য Question Bank-এ একটা **School একাউন্ট**। এই একাউন্ট স্কুলের জন্য প্রশ্ন বানায়, স্কুলের ছাত্রদের অনলাইন পরীক্ষা নেয়, আর নম্বর স্কুলের software-এর রেজাল্টে পাঠায়। নিয়মের উৎস CLAUDE.md-এর নিয়ম ১২।

এই কাজ ধাপে ধাপে হচ্ছে। চারটা ধাপই হয়েছে: একাউন্ট, approve, সংযোগ, স্কুলের পরীক্ষা ও ঢোকার ফর্ম (ফলাফল খোঁজাসহ), রেজাল্টে MCQ নম্বর পাঠানো, আর স্কুলের নিজের প্রশ্ন।

| ধাপ | কী | অবস্থা |
|---|---|---|
| ১ | School একাউন্ট, approve, সংযোগ, প্যানেলের লিংক | ✅ |
| ২ | স্কুলের পরীক্ষা ও ঢোকার ফর্ম (ক্লাস → সেকশন → রোল → নাম), লিংকে ফলাফল খোঁজা | ✅ |
| ৩ | রেজাল্টে নম্বর পাঠানো (মেলানো, পূর্ণমান, অনুপস্থিত) | ✅ |
| ৪ | স্কুলের নিজের প্রশ্ন ও Student প্যানেলে দেখা | ✅ |

## কে কী

- **School একাউন্ট:** স্কুলের Google একাউন্ট দিয়ে `/school` প্যানেলে লগইন। প্রতিটা স্কুলে একজন কম্পিউটার অপারেটর এটা চালান। স্কুলের জন্য যা কিছু (প্রশ্ন, পরীক্ষা, রেজাল্ট পাঠানো) শুধু এই একাউন্ট থেকে হয়।
- **স্কুলের Teacher ও Student:** আগের মতোই নিজের Google একাউন্ট আর নিজের subscription। স্কুলের software-এ শুধু Question Bank-এর লিংক পান। স্কুলের Teacher নিজের একাউন্টে স্কুলের কাজ করতে পারেন না।
- **Admin:** "Schools" পেজে (`SchoolResource`) নতুন School-এর দেওয়া তথ্য দেখে approve, suspend বা আবার চালু করেন। বাটনগুলো Staff-এর সাথে একই (`StaffLikeUserResourceSchema::statusActions()`)।

## একাউন্ট খোলা ও approve

1. "Continue with Google" (School প্যানেল) চাপলে নতুন user তৈরি হয়, `role=school`, `status=pending` (`UserRole::needsApproval()`, Staff-এর মতোই)।
2. Approve না হওয়া পর্যন্ত প্যানেলের সব পেজ "অনুমোদনের অপেক্ষায়" পেজে পাঠায় (`EnsureAccountIsApproved`, Staff-এর সাথে অভিন্ন)। সেখানে School স্কুলের নাম, EIIN, ঠিকানা ও ফোন দেয় (`SchoolDetailsForm`, `school_profiles`-এ)।
3. Admin approve করলে প্যানেল খোলে। এখন আছে "স্কুল সফটওয়্যারের সংযোগ" আর "আমার সাবস্ক্রিপশন"।
4. School referral বা wallet-এ নেই (`UserRole::holdsWallet()`)। School-এর plan আলাদা, Admin বানান (`SubscriptionTargetRole::School`)।

## সংযোগ

**Question Bank-এ** "স্কুল সফটওয়্যারের সংযোগ" পেজ (`School\Pages\SchoolConnection`) তিনটা জিনিস দেখায়: Question Bank-এর ঠিকানা, সংযোগ কোড (`QB-` + ১০ অক্ষর) আর secret (৬৪ অক্ষর, ডিফল্টে লুকানো, কপির বাটনসহ)। কোড আর secret একাউন্টের সাথে একবার তৈরি হয় (`SchoolProfile`), secret `encrypted`। "নতুন সিক্রেট বানান" চাপলে সংযোগ বন্ধ হয়, যতক্ষণ না নতুনটা স্কুলের software-এ বসানো হয়।

**স্কুলের software-এ** Admin প্যানেল → School Settings → Question Bank (`QuestionBankSettings`)। সেখানে তিনটা তথ্য বসিয়ে "সেভ করে যুক্ত করুন" চাপলে software একটা সই করা `POST /api/school/connect` পাঠায়, সাথে নিজের ঠিকানা (`installation_url`)। Question Bank সেটা যাচাই করে ঠিকানাটা আর সময় রাখে, স্কুলের নাম ফেরত দেয়। দুই পেজেই "যুক্ত আছে" দেখায়।

**সই:** দুই দিক একই secret দিয়ে `{timestamp}.{body}`-এর HMAC-SHA256 করে। Header হলো `X-School-Code`, `X-Timestamp` আর `X-Signature`। ৫ মিনিটের বেশি পুরনো অনুরোধ নেওয়া হয় না, আর secret কখনো পাঠানো হয় না। Question Bank-এ এই কাজ `SchoolConnectionSignature` আর `AuthenticateSchoolInstallation` (route `throttle:30,1`) করে, স্কুলের software-এ `HmacSignature` (central app-এর `CentralSignature`-ও এখন এটা ব্যবহার করে)। ভুল কোড বা secret, অথবা পুরনো অনুরোধ হলে 401। Approve না হওয়া বা suspended School হলে 403। স্কুলের software এর প্রতিটার জন্য আলাদা বাংলা বার্তা দেখায়।

**লিংক:** সংযুক্ত হলে স্কুলের software-এর মেনুতে "প্রশ্নব্যাংক" আসে, নতুন ট্যাবে খোলে। Admin প্যানেল থেকে `/school`, Teacher প্যানেল থেকে `/teacher`, Student প্যানেল থেকে `/student`-এ যায় (`QuestionBankConnection::navigationItem()`)।

## স্কুলের পরীক্ষা

- **বানানো:** School প্যানেলে Teacher-এর একই পেজগুলো চলে: "প্রশ্ন বাছাই" (`Teacher\Pages\SelectQuestions`), পরীক্ষার তালিকা, ফলাফল ও প্রশ্নভিত্তিক বিশ্লেষণ (`Teacher\Resources\Exams\ExamResource`), আর প্রিন্ট। নতুন কপি নেই। পেজগুলো যে প্যানেলে খোলা সেই প্যানেলের লিংক বানায় (`Support\CurrentPanel`)। গোনা, plan আর যাচাই Teacher-এর মতোই (`TeacherExamBuilder`, School-এর plan দিয়ে; exam-এর ধরন `teacher_exam`)।
- **কোনটা স্কুলের পরীক্ষা:** যেটা School বানিয়েছে (`Exam::isSchoolExam()`)।
- **লিংকে ঢোকা:** Login আর Guest-এর বদলে স্কুলের ফর্ম (`SchoolExamEntryController`, view `guest-exam.school-join`, ঘর `<x-school-student-fields>`)। ক্লাসের তালিকা স্কুলের software থেকে আসে, দর্শকের ভাষায়। কোনো ক্লাসে সেকশন থাকলে সেকশনের ড্রপডাউন দেখায় আর বাছা বাধ্যতামূলক। এরপর রোল আর নাম। Guest-এর `start`/`result` আর লগইনের `join` এই পরীক্ষায় কাজ করে না।
- **কে, কোন খাতা:** খাতায় থাকে ক্লাস, সেকশন (id ও নাম, স্কুলের software-এর), রোল আর `guest_name`। Account থাকে না (`is_guest`), তাই পরীক্ষার পাতা, autosave আর জমা Guest-এর পথেই হয় (`GuestAttemptSession`: এই ব্রাউজার শুধু নিজের শুরু করা খাতা খুলতে পারে)। একই ক্লাস, সেকশন, রোল আর নাম আবার এলে আগের খাতাই খোলে, আর জমা দেওয়া থাকলে ফলাফল দেখায়। একই রোলে অন্য নাম এলে নতুন খাতা হয়, রেজাল্ট পাঠানোর সময় School একটা বেছে নেবে। রোল এক রূপে রাখা হয় (`ExamAttempt::normalizeRoll()`): বাংলা অঙ্ক ইংরেজিতে, ফাঁকা আর শুরুর শূন্য বাদ।
- **ফলাফল খোঁজা:** একই লিংকে ক্লাস, সেকশন আর রোল দিলে প্রথম জমা দেওয়া খাতা দেখায়। লিংক বন্ধ হলেও কাজ করে। উত্তর দেখানোর নিয়ম Teacher-এর পরীক্ষার মতোই।
- **ফলাফল-তালিকা:** School-এর "Results" পেজে নামের পাশে "Class 6 · A · রোল 15" দেখায় (`participantContact()`)। খাতার পাতায় সেই ঘরের নাম "ক্লাস / রোল"।
- **ক্লাসের তালিকা:** `SchoolRoster` স্কুলের software-এর সই করা `GET /api/question-bank/classes` থেকে আনে। ১০ মিনিট সেটাই ব্যবহার হয়, কারণ পুরো স্কুল একসাথে লিংক খোলে। শেষ পাওয়া তালিকা রেখে দেওয়া হয়, তাই স্কুলের software সাময়িক বন্ধ থাকলেও ফর্ম চলে। কখনো আনা না গেলে "এই মুহূর্তে স্কুলের ক্লাসের তালিকা আনা যাচ্ছে না" পেজ দেখায় (`guest-exam.school-unavailable`)।
- **স্কুলের software-এ:** `AuthenticateQuestionBank` (কোড মিলতে হবে, secret দিয়ে সই, স্কুল যুক্ত থাকতে হবে), `BuildQuestionBankClassListAction` (চালু ক্লাস ক্রম অনুযায়ী, ইংরেজি ও বাংলা নাম, ক্লাসে সেকশন থাকলে চালু সেকশনগুলো)।

## রেজাল্টে নম্বর পাঠানো

School-এর পরীক্ষার তালিকায় অনলাইন পরীক্ষার পাশে "স্কুলের রেজাল্টে পাঠান" (`ExamsTable`, শুধু School প্যানেলে) → পেজ `School\Pages\SendToSchoolResults`। শুধু অনলাইন MCQ-এর নম্বর যায়, স্কুলের software-এর `student_results.mcq_marks` / `is_absent`-এ। পাঠান School নিজে, বাটন চেপে।

1. **কোথায় যাবে:** খাতাগুলো যে ক্লাসের (খাতা থেকে), তারপর স্কুলের software-এর সেই ক্লাসের পরীক্ষা (রেজাল্ট প্রকাশিত হলে বাছা যায় না), তারপর বিষয় (শুধু যে বিষয়ে MCQ আছে, পূর্ণমানসহ)। "ছাত্র-তালিকা আনুন" চাপলে ওই বিষয়ের এখনকার ছাত্র-তালিকা আসে (স্কুলের software-এর নিজের নিয়মে — `ResolveEligibleStudentsForSubject`), সাথে এখন রেজাল্টে কী আছে।
2. **পূর্ণমান না মিললে** একটা জানালা, দুটো পথ: "স্কুলের সফটওয়্যারে ঠিক করুন" (কিছু পাঠানো হয় না) অথবা "অনুপাতে বসান" (যেমন ৩-এ ২ → ৩০-এ ২০; দুই দশমিকে, পূর্ণমানের বেশি না)। পেজে তারপর প্রতিটা নম্বর দেখায়।
3. **মেলানো** ক্লাস + সেকশন + রোল দিয়ে (রোল এক রূপে), শুধু জমা দেওয়া খাতা (`SchoolResultMatcher`)। প্রতিটা ছাত্রের সারি:
   - **মিলেছে** — নাম মিলেছে ("মো./Md." ইত্যাদি আর যতিচিহ্ন বাদ দিয়ে, `SchoolResultMatcher::sameName()`), ডিফল্টে পাঠানো হবে।
   - **নাম মেলেনি** — দুই নাম পাশাপাশি; School "Approve" টিক দিলে তবেই যায়।
   - **এক রোলে একাধিক খাতা** — প্রতিটা খাতা (নাম ও নম্বর) radio-তে; একটা বাছলে তবেই যায়।
   - **অনুপস্থিত** — খাতা নেই; ডিফল্টে "অনুপস্থিত" যায়।
   - তালিকায় নেই এমন রোলের খাতা নিচে আলাদা দেখায়, পাঠানো হয় না।
4. **আগের নম্বর:** "স্কুলের রেজাল্টে আগে থেকে থাকা MCQ নম্বর বদলে দিন" টিক না দিলে যার আগে থেকে MCQ নম্বর আছে (বা অনুপস্থিত দাগানো) তাকে বদলানো হয় না। লিখিত বা ব্যবহারিক নম্বর থাকলে অনুপস্থিত দাগানো হয় না (স্কুলের software-এ অনুপস্থিত সব নম্বর মুছে দেয়)। একই নম্বর আবার পাঠালে কিছু বদলায় না (idempotent)।
5. **প্রকাশিত রেজাল্ট কখনো বদলায় না** — স্কুলের software 409 দেয়, পেজে বার্তা।
6. **পাঠানোর পর** কয়টা সেভ হলো আর কারা কেন বাদ গেল (রোল ও নামসহ) দেখায়। প্রতিটা পাঠানো `school_result_pushes`-এ থাকে (কে, কোথায়, পূর্ণমান, অনুপাতে কি না, কয়টা গেল/সেভ হলো, বাদের তালিকা) আর log-এ লেখা হয় (`SchoolResultPusher`); স্কুলের software-ও log রাখে।

**স্কুলের software-এ** (সব `auth.question-bank`, `throttle:60,1`):
- `GET /api/question-bank/classes/{class}/exams` — ক্লাসের পরীক্ষা (নতুন আগে) ও তার MCQ-ওয়ালা বিষয় (`BuildQuestionBankExamListAction`)।
- `GET /api/question-bank/exams/{exam}/subjects/{subject}/students` — বিষয়ের ছাত্র-তালিকা, এখনকার MCQ/অনুপস্থিত, লিখিত/ব্যবহারিক আছে কি না (`BuildQuestionBankMarkSheetAction`)।
- `POST /api/question-bank/exams/{exam}/subjects/{subject}/results` — নম্বর সেভ, এক transaction-এ (`SaveQuestionBankMcqMarksAction`, `SaveQuestionBankMarksRequest`); প্রতিটা বাদের কারণ ফেরত দেয়: বিষয় নেয় না, পূর্ণমানের বেশি, লিখিত/ব্যবহারিক আছে, আগে থেকে নম্বর আছে।

## স্কুলের নিজের প্রশ্ন

- **লেখা:** School প্যানেলের "আমার প্রশ্ন" Teacher-এর একই resource (`Teacher\Resources\Questions\QuestionResource`, শুধু নিজের প্রশ্ন — `ownedBy()`), JSON import-সহ (Teacher-এর মতোই Admin-এর সেটিং অনুযায়ী)। School-এর লেখা প্রতিটা প্রশ্ন নিজে থেকেই `is_private` (`QuestionObserver`), status সবসময় `pending`; তালিকায় review-এর status দেখায় না (`QuestionsTable::showsReview()`)।
- **কে দেখে:** শুধু ওই School। সাধারণ pool-এ কখনো না (`approvedPool()` আলাদাভাবেও বাদ দেয়), অন্য Teacher/School দেখে না, Admin-এর প্রশ্নের তালিকা, ডুপ্লিকেট-যাচাই আর রিভিউতে আসে না; Admin দেখতে, বদলাতে, approve বা reject করতে পারেন না (`QuestionPolicy::managesAsAdmin()`, `Question::approve()`/`reject()` ব্যতিক্রম ছোড়ে)।
- **পরীক্ষায়:** "প্রশ্ন বাছাই"-এ নিজের pending প্রশ্নের নিয়মেই আসে (`usableInExamBy()`)।
- **এডিট:** কোনো ছাত্র পরীক্ষা দেওয়ার আগে জায়গায় বদলায়; দেওয়ার পরে নতুন সংস্করণ হয় (`Question::editsMakeNewVersion()`), দেওয়া পরীক্ষায় পুরনোটা থাকে — উদ্দীপক গ্রুপেও (`StimulusGroupService`)।
- **ছাত্ররা কবে দেখে:** প্রশ্নের (সব সংস্করণের) যতগুলো পরীক্ষা, তার সবগুলোর শেষ সময় (`exams.link_expires_at`) পার হওয়ার পরের দিন থেকে। কোনো পরীক্ষায় শেষ সময় না থাকলে বা প্রশ্ন কোনো পরীক্ষায় না থাকলে, প্রথম সংস্করণ লেখার `school_question_release_days` দিন (ডিফল্ট ৬০) পরে। পরে নতুন কোনো আসন্ন পরীক্ষায় দিলে আবার বন্ধ, সেটা শেষ হওয়া পর্যন্ত। কোনো job নেই — দেখার মুহূর্তে হিসাব (`Question::scopeReleasedToSchoolStudents()`); দেখায় সর্বশেষ সংস্করণ।
- **কোথায় দেখে:** স্কুলের software-এর Student প্যানেলে "স্কুলের প্রশ্ন" (`Filament\Student\Pages\SchoolQuestions`, শুধু সংযুক্ত থাকলে)। পেজটা প্রতিবার একটা ২ ঘণ্টার সই করা লিংক বানায় (`QuestionBankConnection::schoolQuestionsUrl()`: `expires` + `"link:{code}"`-এর HMAC, ভাষাসহ) আর Question Bank-এর `GET /school-questions/{code}` iframe-এ দেখায়। Question Bank লিংক যাচাই করে (`SchoolConnectionSignature::verifyLink()` — মেয়াদ পার বা ২৪ ঘণ্টার বেশি হলে না; School approve-না-হওয়া/suspended হলে না, 403), শুধু স্কুলের software-এর ঠিকানাকে frame-এ দেখাতে দেয় (`Content-Security-Policy: frame-ancestors`), `throttle:120,1`।
- **পেজে:** ক্লাস · বিষয় (খোলা প্রশ্নের সংখ্যাসহ, যেকোনো ক্লাসের), তারপর অধ্যায় (বা সব অধ্যায়); পাতায় ২০টা, MCQ উদ্দীপকসহ তারপর CQ, উত্তর ও ব্যাখ্যাসহ — প্রশ্ন দেখানোর অভিন্ন view (`filament.support.questions.question-list`)। তালিকা `SchoolQuestionLibrary`, controller `SchoolQuestionsController`, view `school-questions.show`। কিছু খোলা না থাকলে সেটা বলে।

## কোড কোথায়

| কী | Question Bank | School Management |
|---|---|---|
| একাউন্ট ও প্যানেল | `UserRole::School`, `SchoolPanelProvider`, `app/Filament/School/` | — |
| তথ্য ও সংযোগের ডেটা | `Models\SchoolProfile`, `school_profiles` | `Support\QuestionBankConnection` (`school_settings`-এ, secret encrypted) |
| সই | `Support\SchoolConnectionSignature`, `Middleware\AuthenticateSchoolInstallation` | `Support\HmacSignature` |
| সংযোগ | `Api\SchoolConnectionController` (`routes/api.php`) | `Actions\QuestionBank\ConnectToQuestionBankAction`, `Filament\Pages\QuestionBankSettings` |
| Admin | `Resources\Schools\SchoolResource` | — |
| স্কুলের software-কে ডাকা | `Services\SchoolInstallationClient` | `Middleware\AuthenticateQuestionBank` (`api/question-bank/*`) |
| ক্লাস ও সেকশন | `Services\SchoolRoster` | `Actions\QuestionBank\BuildQuestionBankClassListAction`, `QuestionBankClassesController` |
| পরীক্ষায় ঢোকা ও ফলাফল খোঁজা | `SchoolExamEntryController`, `ExamAttempt::startForSchoolStudent()` / `findSchoolResult()`, `<x-school-student-fields>` | — |
| স্কুলের নিজের প্রশ্ন ও ছাত্রদের দেখা | `questions.is_private`, `Question::scopeReleasedToSchoolStudents()` / `editsMakeNewVersion()`, `Services\SchoolQuestionLibrary`, `SchoolQuestionsController`, `SchoolConnectionSignature::verifyLink()` | `Filament\Student\Pages\SchoolQuestions`, `QuestionBankConnection::schoolQuestionsUrl()` |
| রেজাল্টে নম্বর পাঠানো | `School\Pages\SendToSchoolResults`, `Services\SchoolResultMatcher`, `SchoolResultPusher`, `Models\SchoolResultPush` | `QuestionBankResultsController`, `Actions\QuestionBank\BuildQuestionBankExamListAction` / `BuildQuestionBankMarkSheetAction` / `SaveQuestionBankMcqMarksAction` |

## টেস্ট

- Question Bank: `SchoolExamTest` (Teacher-এর পেজে বানানো, স্কুলের ফর্ম ও সই করা অনুরোধ, শুরু ও আবার আসা, একই রোলে অন্য নাম, ক্লাস/সেকশনের যাচাই, ফলাফল খোঁজা, Login/Guest-এর পথ বন্ধ, তালিকার cache ও শেষ তালিকা, তালিকা না পাওয়া, ফলাফল-তালিকায় পরিচয়, রোলের রূপ), `SchoolAccountTest` (সাইনআপে pending, অপেক্ষার পেজ ও তথ্য, referral নেই, Admin-এর approve, কোড/secret, নতুন secret, সংযোগ — ঠিক, ভুল secret, অজানা কোড, পুরনো অনুরোধ, approve না হওয়া/suspended, ঠিকানা লাগে)।
- Question Bank: `SchoolQuestionsTest` (School-এর প্রশ্ন নিজের — pool/অন্য Teacher/অন্য School থেকে আলাদা, Admin-এর তালিকা/policy/ডুপ্লিকেট/approve-এ না, পরীক্ষা দেওয়ার পর নতুন সংস্করণ, খোলার দিন — শেষের পরের দিন, আসন্ন পরীক্ষায় আবার বন্ধ, শেষ সময় না থাকলে/পরীক্ষায় না থাকলে Admin-এর দিন, সংস্করণ মিলিয়ে, সই করা লিংকে উত্তরসহ পেজ ও frame-ancestors, ভুল সই/মেয়াদ পার/বেশি লম্বা মেয়াদ/suspended, কিছু খোলা না থাকলে, School-এর "আমার প্রশ্ন" পেজ)।
- Question Bank: `SchoolResultsTest` (বাটন শুধু School-এর অনলাইন পরীক্ষায়, অন্যের পরীক্ষা খোলে না, মেলানো — মিলেছে/নাম মেলেনি/এক রোলে একাধিক/অনুপস্থিত/তালিকায় নেই, নামের রূপ, অনুপাতের হিসাব, পূর্ণমানের জানালা দুই পথ, approve/বাছাই ছাড়া যায় না, পাঠানো ও রেকর্ড, বাদের কারণ, প্রকাশিত রেজাল্টের বার্তা, software বন্ধ থাকলে)।
- School Management: `QuestionBankSchoolQuestionsTest` (শুধু সংযুক্ত হলে, সই করা ২ ঘণ্টার লিংক ও ভাষা, iframe)।
- School Management: `QuestionBankResultsApiTest` (পরীক্ষা ও বিষয়ের তালিকা, ছাত্র-তালিকা, সেভ, অনুপস্থিত, বদলানোর অনুমতি, একই নম্বর আবার, লিখিত থাকলে অনুপস্থিত না, পূর্ণমানের বেশি, বিষয় না নেওয়া, প্রকাশিত হলে 409, সই ছাড়া না), `QuestionBankClassListApiTest` (চালু ক্লাস ও সেকশন ক্রমে, ভুল কোড/secret/পুরনো অনুরোধ, যুক্ত না থাকলে), `QuestionBankConnectionTest` (সই করা অনুরোধ, secret encrypted, প্রত্যাখ্যানের বার্তা, ফাঁকা secret রাখে, লিংক শুধু সংযুক্ত হলে), `CentralStudentReportTest` (অভিন্ন সইয়ে বদলের পরও)।
