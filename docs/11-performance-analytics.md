# ১১ — পারফরম্যান্স বিশ্লেষণ (Student ও Teacher)

জমা পড়া খাতা থেকে Student নিজের অবস্থা দেখে, আর Teacher তার ক্লাসের ও প্রত্যেক ছাত্রের অবস্থা দেখেন। নিয়মের উৎস CLAUDE.md-এর নিয়ম ১১।

## কে দেখে: শুধু পেইড plan

- Plan-এর নতুন **সুবিধা** `PlanFeature::Analytics` ("পারফরম্যান্স বিশ্লেষণ")। Admin plan-এর "সুবিধা" শর্তে টিক দেন। মনে রাখার কথা: যে plan-এ "সুবিধা" শর্তই নেই, সেখানে সব সুবিধা আছে, তাই analytics-ও আছে।
- যাচাই হয় শুধু `SubscriptionLimitService::allowsAnalytics()`-এ। এখানে গোনা হয় শুধু **subscription** (পেইড বা ট্রায়াল)। Role-এর **ফ্রি plan কখনো গোনা হয় না**, তার শর্তে যা-ই লেখা থাকুক। কোনো শ্রেণি বা বিষয়ের সাথে এটা বাঁধা না: সুবিধাটা পুরো একাউন্টের জন্য খোলে।
- **Plan না থাকলে** dashboard-এ লক করা preview দেখায় (`AnalyticsLockedWidget`, প্যানেলভেদে `StudentAnalyticsLockedWidget` / `TeacherAnalyticsLockedWidget`)। এতে বানানো সংখ্যা দিয়ে আঁকা ঝাপসা ছবি থাকে, আর থাকে "plan দেখুন" বাটন। তখন user-এর কোনো ডেটা পড়া হয় না। প্রতিটা analytics widget-এর `canView()` একই যাচাই করে (`NeedsAnalyticsPlan`), তাই লক আসলে সার্ভারে।
- আগের ফ্রি জিনিস ফ্রিই থাকে: "দুর্বল অধ্যায়" widget, "আমার ভুলগুলো অনুশীলন" আর "আমার ফলাফল"।

## হিসাবের নিয়ম

| মাপকাঠি | কীভাবে |
|---|---|
| স্কোর | খাতার `total_score` ÷ পরীক্ষার `total_marks` × ১০০ (`ExamAttempt::SCORE_PERCENT_SQL`); `total_marks` ০ হলে ধরা হয় না |
| স্কোরের ট্রেন্ড | সর্বশেষ N খাতা (`performance_trend_exams`), পুরনোটা আগে। **"আমার ভুলগুলো অনুশীলন" বাদ**: ওগুলো আগের ভুল প্রশ্ন দিয়ে বানানো, তাই কম স্কোর মানে দুর্বলতা নিয়ে কাজ, অবনতি না |
| এই মাস / গত মাস | এই মাসে কয়টা খাতা জমা পড়েছে (সব ধরনের), আর দুই মাসের গড় স্কোর (ভুলের অনুশীলন বাদ) |
| টানা দিন | আজ পর্যন্ত টানা যত দিন অন্তত একটা খাতা জমা পড়েছে। আজ এখনো না দিলেও গতকাল দিয়ে থাকলে streak টিকে থাকে |
| সঠিকের হার (বিষয়, অধ্যায়, কঠিনতা, সার্বিক) | প্রতিটা MCQ-এর **সর্বশেষ উত্তর**টাই গোনা হয়, সংস্করণ মিলিয়ে (`coalesce(parent_id, id)`)। আগের ভুল শুধরে নিলে সেটা আর বিপক্ষে গোনা হয় না। ফাঁকা মানে সঠিক না, আর আলাদা করে "ফাঁকা" দেখায়। এখানে সব ধরনের খাতা আসে, ভুলের অনুশীলনও |
| ন্যূনতম ডেটা | কোনো দলে `performance_min_questions`-এর কম প্রশ্ন থাকলে শতাংশের বদলে "আরও ডেটা দরকার" দেখায় (`PerformanceRate`) |
| কভারেজ | বিষয়ের অনুমোদিত (latest) MCQ-এর কত শতাংশ অন্তত একবার এসেছে |
| বিগত সালের প্রশ্নপত্র | স্কোরে আর বিষয়ের হারে আসে (পেপারে বিষয় থাকলে)। **অধ্যায়, কঠিনতা আর কভারেজে আসে না**, কারণ এর প্রশ্ন প্রশ্নভাণ্ডারের না (নিয়ম ৫.৪) |
| রঙ | ৭৫%+ সবুজ, ৫০%+ হলুদ, নিচে লাল (`PerformanceRate::colorFor()`) |

**উত্তর ফাঁস আটকানো:** Student-এর নিজের view-তে Teacher-এর পরীক্ষার স্কোর সাথে সাথেই আসে (জমা দিলেই student স্কোর দেখে)। কিন্তু প্রশ্নভিত্তিক হিসাবে (বিষয়, অধ্যায়, কঠিনতা) সেটা আসে **উত্তর প্রকাশের পর**, নইলে অধ্যায়ের হার থেকে উত্তর আন্দাজ করা যেত। নিয়মটা SQL-এ এক জায়গায় আছে: `Exam::whereShowsAnswersToStudents()`, যা `showsAnswersToStudents()`-এর সাথে মেলে। ভুলের তালিকাও (`StudentMistakeTracker`) এখন এটাই ব্যবহার করে।

## Student-এর dashboard

ওপরে ফিল্টার "বিশ্লেষণ কোন পরীক্ষা থেকে": সব (ডিফল্ট) / আমার নিজের অনুশীলন / শিক্ষকদের পরীক্ষা / বিগত সালের প্রশ্ন (`PerformanceSource`)। বিগত সালের পেপার যে-ই দিক, সেটা "বিগত সালের প্রশ্ন" হিসেবে গোনা হয়। ফিল্টার দেখায় শুধু analytics থাকলে।

| Widget | কী দেখায় |
|---|---|
| `PerformanceStatsWidget` | এই মাসের পরীক্ষা, এই মাসের গড় স্কোর (গত মাসের তুলনায় ↑↓), সার্বিক সঠিকের হার, টানা দিন |
| `ScoreTrendChartWidget` | স্কোরের রেখা |
| `DifficultyChartWidget` | প্রতিটা কঠিনতায় সঠিক / ভুল / ফাঁকা (stacked bar) |
| `SubjectPerformanceWidget` | বিষয়ভিত্তিক হার, দুর্বলটা আগে, কভারেজসহ। "অনুশীলন করো" বাটন Auto-Generate পেজে বিষয়টা আগে থেকে বেছে খোলে (`?subject=`)। বাটনটা দেখায় শুধু student-এর নিজের শ্রেণির বিষয়ে |
| `StudentWeakChaptersWidget` | আগের মতোই (ফ্রি) — [07-student-practice.md](07-student-practice.md) |

## Teacher-এর dashboard

**Teacher শুধু নিজের পরীক্ষার ডেটা দেখেন।** Student-এর self-practice বা অন্য Teacher-এর পরীক্ষা এখানে কখনো আসে না। ওপরে ফিল্টার "ক্লাসের বিশ্লেষণ": আমার সব পরীক্ষা (ডিফল্ট), অথবা Teacher-এর পরীক্ষার একটা শ্রেণি · বিষয়।

| Widget | কী দেখায় |
|---|---|
| `TeacherClassTrendChartWidget` | সর্বশেষ পরীক্ষাগুলোর গড় স্কোর (রেখা) আর অংশগ্রহণকারী (bar), **গেস্টসহ** |
| `TeacherClassWeakChaptersWidget` | ক্লাস যে ৫টা অধ্যায়ে সবচেয়ে খারাপ করে। সব উত্তর গোনা হয় (গেস্টসহ, প্রতিটা খাতা), আর অধ্যায়ে ন্যূনতম উত্তর থাকতে হয় |
| `TeacherStudentsNeedingAttentionWidget` | "মনোযোগ দরকার": (১) স্কোর টানা `performance_falling_exams` বার কমেছে, অথবা (২) `performance_inactive_days` দিন Teacher-এর কোনো পরীক্ষা দেয়নি, অথচ এর মধ্যে Teacher নতুন পরীক্ষা (draft না) দিয়েছেন। সবচেয়ে বেশি দিন অনুপস্থিত student আগে, সর্বোচ্চ ১০ জন |
| `TeacherStudentsPerformanceWidget` | "আমার ছাত্র": লগইন করা student, কয়টা পরীক্ষা, গড় স্কোর, শেষ পরীক্ষায় ↑↓, শেষ পরীক্ষার তারিখ। Paginated, আর হিসাব query-র ভেতরেই হয়। নাম চাপলে বিস্তারিত পেজ খোলে |

**Guest:** ক্লাসের হিসাবে (ট্রেন্ড, দুর্বল অধ্যায়) গোনা হয়। কিন্তু student-তালিকা, "মনোযোগ দরকার" আর বিস্তারিত পেজে আসে না, কারণ Guest প্রতিবার নাম ও যোগাযোগ নতুন করে লেখে, তাই দুই খাতা একই মানুষের কি না নিশ্চিত না।

**বিস্তারিত পেজ** `Teacher\Pages\StudentPerformanceReport` (`/teacher/students/{student}/performance`, মেনুতে নেই): Student-এর dashboard-এর একই widget-গুলো (`PerformanceStatsWidget`, ট্রেন্ড, কঠিনতা, বিষয়) আর সাথে `ChapterPerformanceWidget`, তবে `PerformanceScope::teacherView()` দিয়ে। এখানে শুধু এই Teacher-এর পরীক্ষা গোনা হয়, আর উত্তর প্রকাশের শর্ত নেই (Teacher নিজের পরীক্ষার সব উত্তর আগেই দেখেন)। যে student এই Teacher-এর কোনো পরীক্ষা দেয়নি তার পেজ (বা widget-এ সরাসরি `studentId` পাঠালে) 404 (`TeacherClassPerformance::hasStudent()`)। এখানে "অনুশীলন করো" বাটন নেই।

## কোড কোথায়

| কী | কোথায় |
|---|---|
| Student-এর সব হিসাব | `App\Services\StudentPerformance` (`summary()`, `scoreTrend()`, `overallRate()`, `subjectRates()`, `chapterRates()`, `difficultyRates()`, `streakDays()`) |
| কার খাতা, কার জন্য | `App\Services\PerformanceScope` (`ownView()`, `teacherView()`), উৎসের ফিল্টার `App\Enums\PerformanceSource` |
| ক্লাসের সব হিসাব | `App\Services\TeacherClassPerformance` (`classTrend()`, `weakChapters()`, `students()`, `directions()`, `needingAttention()`, `hasStudent()`) |
| একটা দলের হার | `App\Services\PerformanceRate` |
| কে দেখে | `SubscriptionLimitService::allowsAnalytics()`; widget-এ `Support\Widgets\Performance\Concerns\NeedsAnalyticsPlan` |
| Widget | `App\Filament\Support\Widgets\Performance\*` (দুই প্যানেলের অভিন্ন; Student প্যানেলে `->widgets()` দিয়ে যোগ), `App\Filament\Teacher\Widgets\TeacherClass*` / `TeacherStudents*` |
| Dashboard-এর ফিল্টার | `Student\Pages\Dashboard`, `Teacher\Pages\Dashboard`, দুটোই `Support\Pages\FilteredDashboard` থেকে। এটা ফিল্টার শুধু সেই widget-কে পাঠায় যে সেটা পড়ে। Filament সবাইকে পাঠায়, আর যে widget পড়ে না তার loading placeholder array-তে ভেঙে যেত |

"সর্বশেষ উত্তর" বের করতে `row_number() over (partition by …)` ব্যবহার হয়। এটা SQLite (৩.২৫+) ও MySQL (৮+) দুটোতেই চলে। লাইভের MySQL ৮-এর নিচে হলে এই query ভাঙবে।

## টেস্ট

`StudentPerformanceTest` (ট্রেন্ড, ভুলের অনুশীলন বাদ, সর্বশেষ উত্তর ও সংস্করণ, ফাঁকা, উত্তর প্রকাশের আগে লুকানো, বিগত সালের পেপার, ফিল্টার, Teacher-এর view, ন্যূনতম ডেটা, কঠিনতা, কভারেজ, streak, মাসের তুলনা, কে দেখে), `TeacherClassPerformanceTest` (ক্লাসের ট্রেন্ড গেস্টসহ, শুধু নিজের পরীক্ষা, দুর্বল অধ্যায়, student-তালিকায় গেস্ট নেই, শ্রেণি-বিষয় ফিল্টার, ↑↓, মনোযোগ দরকার), `PerformanceAnalyticsPagesTest` (লক, dashboard, বিস্তারিত পেজ, অন্যের student-এ 404), `QueryCountTest` (student-তালিকা ও বিষয়/অধ্যায়ের query-সংখ্যা)।
