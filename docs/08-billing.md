# ০৮ — Subscription, পেমেন্ট, Referral, Wallet ও Staff-এর আয়

নিয়মের উৎস: CLAUDE.md নিয়ম ৬ ও ৭। এখানকার প্রতিটা সংখ্যা (শতাংশ, দিন, বোনাস) Admin-এর সেটিং — [09-admin-settings.md](09-admin-settings.md)।

টাকার লজিক কখনো পেজ বা controller-এ থাকে না; সবই নিচের service ও model-এ।

## Plan ও তার শর্ত

দামের তালিকা পুরোপুরি Admin-এর হাতে (`SubscriptionPlanResource`)। কোডে কোনো plan বা দাম লেখা নেই।

**একটা plan** = নাম, কার জন্য (`teacher`/`student`), দাম, মেয়াদ, আর Admin-এর যোগ করা **শর্ত**।

- **মেয়াদ** (`duration_unit` + `duration_length`/`ends_on`): X দিন, X মাস, X বছর, একটা নির্দিষ্ট তারিখ পর্যন্ত ("পরীক্ষা পর্যন্ত" plan — সবার subscription ওই তারিখেই শেষ, আর তারিখ পার হলে plan আর বিক্রি হয় না), বা মেয়াদহীন (ফ্রি plan)।
- **শর্ত** (`conditions`): ফর্মে "শর্ত যোগ করুন" বাটন দিয়ে যোগ ও মুছে ফেলা যায়, প্রতিটা ধরন একবার। **যে বিষয়ে শর্ত নেই সেখানে কোনো সীমা নেই।**

| শর্ত | মানে |
|---|---|
| শ্রেণি | কোন শ্রেণিগুলো (ফাঁকা = সব); "ক্রেতা কয়টা বাছবে" দিলে ক্রেতা কেনার সময় তালিকা থেকে ততগুলো বাছে |
| বিষয় | কোন বিষয়গুলো (ফাঁকা = সব); একইভাবে ক্রেতা বাছতে পারে। বাছাই করা বিষয় plan-এর (বা ক্রেতার বাছাই করা) **সব** শ্রেণিতে পড়ানো হতে হবে — তাই "Class 6–10, ১টা বিষয়"-এ শুধু কমন বিষয় আসে, ৯–১০-এর বিভাগের বিষয় না। কমন কি না সিস্টেম `class_subjects` থেকে নিজেই বোঝে; তালিকা ও সার্ভারের যাচাই দুটোই `PlanConditions::pickableSubjectIds()` থেকে। শ্রেণি বাছতে হলে আগে শ্রেণি, তারপর বিষয়ের তালিকা |
| কঠিনতা | কোন difficulty-র প্রশ্ন ব্যবহার করা যাবে (যেমন ফ্রি = শুধু সহজ) |
| সুবিধা | অনলাইন পরীক্ষা (শেয়ার-লিংক), প্রিন্ট/PDF, বিগত সালের প্রশ্ন (পুরো প্রশ্নপত্রে পরীক্ষা) — টিক না থাকলে নেই |
| দিনে পরীক্ষা / মাসে পরীক্ষা | কতগুলো পরীক্ষা বানানো যাবে (group plan-এ প্রত্যেকের আলাদা) |
| প্রতি অনলাইন পরীক্ষায় অংশগ্রহণকারী | Teacher-এর একটা পরীক্ষা সর্বোচ্চ কতজন শুরু করতে পারে; সেভের সময় পরীক্ষায় কপি হয় (`exams.max_participants`) |
| আসন (plan ভাগ করা মানুষ) | ক্রেতাসহ কতজন — নিচে "Group plan" |
| বিগত সালের প্রশ্ন | কোন ধরনের প্রশ্নপত্র (বোর্ড/মেডিকেল/বিশ্ববিদ্যালয়…; ফাঁকা = সব), শুধু কোন উৎস (ফাঁকা = সব), কত বছরের পুরনো হতে হবে (যেমন ৫ = গত ৫ বছরের পেপার এই plan-এ নেই; ফাঁকা = সব সাল), আর **"প্রতি N দিনে X টা"** — Admin দিন ঠিক করেন (৭ = সপ্তাহ, ১৫ = পাক্ষিক, ৩০ = মাস)। গণনা **চলমান**: শেষ N দিনে কয়টা হয়েছে; সীমা শেষ হলে বলে দেয় পরেরটা কখন থেকে। এই সীমা self-practice ও পরীক্ষার দিনের/মাসের সীমা থেকে **আলাদা** — দুটো একে অন্যকে খায় না। Teacher ও Student দুজনের জন্যই একই নিয়ম; Student-এর প্রতিবার বসা, Teacher-এর প্রতিটা বানানো পরীক্ষা একটা করে গোনা। বোর্ডের পেপারে শ্রেণি/বিষয়ের শর্তও খাটে; ভর্তি পরীক্ষার পেপারে খাটে না। নিয়মের অর্থ শুধু `App\Services\PastPaperAccess`-এ, যাচাই ও গণনা `SubscriptionLimitService::authorizePastPaper()`-এ |

- **ফ্রি plan** (`is_default_free`): role-এর সবার জন্য, কেনা লাগে না।
- **ট্রায়াল** (`is_trial`): দাম শূন্য, একাউন্টপ্রতি একবারই; নিলে সাথে সাথে চালু (Teacher-এর ৭ দিনের ট্রায়াল)।
- **বিক্রি চলছে** (`is_active`): বন্ধ করলে আর কেনা যায় না, কেনা subscription চলতে থাকে।
- Admin-এর plan তালিকায় All / Student / Teacher ট্যাব। সব plan এক পাতায় থাকে (pagination নেই), ট্যাব শুধু ব্রাউজারে সারি লুকায় — সার্ভারে অনুরোধ বা নতুন query হয় না; ট্যাব বদলালে টিক দেওয়া সারি খালি হয়, যাতে লুকানো plan bulk delete-এ না পড়ে।

শর্তের ডেটা কীভাবে পড়া হয় তা শুধু `App\Services\PlanConditions`-এ।

### Group plan (কয়েকজন student, coaching-এর Teacher-রা)

`App\Services\SubscriptionMemberService` — একমাত্র জায়গা।

- "আসন" শর্তওয়ালা plan যে কেনে, সে "My Subscription" পেজে বাকিদের **Google email** দিয়ে যোগ করে, আসন পর্যন্ত (নিজেরটা ধরে)। প্রত্যেকে নিজের একাউন্টে ঢোকে — বিল ভাগ, একাউন্ট ভাগ না। একাউন্ট খোলার আগেও email যোগ করা যায়; ওই email-এ সঠিক role-এর একাউন্ট ঢুকলেই plan পায়।
- শুধু plan যে role-এর জন্য সেই role (Student-এর plan-এ Teacher না)।
- সদস্যের কাছে plan "শেয়ার করা" হিসেবে দেখায়, ক্রেতার নামসহ। শর্ত ও ক্রেতার বাছাই (শ্রেণি/বিষয়) সবার জন্য এক; দৈনিক/মাসিক গণনা প্রত্যেকের আলাদা, ফলাফল-পরিসংখ্যানও আলাদা।
- ক্রেতার subscription শেষ হলে সবার access শেষ (`User::activeSubscriptions()` শুধু চালু subscription খোঁজে)।
- কাউকে সরানো যায় Admin-এর দিনে একবার (`group_member_change_days`, ডিফল্ট ৩০), যাতে কয়েকটা আসন পুরো ক্লাসে পালা না হয়। খালি আসন যেকোনো সময় ভরা যায়।
- যোগ/সরানো log হয়।

### কে কী পারে (`SubscriptionLimitService`)

একজন user-এর অধিকার = তার **সব সক্রিয় subscription + role-এর ফ্রি plan** মিলিয়ে (প্রতিটা একটা `PlanGrant`)। কিছুই না থাকলে plan লাগে এমন কিছুই করা যায় না।

- **কেনা জিনিস বদলায় না:** subscription কেনার মুহূর্তে plan-এর শর্তের একটা কপি (`subscriptions.conditions`) আর ক্রেতার বাছাই (`subscriptions.selections`) রেখে দেয়। পরে Admin plan বদলালে আগের ক্রেতাদের কিছু বদলায় না।
- **যাচাই** (`authorize()`): এই শ্রেণি+বিষয়, এই সুবিধা, এই difficulty-গুলো — কোনো grant-এ আছে কি না, আর তার সীমা বাকি আছে কি না। না থাকলে কোনটা নেই সেটা বলে আটকায়।
- **প্রতিটা পরীক্ষা যে grant দিয়ে হলো তার ঘরেই গোনা হয়** (`exams.subscription_id`; ফাঁকা = ফ্রি plan)। তাই পেইড plan-এর পরীক্ষা ফ্রি plan-এর দিনের সীমা খায় না। আগে সীমাহীন grant, তারপর পেইড, শেষে ফ্রি — এই ক্রমে বাছা হয়।
- **গণনা:** আলাদা counter নেই; সরাসরি query (আজ ক'টা, এই মাসে ক'টা)। দিন বা মাস বদলালে নিজে থেকেই শূন্য।
- **ফোন বোনাস:** ফোন নম্বর দিলে মাসিক সীমাওয়ালা grant-এ Admin-এর বোনাস যোগ হয়; সীমাহীন সীমাহীনই থাকে।

**কোথায় দেখা হয়:**

| কাজ | কী লাগে |
|---|---|
| Teacher পরীক্ষা সেভ (`TeacherExamBuilder::build()`) | শ্রেণি+বিষয়, পরীক্ষার ধরন অনুযায়ী সুবিধা (অনলাইন/প্রিন্ট), প্রতিটা প্রশ্নের difficulty, আর সীমা — এখানেই একবার গোনা হয় |
| পরীক্ষা এডিট (`update()`) | একই, কিন্তু আবার গোনা হয় না |
| Publish / প্রিন্ট (`ExamPolicy`) | শুধু শ্রেণি+বিষয় আর অনলাইন/প্রিন্ট সুবিধা — আবার গোনা হয় না |
| "প্রশ্ন বাছাই" পেজ | শ্রেণি/বিষয়ের তালিকায় শুধু plan-এর ভেতরেরগুলো, প্রশ্নের তালিকায় শুধু অনুমোদিত difficulty; plan না থাকলে "plan লাগবে" বার্তা |
| Student self-practice (তিন মোড, `SelfPracticeExamService`) | নিজের শ্রেণির বিষয়, difficulty, সীমা — তিন মোড একই সীমায় |

- Guest বা Student যখন Teacher-এর শেয়ার-লিংকে পরীক্ষা দেয়, তার নিজের কোনো plan লাগে না — যেকোনো difficulty চলে।
- **Student-এর শ্রেণি** (`users.academic_class_id`) প্রোফাইলে থাকে। Self-practice সবসময় নিজের শ্রেণি থেকে; শ্রেণি না দিলে পেজ প্রোফাইলে পাঠায়। শ্রেণি বাছতে হয় এমন plan কিনলে শ্রেণির ঘরে প্রোফাইলের শ্রেণি আগে থেকে বসানো থাকে, বদলালে প্রোফাইলও বদলায়।

লোকালে শুরু করার দামের তালিকা: `SubscriptionPlanSeeder` (শুধু `APP_ENV=local`)। Group plan (coaching, কয়েকজন student) Admin "আসন" শর্ত দিয়ে বানান।

## ডিভাইস সীমা

`App\Services\DeviceSessionService` — একাউন্ট শেয়ার ঠেকাতে। Teacher ও Student একসাথে Admin-এর ঠিক করা কয়টা ডিভাইসে লগইন থাকতে পারে (`teacher_device_limit` ডিফল্ট ৩, `student_device_limit` ডিফল্ট ২; ০ = সীমা নেই)। Google দিয়ে লগইনের সময় এর বেশি হলে সবচেয়ে আগে ব্যবহৃত ডিভাইস থেকে লগআউট হয় (`sessions` টেবিল, তাই শুধু `SESSION_DRIVER=database`-এ)। প্রোফাইল পেজে নিজের ডিভাইসের তালিকা ("এই ডিভাইস" চিহ্নসহ) আর অন্যটা থেকে লগআউটের বাটন।

পরীক্ষা চলাকালীন এক ডিভাইসে আটকানো হয়নি — চলমান পরীক্ষায় অন্য ডিভাইস থেকে ফেরা এখনকার নিয়ম ([06-exams.md](06-exams.md))।

## Subscription কেনা

"My Subscription" পেজ (Teacher ও Student)। এখন কোনো payment gateway নেই: ক্রেতা bKash/Nagad/Rocket-এ টাকা পাঠিয়ে transaction id জমা দেয়, Admin মিলিয়ে approve করে।

পুরো প্রক্রিয়া `App\Services\SubscriptionPurchaseService`-এ; পেমেন্টের status শুধু এখান থেকেই বদলায়।

### দাম (`quote()`)

```
তালিকার দাম
 − referral discount   (শুধু প্রথম কেনায়, refer হয়ে এসে থাকলে)
 − wallet credit       (ব্যবহার করতে চাইলে; বাকি দামের বেশি না)
 = নগদে দিতে হবে
```

### চার ধাপ

| ধাপ | কে | কী হয় |
|---|---|---|
| `request` | ক্রেতা | পেমেন্ট `pending`। Wallet credit **সাথে সাথে কাটা হয়**, যাতে যাচাই চলাকালীন দুইবার খরচ না হয়। একসাথে একটাই pending পেমেন্ট থাকতে পারে। Credit-এ পুরো দাম মিটে গেলে যাচাইয়ের কিছু থাকে না, subscription সাথে সাথে শুরু। |
| `approve` | Admin | পেমেন্ট `success`; subscription শুরু; refund-এর সময়সীমা বসে; referrer-এর reward রেকর্ড হয়। |
| `reject` | Admin | টাকা আসেনি: পেমেন্ট `rejected`; কাটা wallet credit ফেরত। |
| `refund` | Admin | টাকা ফেরত দেওয়া হয়েছে (সিস্টেমের বাইরে): subscription এখনই শেষ; wallet credit ফেরত; referrer-এর reward বাতিল। **শুধু refund-এর সময়সীমার ভেতরে, আর subscription একেবারে ব্যবহার না হলে** (নিচে)। |

- **Idempotency:** প্রতিটা ধাপ একটা database transaction, যেটা আগে পেমেন্ট lock করে তার status আবার দেখে। দুইবার ক্লিক বা (ভবিষ্যতে) gateway-র callback দুইবার এলে দ্বিতীয়বার কিছু হয় না। একই transaction id দুইবার জমা দেওয়াও যায় না (unique)।
- **মেয়াদ জোড়া লাগে:** একই plan-এর সক্রিয় subscription থাকলে নতুনটার মেয়াদ আগেরটার শেষ থেকে গোনা হয়।
- **OTP:** Admin চালু করলে wallet credit খরচের সময় ফোনে কোড চাওয়া হয় (`OtpService`)। SMS provider যুক্ত না হওয়া পর্যন্ত এটা বন্ধ রাখার কথা।
- **Gateway এলে** শুধু `approve` ডাকার পথটা বদলাবে (Admin-এর জায়গায় callback)।
- প্রতিটা ধাপ log হয়।

### Refund-এর নীতি: ব্যবহার না করলে পুরো টাকা, ব্যবহার করলে কিছু না

- Refund হয় শুধু দুই শর্ত একসাথে মিললে (`Payment::isRefundable()`): Admin-এর সময়সীমার ভেতরে (`refund_window_days`), আর ওই subscription-এ **কিছুই গোনা হয়নি**। আংশিক refund নেই।
- "ব্যবহার" = ওই subscription-এ গোনা যেকোনো exam (`exams.subscription_id`) — Teacher-এর তৈরি পরীক্ষা, self-practice (তিন মোডই), বা বিগত সালের প্রশ্নপত্র (নিজে বসা বা পরীক্ষা হিসেবে নেওয়া)। Group plan-এ সদস্যদের ব্যবহারও ধরা হয়। তিন ভাগের সংজ্ঞা `Subscription::usageCounts()`-এ; একটার হিসাব `usage()`, পুরো তালিকার `withCount(Subscription::usageCounts('subscriptionExams'))` (এক query-তে), `hasBeenUsed()`।
- Payments টেবিলের **"ব্যবহার"** কলামে প্রতিটা পেমেন্টের subscription-এর এই তিন সংখ্যা সবসময় দেখায় — refund-এর সময় পার হলেও।
- Refund-এর শেষ সময় approve-এর মুহূর্তে তখনকার সেটিং দিয়ে বসে; পরে সেটিং বদলালে পুরনো পেমেন্টে বদলায় না।
- সময়সীমার ভেতরে Admin-এর Refund বোতাম সবসময় থাকে; জানালায় দেখায় "এই subscription-এ এখন পর্যন্ত: তৈরি করা পরীক্ষা / Self-practice / বিগত সালের প্রশ্ন"-এর সংখ্যা। কিছু ব্যবহার হয়ে থাকলে লাল লেখায় কারণ দেখায় আর নিশ্চিত করার বোতাম থাকে না; service-ও (`refund()`) আবার যাচাই করে আটকায়।

### বেলের নোটিফিকেশন

প্রতিটা ধাপ সত্যিই ঘটলে (ফেরত `true`, transaction শেষ) বেলে জানানো হয় — তাই দুইবার ক্লিক বা দুইবার callback-এ একবারই। ফ্রি ট্রায়ালে কিছু না।

| কখন | কাকে | কী |
|---|---|---|
| `request` (pending) | Admin-রা | "অমুক (Teacher) অমুক plan কিনতে চান" — টাকা, মাধ্যম, নম্বর, transaction ID; বোতামে Payments পেজ (pending ফিল্টার + ক্রেতার নাম) |
| `request` (pending) | ক্রেতা | "আপনার পেমেন্ট পৌঁছেছে, যাচাই চলছে" |
| `request` (পুরোটা wallet credit-এ) | Admin-রা | "অমুক অমুক plan কিনেছেন — wallet credit-এ, চালু" |
| `approve` | ক্রেতা | "আপনার plan চালু, … পর্যন্ত" |
| `reject` | ক্রেতা | কারণ (Admin-এর note, না থাকলে সাধারণ কথা) + wallet credit ফেরতের কথা |
| `refund` | ক্রেতা | plan শেষ, টাকা ফেরত + wallet credit ফেরতের কথা |

- **কোন Admin-রা:** Admin সেটিংসের "Admin-দের নোটিফিকেশন" অংশে প্রতিটা ধরনের জন্য বাছা যায় (`admin_notification_recipients`); কাউকে না বাছলে সব সক্রিয় Admin ও Super Admin।
- **ভাষা:** প্রতিটা নোটিফিকেশন প্রাপকের প্যানেলের ভাষায় লেখা হয় (Admin ইংরেজি, Teacher/Student বাংলা — `UserRole::defaultLocale()`), কারণ লেখাটা সেভ হয়ে থাকে। লিংক path হিসেবে সেভ, তাই ডোমেইন বদলালেও চলে।
- **সাথে সাথে পাঠানো** (`notifyNow`) — queue worker ছাড়াই পৌঁছায়।
- বেল চার প্যানেলেই (`PanelDefaults`), প্রতি ৬০ সেকেন্ডে নতুনটা খোঁজে।
- কোড: পাঠানোর একমাত্র পথ `App\Services\BellNotifier` (`toUser()`, `toAdmins()`); কেনার লেখাগুলো `App\Services\PurchaseNotifications`; Admin-এর ধরনের তালিকা `App\Enums\AdminNotificationTopic` (নতুন ধরন = একটা case)। টেস্ট: `PurchaseNotificationsTest`।

Admin-এর পেজ: `PaymentResource` (approve, reject, refund), `SubscriptionResource`, `SubscriptionPlanResource`।

## Referral

নিয়মগুলো `App\Services\ReferralService`-এ (কে refer করতে পারে, ছাড়, reward); একজন student কতদিন কার অধীনে থাকে তা `App\Services\StudentReferralService`-এ।

- **কে refer করতে পারে:** যেকোনো Teacher বা Student; আর সক্রিয় Staff।
- **কোড:** প্রত্যেকের নিজের ৮ অক্ষরের কোড (গুলিয়ে ফেলার মতো অক্ষর ছাড়া), প্রথমবার চাওয়ার সময় তৈরি। শেয়ার করার লিংক `/r/{code}`।
- **এক স্তর:** A যদি B-কে আনে আর B আনে C-কে, A শুধু B-র জন্য reward পায়।

### Student — একবারে একজন referrer, নির্দিষ্ট দিনের জন্য

একজন student একসাথে একজনেরই referral-এ থাকে (`student_referrals`), Admin-এর ঠিক করা দিন পর্যন্ত (`referral_lock_days`, ডিফল্ট ৯০), যেদিন সেট হয়েছে সেদিন থেকে। এরপর নিজে থেকেই শেষ হয় — কোনো scheduled job নেই, কিছু মোছা হয় না; পরের কোড বা পরীক্ষার লিংক নতুন একটা শুরু করে।

| কীভাবে সেট হয় | নিয়ম |
|---|---|
| **পরীক্ষার লিংক** | লগইন করা student Teacher-এর শেয়ার-লিংকের পরীক্ষা খুললে (`ExamAttempt::startFor()` → `attributeFromExam()`) ওই Teacher-এর অধীনে আসে, **সাথে সাথে লক**। Guest বা self-practice-এ কিছু হয় না। |
| **নিজে লেখা কোড** (প্রোফাইল) | Admin-এর দিন পর্যন্ত (`referral_change_days`, ডিফল্ট ১) বদলানো যায়, প্রথম লেখার সময় থেকে গোনা; বদলালেও শেষের তারিখ সরে না। তারপর লক। |
| **Referral লিংক দিয়ে signup** | নিজে লেখা কোডের মতোই (বদলানোর সময় আছে)। |

- লক থাকা referral-এর ওপর অন্য কোনো পরীক্ষার লিংক বা কোড বসে না। বদলানোর সময়ের মধ্যে Teacher-এর পরীক্ষার লিংক খুললে সেটাই বসে আর তখন থেকে লক।
- প্রোফাইলে থাকে শুধু নিজের কোড শেয়ার করা আর "আমার আয় দেখুন" লিংক; আয়ের হিসাব এক জায়গাতেই — Teacher/Student-এর "রেফারেল ও কমিশন" পেজ (কমিশন, wallet-এর হিসাব, উত্তোলন), Staff-এর "My Earnings"।
- Student প্রোফাইলে দেখে কার অধীনে আছে, কীভাবে, কত দিন বাকি, আর কতক্ষণ বদলানো যাবে। Teacher "রেফারেল ও কমিশন" পেজে তার ছাত্রদের দেখে (নাম ও ঢাকা email — `ab***@gmail.com`, পুরো email/ফোন কখনো না) ও কত দিন বাকি।

### Teacher — একবারই, signup-এর কোড দিয়ে

একজন Teacher-কে যে refer করে (`users.referred_by_id`), সেটা স্থায়ী; কোড দেওয়া যায় প্রথম কেনার আগ পর্যন্ত, আর ছাড় ও reward শুধু তার **প্রথম** কেনায়।

### ছাড় ও reward

- **কে referrer, কেনার মুহূর্তে ঠিক হয়** আর payment-এ লেখা থাকে (`payments.referrer_id`, `student_referral_id`) — পরে student-এর referrer বদলালেও কোন কেনা কার, তা বদলায় না।
- **ছাড়** (`referee_discount_percent`, ০ = বন্ধ): referral চলাকালীন student-এর **প্রতিটা** কেনায়; Teacher-এর প্রথম কেনায়।
- **Reward** (`rewardFor()`), পেমেন্ট approve হওয়ার সময়:
  - Student-এর কেনায়: referral চলাকালীন প্রতিটা কেনায় — অথবা Admin "শুধু প্রথম কেনায়" চালু রাখলে ওই referral-এর প্রথম কেনায় (`commission_first_purchase_only`)। Teacher-এর কেনায়: শুধু প্রথমটায়।
  - শুধু **নগদে দেওয়া** অংশের ওপর; wallet credit দিয়ে দেওয়া অংশে না (credit থেকে যেন আরও credit না জন্মায়)।
  - শতাংশ referrer-এর ধরন অনুযায়ী আলাদা: Teacher-এর কমিশন, Student-এর, Staff-এর। সেই মুহূর্তের শতাংশ reward-এ লেখা থাকে (`referral_rewards.percent`); পরে সেটিং বদলালে পুরনো reward বদলায় না।
  - **Teacher ও Student কমিশন পায় শুধু নিজের পেইড subscription চালু থাকলে** — ফ্রি ট্রায়ালে বা ফ্রি plan-এ না (`earnsCommission()`; Staff-এর এই শর্ত নেই)। না থাকলে কোনো reward তৈরি হয় না (log হয়), আর তার পেজে "কমিশন বন্ধ আছে" দেখায়।

**Reward-এর অবস্থা** (`referral_rewards`; তারিখ থেকে পড়া, সংরক্ষিত না):

| অবস্থা | মানে |
|---|---|
| maturing | পেমেন্টের refund-এর সময়সীমা এখনো চলছে |
| available | সময়সীমা শেষ; এখন এটা referrer-এর |
| paid | (Staff) payout-এ দেওয়া হয়েছে |
| cancelled | সময়সীমার ভেতরে refund হয়েছে; reward কখনো কারও হয়নি |

Refund শুধু সময়সীমার ভেতরে হয় বলে, পাকা হয়ে যাওয়া reward কখনো ফেরত নিতে হয় না।

**Reward কোথায় যায়:**
- Teacher ও Student → wallet-এ নগদ কমিশন, **মেয়াদ নেই**; নিচের নিয়মে তোলা যায়।
- Staff → নগদ, পরের payout-এ earning-এর সাথে।

**জালিয়াতির সংকেত:** পেমেন্ট যে নম্বর থেকে এসেছে সেটা ওই পেমেন্টের referrer-ও ব্যবহার করলে Admin-এর পেমেন্ট তালিকায় চিহ্নিত হয়।

Admin-এর রিপোর্ট: `ReferralReport` পেজ — প্রতি referrer কতজনকে এনেছে (refer করা Teacher + তার অধীনে আসা student, প্রত্যেকে একবার), কতজন কিনেছে, কত নগদ এসেছে (যে পেমেন্টের referrer সে), reward-এ কত খরচ।

## কমিশন তোলা (Teacher ও Student)

`App\Services\WithdrawalService` — একমাত্র জায়গা। পেজ দুই প্যানেলেই "রেফারেল ও কমিশন" (`Support\Pages\ReferralsAndCommissionPage`); Admin `WithdrawalResource` (Payroll → Withdrawals, Teacher/Student ফিল্টারসহ)। Staff তোলে না — তার reward payout-এর সাথে নগদে।

- wallet-এ role অনুযায়ী Admin-এর ঠিক করা একটা অঙ্ক **সবসময় থাকে** (`teacher_reserve_amount` / `student_reserve_amount`) — সেটা শুধু নিজের পরের subscription কিনতে খরচ হয়, কখনো তোলা যায় না। এর বেশি যা আছে, তা থেকে request করে তোলা যায়, সর্বনিম্ন `teacher_min_withdrawal` / `student_min_withdrawal`।
- **request**: অঙ্ক, bKash/Nagad/Rocket, নম্বর। টাকা সাথে সাথে wallet থেকে কাটা হয় (`wallet_transactions` → `withdrawal`), যাতে একই টাকা খরচ বা দুবার request না হয়। একসাথে একটাই অপেক্ষমাণ request।
- **জালিয়াতির সংকেত:** যে নম্বরে টাকা চাওয়া হচ্ছে, সেই নম্বর থেকে তার রেফার করা কোনো ক্রেতা পেমেন্ট করে থাকলে request-এ চিহ্ন থাকে (`number_used_by_referred_buyer`, request-এর মুহূর্তে) — Admin-এর তালিকায় লাল চিহ্ন। আটকায় না, শুধু দেখায়।
- **markPaid** (Admin): টাকা পাঠিয়ে transaction id দিয়ে চিহ্নিত। **reject**: কারণসহ; টাকা wallet-এ ফেরত (`withdrawal_return`)।
- প্রতিটা ধাপ এক transaction-এ, row lock করে আবার অবস্থা দেখে — দুবার ক্লিকে দ্বিতীয়বার কিছু হয় না। সব log হয়, নম্বর ছাড়া। নম্বর `encrypted` cast-এ সংরক্ষিত।

## Wallet

`App\Services\WalletService` — যোগ, খরচ ও গণনার একমাত্র জায়গা।

- Wallet শুধু Teacher ও Student-এর। এতে তার রেফারেল কমিশন জমে — subscription কিনতে খরচ করা যায়, আর reserve-এর বেশিটুকু নগদে তোলা যায়। **কোনো মেয়াদ নেই** (আগে মেয়াদসহ দেওয়া সারির মেয়াদ এখনো মানা হয়)।
- **Balance সংরক্ষিত না।** প্রতিবার হিসাব হয়: পাকা হওয়া reward − খরচ − তোলা + ফেরত। ফলে কোনো সংখ্যা তাল হারায় না, আর reward পাকানোর জন্য কোনো scheduled job লাগে না।
- `wallet_transactions`-এ শুধু সারি যোগ হয় (`purchase`, `purchase_return`, `withdrawal`, `withdrawal_return`); ভুল সংশোধনও নতুন সারি দিয়ে।

Admin-এর পেজ: `WalletTransactionResource`।

## ফোন নম্বর

প্রোফাইল পেজে user ফোন নম্বর দিতে পারে। এর জন্য মাসে বাড়তি ফ্রি পরীক্ষা পাওয়া যায় (Admin-এর সেটিং), আর wallet credit খরচে OTP চালু থাকলে নম্বরটা সত্যি হওয়া দরকার।

## Staff-এর আয়

### Earning

Staff-এর প্রশ্ন approve হওয়ার মুহূর্তে (`Question::approve()`):

- `question_rates` থেকে rate নেওয়া হয়: ওই বিষয়ের rate থাকলে সেটা, নইলে ডিফল্ট (`subject_id` ফাঁকা)।
- `staff_earnings`-এ একটা সারি তৈরি হয়, `amount` = **সেই মুহূর্তের rate**। পরে rate বদলালে এই সারি বদলায় না।
- Rejected প্রশ্নে কিছু তৈরি হয় না। Teacher-এর প্রশ্নে কখনো earning হয় না।
- `staff_profiles`-এর মোট সংখ্যাগুলো হালনাগাদ হয়।

Staff-এর JSON ইমপোর্ট নেই, যাতে প্রশ্নপ্রতি আয় AI দিয়ে শত শত প্রশ্ন ঢেলে না বাড়ানো যায়।

### Payout

`StaffPayout::createFor()` — একজন Staff-এর এখন পর্যন্ত বকেয়া সব একসাথে একটা batch-এ:

- বকেয়া earning (`pending_payout`) + পাকা হওয়া referral reward।
- একটা transaction-এ সারিগুলো lock করে; প্রতিটা earning `paid` হয় ও batch-এর সাথে যুক্ত হয়। তাই দ্বিতীয়বার চালালে একই টাকা আবার যায় না।
- আলাদা earning সরাসরি "paid" করা হয় না; সবসময় batch-এর মাধ্যমে।

Admin-এর পেজ: `StaffPayoutResource`, `QuestionRateResource`। Staff নিজের প্যানেলে দেখে: My Earnings, Monthly Earnings, Payout History, আর approved/pending প্রশ্নের breakdown।

### ব্যাংক তথ্য

`staff_profiles.bank_account_number` ও `mobile_banking_number` Laravel-এর `encrypted` cast দিয়ে সংরক্ষিত। এগুলো log, dump বা error message-এ কখনো সাধারণ লেখায় যাবে না।

## কোড ও টেস্ট

| কী | কোথায় |
|---|---|
| Plan, শর্ত, লিমিট | `app/Services/SubscriptionLimitService.php`, `PlanConditions.php`, `PlanGrant.php`, `app/Filament/Resources/SubscriptionPlans/SubscriptionPlanResource.php`, `<x-plan-usage>` |
| কেনা | `app/Services/SubscriptionPurchaseService.php`, `app/Models/Payment.php`, `Subscription.php`, `SubscriptionPlan.php` |
| Referral | `app/Services/ReferralService.php`, `StudentReferralService.php`, `app/Models/ReferralReward.php`, `StudentReferral.php`, `app/Http/Controllers/ReferralLinkController.php` |
| কমিশন তোলা | `app/Services/WithdrawalService.php`, `app/Models/Withdrawal.php`, `app/Filament/Support/Pages/ReferralsAndCommissionPage.php`, `app/Filament/Resources/Withdrawals/` |
| Wallet | `app/Services/WalletService.php`, `app/Models/WalletTransaction.php` |
| OTP | `app/Services/OtpService.php`, `app/Services/Sms/` |
| Staff | `app/Models/StaffEarning.php`, `StaffPayout.php`, `StaffProfile.php`, `QuestionRate.php` |
| পেজ | `app/Filament/Support/Pages/MySubscriptionPage.php`, `MyProfilePage.php`, `ReferralsAndCommissionPage.php` |

টেস্ট: `SubscriptionLimitServiceTest`, `SubscriptionPlanConditionsTest`, `SubscriptionPurchaseServiceTest`, `SubscriptionPurchaseFlowTest`, `SubscriptionResourcesTest`, `ReferralTest`, `StudentReferralTest`, `TeacherCommissionTest`, `StaffReferralTest`, `ReferralReportTest`, `WalletServiceTest`, `OtpServiceTest`, `StaffPayrollTest`, `StaffPayoutTest`, `StaffPayrollResourcesTest`।
