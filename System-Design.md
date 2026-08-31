# Online Exam & Question Bank Management System — System Design

## ১. প্রজেক্ট সংক্ষিপ্ত বিবরণ

এটি একটি **৪-প্যানেল** ভিত্তিক Question Bank + Exam Management + Monetization প্ল্যাটফর্ম:

| প্যানেল | মূল কাজ |
|---|---|
| **Admin** | প্রশ্ন আপলোড, Teacher/Staff-এর তৈরি প্রশ্ন রিভিউ ও অ্যাপ্রুভ/রিজেক্ট, Staff পারিশ্রমিক পরিশোধ, Subscription প্ল্যান ম্যানেজমেন্ট, সিস্টেম রিপোর্ট/রেভিনিউ |
| **Teacher** | Approved পুল থেকে প্রশ্ন বেছে exam বানানো, নতুন প্রশ্ন এড করা (approval-pending), exam-এর শেয়ারযোগ্য লিংক জেনারেট করা, exam নেওয়ার জন্য Subscription |
| **Staff** | শুধুই প্রশ্ন এড করা (content-only role) — subject-ভিত্তিক নিজের অবদান ও উপার্জন দেখা |
| **Student** | Teacher-এর শেয়ার করা লিংকে (Login বা Guest দুইভাবেই) exam দেওয়া; নিজের Subscription থাকলে নিজে থেকে (self-practice) exam জেনারেট করে দেওয়া |

**মূল বিজনেস রুলসমূহ:**
1. Teacher বা Staff যখন নতুন প্রশ্ন যোগ করবে, status হবে `PENDING`। Admin অ্যাপ্রুভ না করা পর্যন্ত সেই প্রশ্ন **শুধু owner (যে বানিয়েছে) ও Admin দেখতে পারবে** — অন্য কোনো Teacher/Staff দেখতে পারবে না।
2. Staff-এর প্রশ্ন **approve হলেই** তার পারিশ্রমিক ledger-এ যোগ হবে (rejected প্রশ্নের জন্য কোনো টাকা না)।
3. Teacher/Student-এর **subscription না থাকলে বা মাসিক ফ্রি-লিমিট শেষ হয়ে গেলে** নতুন exam create/publish বা self-practice exam জেনারেট করতে পারবে না (Freemium মডেল — নিচে বিস্তারিত)।
4. Exam-এর শেয়ার লিংকে Student **Login করেও ঢুকতে পারবে, আবার Guest হিসেবেও** (শুধু নাম দিয়ে) ঢুকতে পারবে — দুটোই সবসময় সাপোর্টেড থাকবে।

---

## ২. Actor ও Role-ভিত্তিক Permission Matrix

| Action | Admin | Teacher | Staff | Student (Logged-in) | Student (Guest) |
|---|---|---|---|---|---|
| প্রশ্ন আপলোড/তৈরি করা | ✅ (auto-approved) | ✅ (pending) | ✅ (pending) | ❌ | ❌ |
| নিজের pending/rejected প্রশ্ন দেখা | ✅ | ✅ | ✅ | ❌ | ❌ |
| Approved পুল থেকে প্রশ্ন দেখা/বাছাই | ✅ | ✅ | ❌ (দরকার নেই) | ❌ | ❌ |
| প্রশ্ন approve/reject করা | ✅ | ❌ | ❌ | ❌ | ❌ |
| নিজের subject-wise contribution/earning দেখা | — | — | ✅ | — | — |
| Staff payout process করা | ✅ | ❌ | ❌ | ❌ | ❌ |
| Subscription plan তৈরি/এডিট করা | ✅ | ❌ | ❌ | ❌ | ❌ |
| Exam তৈরি ও শেয়ার লিংক জেনারেট | ✅ | ✅ (active subscription/free-limit সাপেক্ষে) | ❌ | ❌ | ❌ |
| শেয়ার লিংকে exam attempt দেওয়া | ❌ | ❌ | ❌ | ✅ | ✅ |
| Self-practice exam জেনারেট করা | — | — | — | ✅ (active subscription/free-limit সাপেক্ষে) | ❌ (লগইন বাধ্যতামূলক) |
| ফলাফল দেখা | ✅ (সব) | ✅ (নিজের exam-এর) | ❌ | ✅ (নিজের, পরে আবার লগইন করেও) | ✅ (শুধু জমা দেওয়ার সাথে সাথেই) |

---

## ৩. Question Approval — State Machine

```
                ┌────────────┐
 Teacher/Staff  │            │
   adds ───────▶│  PENDING   │
                │            │
                └─────┬──────┘
                      │ Admin action
        ┌─────────────┼──────────────┐
        ▼                            ▼
 ┌────────────┐               ┌────────────┐
 │  APPROVED  │               │  REJECTED  │
 │ (visible   │               │ (visible   │
 │ to owner+  │               │ only to    │
 │ all        │               │ owner +    │
 │ Teacher,   │               │ admin)     │
 │ Staff হলে  │               └─────┬──────┘
 │ earning    │                     │ edit & resubmit
 │ ledger-এ   │                     └──────▶ back to PENDING
 │ যোগ হবে)   │
 └────────────┘
```

- Admin নিজে যা আপলোড করে তা সরাসরি `APPROVED`।
- Teacher বা Staff-এর তৈরি প্রশ্ন default `PENDING`, `created_by` ফিল্ড দিয়েই owner ট্র্যাক হবে (role আলাদা করে না রেখে — `users.role` থেকেই বোঝা যাবে owner Teacher না Staff)।
- `REJECTED` হলে owner কারণ (rejection_reason) সহ notification পাবে, এডিট করে আবার সাবমিট করতে পারবে (`PENDING`-এ ফিরে যাবে)।
- **Staff-এর প্রশ্ন `APPROVED` হওয়ার মুহূর্তেই** একটা `staff_earnings` row তৈরি হবে (নিচে দেখুন সেকশন ৬)।
- Approved প্রশ্ন এডিট করলে নতুন version (`parent_id`/`version`/`is_latest`) তৈরি হয়ে আবার `PENDING`-এ যায়, পুরনো row অক্ষত থাকে — পুরনো exam-গুলোর data integrity বজায় থাকার জন্য।

---

## ৪. Database Schema (ER Design)

### Core Tables

**users**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| name | string | |
| email | string, unique | |
| password_hash | string | |
| role | enum(`admin`,`teacher`,`staff`,`student`) | |
| status | enum(`active`,`suspended`) | |
| created_at | timestamp | |

**subjects**
| Field | Type |
|---|---|
| id | PK |
| name | string |
| created_by | FK → users.id |

**questions**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| subject_id | FK → subjects.id | |
| question_text | text (rich text, CKEditor output) | |
| question_type | enum(`mcq`,`true_false`,`short`,`descriptive`) | |
| options | JSON | |
| correct_answer | text/JSON | |
| marks | decimal | |
| difficulty | enum(`easy`,`medium`,`hard`) | |
| **status** | enum(`pending`,`approved`,`rejected`) | ⭐ মূল ফিল্ড |
| created_by | FK → users.id (admin/teacher/staff) | |
| approved_by | FK → users.id (nullable) | |
| rejection_reason | text (nullable) | |
| parent_id | FK → questions.id (nullable) | versioning (root প্রশ্নের id) |
| version | int, default 1 | |
| is_latest | boolean, default true | |
| created_at / updated_at | timestamp | |

> Versioning নিয়ম আগের মতোই অক্ষত: approved প্রশ্ন এডিট করলে নতুন pending row তৈরি হয় (`version+1`), পুরনো row `is_latest=false` হয়ে যায় কিন্তু delete হয় না — পুরনো exam-এর data ভাঙে না। Approved pool query সবসময়: `WHERE status='approved' AND is_latest=true`।

**question_approval_logs**
| Field | Type |
|---|---|
| id | PK |
| question_id | FK |
| action | enum(`approved`,`rejected`,`resubmitted`) |
| performed_by | FK → users.id |
| reason | text |
| created_at | timestamp |

---

### Staff Payroll Tables

**staff_profiles**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| user_id | FK → users.id, unique | |
| bank_account_number | string, **encrypted cast** | সংবেদনশীল ডেটা |
| bank_name | string | |
| branch_name | string (nullable) | |
| account_holder_name | string | |
| mobile_banking_number | string, encrypted (nullable) | bKash/Nagad ব্যক্তিগত নম্বর হলে |
| total_questions_approved | int, default 0 | denormalized counter (dashboard-এর জন্য fast read) |
| total_earned | decimal, default 0 | denormalized counter |
| total_paid | decimal, default 0 | |

**question_rates** (পারিশ্রমিক rate কনফিগারেশন)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| subject_id | FK → subjects.id, nullable | null হলে "default rate" |
| rate_amount | decimal | প্রতি প্রশ্নের টাকা |
| effective_from | date | rate পরিবর্তনের ইতিহাস রাখার জন্য |

**staff_earnings** (ledger — প্রতিটা approved প্রশ্নের জন্য একটা এন্ট্রি)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| staff_id | FK → users.id | |
| question_id | FK → questions.id | |
| amount | decimal | approve করার সময়কার rate অনুযায়ী snapshot (পরে rate বদলালেও পুরনো এন্ট্রি না বদলায়) |
| status | enum(`pending_payout`,`paid`) | |
| payout_id | FK → staff_payouts.id, nullable | |
| created_at | timestamp | approve হওয়ার সময় |

**staff_payouts** (batch payment — Admin একসাথে অনেক earning পরিশোধ করলে)
| Field | Type |
|---|---|
| id | PK |
| staff_id | FK → users.id |
| total_amount | decimal |
| status | enum(`processing`,`paid`,`failed`) |
| paid_at | timestamp (nullable) |
| reference_note | string (nullable, e.g. bKash TrxID) |
| paid_by | FK → users.id (admin) |

---

### Subscription & Billing Tables

**subscription_plans**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| name | string | e.g. "Teacher Free", "Teacher Pro", "Student Free", "Student Pro" |
| target_role | enum(`teacher`,`student`) | |
| price | decimal | ফ্রি প্ল্যানে 0 |
| billing_cycle | enum(`monthly`,`yearly`,`free`) | |
| monthly_exam_limit | int, nullable | null = unlimited; ফ্রি প্ল্যানে যেমন 3 |
| is_default_free | boolean | নতুন ইউজার সাইনআপে অটো এসাইন হবে |

**subscriptions**
| Field | Type |
|---|---|
| id | PK |
| user_id | FK → users.id |
| plan_id | FK → subscription_plans.id |
| status | enum(`active`,`expired`,`cancelled`) |
| starts_at | datetime |
| ends_at | datetime (nullable, free plan হলে null/rolling) |
| auto_renew | boolean |

**payments** (সাবস্ক্রিপশন কেনার transaction)
| Field | Type |
|---|---|
| id | PK |
| user_id | FK |
| subscription_id | FK (nullable) |
| amount | decimal |
| gateway | enum(`sslcommerz`,`bkash`,`nagad`,`manual`) — গেটওয়ে চূড়ান্ত করা বাকি |
| gateway_transaction_id | string (nullable) |
| status | enum(`pending`,`success`,`failed`) |
| paid_at | datetime (nullable) |

**ফ্রি-লিমিট গণনার পদ্ধতি:** আলাদা কোনো "usage counter" টেবিল না রেখে সরাসরি query দিয়ে গণনা করাই যথেষ্ট (স্কেল বড় না হওয়া পর্যন্ত):
```php
$examsThisMonth = Exam::where('created_by', $teacher->id)
    ->whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->count();

if ($examsThisMonth >= $activePlan->monthly_exam_limit) {
    // block, "upgrade করুন" মেসেজ দেখাও
}
```

---

### Exam & Attempt Tables

**exams**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| title | string | |
| created_by | FK → users.id | Teacher, অথবা self-practice হলে Student নিজেই |
| exam_type | enum(`teacher_exam`,`self_practice`) | ⭐ দুই ধরনের exam আলাদা করার জন্য |
| **generation_mode** | enum(`manual`,`auto`), nullable | শুধু `self_practice`-এর জন্য প্রযোজ্য — Student নিজে প্রশ্ন বেছেছে নাকি সিস্টেম random জেনারেট করেছে |
| subject_id | FK → subjects.id | |
| duration_minutes | int | |
| start_time / end_time | datetime (nullable, self-practice-এ প্রযোজ্য না-ও হতে পারে) | |
| total_marks | decimal | |
| status | enum(`draft`,`published`,`ongoing`,`completed`) | |
| **share_token** | string, unique, nullable | শেয়ারযোগ্য লিংকের token (`/exam/{share_token}`) |
| link_expires_at | datetime (nullable) | |
| is_link_active | boolean, default true | Teacher চাইলে লিংক বন্ধ করে দিতে পারবে |

**exam_questions**
| Field | Type |
|---|---|
| exam_id | FK |
| question_id | FK (শুধু `approved AND is_latest` allow) |
| marks_override | decimal (nullable) |
| order_index | int |

**exam_attempts**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| exam_id | FK | |
| student_id | FK → users.id, **nullable** | Guest হলে null |
| **is_guest** | boolean | |
| **guest_name** | string, nullable | Guest attempt-এর জন্য |
| **guest_contact** | string, nullable | ফোন/ইমেইল — result জানানোর জন্য (ঐচ্ছিক) |
| started_at / submitted_at | timestamp | |
| status | enum(`in_progress`,`submitted`,`auto_submitted`) | |
| total_score | decimal | |

> **Guest attempt-এর ফলাফল** শুধু জমা দেওয়ার সাথে সাথেই স্ক্রিনে দেখানো হবে (যেহেতু পরে লগইন করে ফিরে দেখার কোনো অ্যাকাউন্ট নেই)। `guest_contact` দেওয়া থাকলে ভবিষ্যতে email/SMS-এ ফলাফল পাঠানোর ফিচার যোগ করা যাবে (এখনই বাধ্যতামূলক না)।

**attempt_answers**
| Field | Type |
|---|---|
| attempt_id | FK |
| question_id | FK |
| student_answer | text/JSON |
| is_correct | boolean (nullable) |
| obtained_marks | decimal |

---

## ৫. Subscription / Freemium এনফোর্সমেন্ট — কোথায় কোথায় চেক হবে

| Action | চেক পয়েন্ট |
|---|---|
| Teacher নতুন exam তৈরি/publish করছে | `Teacher\Resources\ExamResource\Pages\CreateExam` অথবা `ExamPolicy@create`-এ মাসিক limit চেক |
| Student self-practice exam জেনারেট করছে | নতুন `Student\Pages\GeneratePracticeExam` কাস্টম পেজে submit করার আগে চেক (দুই মোডেই — auto ও manual) |
| Exam-এর শেয়ার লিংকে Student ঢুকছে | **কোনো চেক লাগবে না** — এটা Teacher-এর subscription-এর আওতায় (exam আগেই তৈরি/publish হয়ে গেছে) |

লিমিট শেষ হয়ে গেলে UI-তে "আপনার এই মাসের ফ্রি লিমিট (৩টা) শেষ, Upgrade করুন" এই ধরনের বার্তা + Upgrade বাটন (subscription page-এ redirect) দেখানো উচিত।

---

## ৬. Staff Payment ফ্লো

1. Staff প্রশ্ন এড করে → `status = pending`।
2. Admin approve করে → **`QuestionObserver`** (বা `ApproveAction`-এর ভিতরে):
   - `question_approval_logs`-এ এন্ট্রি
   - `question_rates` থেকে (subject অনুযায়ী, না থাকলে default) rate নিয়ে `staff_earnings` টেবিলে নতুন row (`status = pending_payout`)
   - `staff_profiles.total_questions_approved` ও `total_earned` counter বাড়ানো
   - Staff-কে notification: "আপনার প্রশ্ন approved, ৳X যোগ হয়েছে"
3. Admin মাস শেষে (বা যেকোনো সময়) Staff Payout Panel থেকে একজন Staff-এর সব `pending_payout` earning সিলেক্ট করে batch payout করে → `staff_payouts` row তৈরি, সংশ্লিষ্ট `staff_earnings.status = paid` ও `payout_id` সেট।
4. Staff নিজের প্যানেলে দেখতে পারবে: subject-wise কতগুলো প্রশ্ন approved, মোট উপার্জন, কত পরিশোধিত, কত বাকি।

---

## ৭. Self-Practice Exam — Auto-Generate ও Manual, দুইটাই

Student নিজের subscription/free-limit-এর আওতায় দুইভাবে practice exam বানাতে পারবে:

### মোড ১ — Auto-Generate
Student শুধু কিছু প্যারামিটার দেবে, সিস্টেম নিজে random প্রশ্ন বেছে exam বানিয়ে দেবে:
- Subject (একাধিকও হতে পারে)
- Difficulty (easy/medium/hard, বা mixed)
- মোট প্রশ্ন সংখ্যা
- (ঐচ্ছিক) question_type filter (mcq/short/descriptive)

```php
Question::where('status', 'approved')
    ->where('is_latest', true)
    ->where('subject_id', $subjectId)
    ->when($difficulty, fn($q) => $q->where('difficulty', $difficulty))
    ->inRandomOrder()
    ->limit($count)
    ->get();
```
এই সেট দিয়ে `exams` row তৈরি হবে: `exam_type=self_practice`, `generation_mode=auto`, `created_by=student_id`।

### মোড ২ — Manual Selection
Student নিজে approved pool ব্রাউজ করে (subject/keyword দিয়ে ফিল্টার করে) নিজের পছন্দমতো প্রশ্ন টিক দিয়ে বেছে নেবে — অনেকটা Teacher-এর `ExamResource`-এর question picker-এর মতোই, শুধু Student-এর নিজস্ব প্যানেলের সংস্করণ।
- `generation_mode=manual`
- UI: `CheckboxList`/searchable multi-select relation field, ঠিক Teacher-এর exam builder-এর মতোই কিন্তু Student Panel-এ।

### দুই মোডেই কমন
- দুটোতেই তৈরি হওয়া exam সম্পূর্ণভাবে `self_practice` টাইপ, তাই এটা কখনো Guest-দের কাছে শেয়ার হবে না (`share_token` লাগবে না, `null` থাকবে) — একান্তই সেই Student-এর নিজের অ্যাটেম্পটের জন্য।
- Auto বা Manual — দুটোই monthly free-limit-এর হিসাবে একইভাবে গণনা হবে (`exam_type='self_practice'` ফিল্টার করে count)।
- Auto-generate exam-এও তৈরি হওয়ার পর Student চাইলে প্রশ্ন add/remove করে "manual-এ কনভার্ট" করতে পারবে কিনা — এটা v1-এ optional রাখা যায়, শুরুতে দুটো মোডকে সম্পূর্ণ আলাদা ফ্লো হিসেবেই রাখা সহজ।

---

## ৮. Exam Sharing — Guest vs Login Access

```
Teacher exam publish করে → share_token জেনারেট হয় (random 32-char string)
    → লিংক: https://domain.com/exam/{share_token}

Student লিংকে ক্লিক করলে দুটো অপশন দেখাবে:
  ① "Login করে দিন"  → normal student auth → exam_attempts.student_id সেট, is_guest=false
  ② "Guest হিসেবে দিন" → শুধু নাম (+ ঐচ্ছিক contact) নেয় → exam_attempts.student_id=null, is_guest=true
```

- Route protection: `share_token` আন্দাজ করে অন্যের exam-এ ঢোকা ঠেকাতে token যথেষ্ট লম্বা ও random হতে হবে (Laravel-এর `Str::random(32)` বা signed URL)।
- `link_expires_at` পার হয়ে গেলে বা `is_link_active=false` হলে "এই exam-টি আর সক্রিয় নেই" পেজ দেখাবে।
- Guest submission-এ **rate limiting** বাধ্যতামূলক (একই IP থেকে বারবার submit করে spam/multiple-attempt ঠেকাতে) — Laravel-এর `throttle` middleware বা cookie/fingerprint ভিত্তিক one-attempt-per-browser চেক।

---

## ৯. Module-ভিত্তিক ডিজাইন (Filament Resources & Actions, ৪-Panel)

### Admin Panel (`/admin`)
- `QuestionResource` — সব status; `ApproveAction`/`RejectAction` (approve হলে earning trigger — Staff owner হলে)।
- `SubjectResource`, `TeacherResource`, `StaffResource`
- `QuestionRateResource` — subject-wise rate কনফিগার
- `StaffPayoutResource` — pending earnings দেখে batch payout মার্ক করা
- `SubscriptionPlanResource` — প্ল্যান তৈরি/এডিট
- `SubscriptionResource`, `PaymentResource` — সব ইউজারের সাবস্ক্রিপশন ও পেমেন্ট history
- `ExamResource` — সব exam-এর overview/report (read-only)

### Teacher Panel (`/teacher`)
- `QuestionResource` — নিজের সব status + সবার approved pool (আগের ডিজাইনের মতোই `getEloquentQuery()` scope)
- `ExamResource` — exam তৈরি, question picker (শুধু approved+latest), publish action **(subscription limit চেক সহ)**, share-link জেনারেট/কপি বাটন, ফলাফল/গ্রেডিং পেজ
- `Pages\MySubscription` — বর্তমান প্ল্যান, ব্যবহার (X/Y exams this month), upgrade বাটন

### Staff Panel (`/staff`)
- `QuestionResource` — শুধু নিজের প্রশ্ন (সব status), owner-only scope
- `Pages\MyEarnings` — subject-wise breakdown টেবিল + total approved/earned/paid, `staff_profiles` এডিট ফর্ম (bank info)

### Student Panel (`/student`)
- `Pages\JoinExam` — share link/code দিয়ে ঢোকার এন্ট্রি পয়েন্ট (login বা guest চয়েস)
- `Pages\TakeExamPage`, `Pages\ExamResultPage`
- `Pages\GeneratePracticeExam` — subject/difficulty/সংখ্যা দিয়ে **Auto-Generate** মোড **(subscription limit চেক সহ)**
- `Pages\BuildPracticeExam` — approved pool ব্রাউজ করে **Manual Selection** মোড **(subscription limit চেক সহ)**
- `Pages\MySubscription`
- নিজের attempt/result history (`where('student_id', auth()->id())`)

**Access-control নিয়ম (আগের মতোই বাধ্যতামূলক, শুধু Staff/Guest যোগ হলো):**
- প্রতিটা Policy (`QuestionPolicy`, `ExamPolicy`, `StaffEarningPolicy`) owner+role+status ডাবল-চেক করবে।
- Guest route সম্পূর্ণ আলাদা (unauthenticated), তাই এখানে Laravel-এর সাধারণ web middleware + rate limiting দিয়ে সুরক্ষিত রাখতে হবে, Filament auth-এর বাইরে থাকবে (একটা সাধারণ Laravel controller/Livewire component, Filament panel না)।

---

## ১০. পুরো ফ্লো (End-to-End, updated)

1. Admin/Teacher/Staff প্রশ্ন যোগ করে → Staff/Teacher-এরটা `pending`।
2. Admin approve করে → Approved pool-এ যোগ, Staff হলে earning-ও যোগ হয়।
3. Teacher exam বানায় (subscription/free-limit চেক পাস করলে) → publish → share link পায়।
4. Teacher লিংক শেয়ার করে (WhatsApp/Facebook গ্রুপ ইত্যাদিতে)।
5. Student লিংকে ঢুকে Login বা Guest বেছে নিয়ে exam attempt দেয়।
6. Auto-grade (MCQ/True-False), descriptive হলে Teacher manual grading করে।
7. ফলাফল Student (ও Guest সাথে সাথে) দেখতে পায়; Teacher/Admin analytics দেখে।
8. পাশাপাশি: Student চাইলে নিজের subscription-এর আওতায় নিজের মতো practice exam জেনারেট করে দিতে পারে (Teacher ছাড়াই)।
9. Admin পর্যায়ক্রমে Staff-দের payout করে, Subscription revenue রিপোর্ট দেখে।

---

## ১১. Tech Stack (চূড়ান্ত)

| Layer | Choice |
|---|---|
| Backend Framework | **Laravel 11** |
| Panel Framework | **Filament v5** (৪-প্যানেল: Admin, Teacher, Staff, Student) — Livewire v4-ভিত্তিক |
| Database | **MySQL** |
| Frontend (ভিতরে) | Filament built-in **Livewire + Alpine.js + Blade + Tailwind CSS** — আলাদা করে সেটআপ লাগবে না |
| Rich Text Editor | **CKEditor 5** (+ `ckeditor5-math`/MathLive plugin, KaTeX রেন্ডারিং — বাংলা টেক্সট plain, শুধু math widget আলাদা) |
| Role/Permission | **Spatie `laravel-permission`** + **Filament Shield** (৪ role-এর জন্য UI-সহ permission ম্যানেজমেন্ট) |
| সংবেদনশীল ডেটা এনক্রিপশন | Laravel `encrypted` cast (`staff_profiles.bank_account_number` ইত্যাদি) |
| Notification | Filament Database Notifications (+ ঐচ্ছিক Reverb/Pusher broadcast) |
| Payment Gateway | **চূড়ান্ত করা বাকি** — বাংলাদেশ কনটেক্সটে সাধারণত SSLCommerz (bKash/Nagad/কার্ড সব এক ইন্টিগ্রেশনে কভার করে) অথবা bKash Merchant API সরাসরি — কোনটা প্রেফার করছেন জানালে ডিটেইল যোগ করে দেব |
| Reports/Export | `maatwebsite/laravel-excel` (staff payout report, subscription revenue export) |
| PDF | `spatie/laravel-pdf` বা DomPDF (result sheet, payment receipt) |
| Guest Exam Link | Laravel signed/random token + `throttle` middleware |
| Queue | Database বা Redis driver (bulk notification, subscription expiry check করার জন্য scheduled job) |
| Scheduler | Laravel Scheduler — daily job: subscription expire করা, free-limit reset (মাস শুরুতে দরকার নেই কারণ query মাস অনুযায়ী নিজে থেকেই resolve করে) |

### Filament Multi-Panel Architecture (৪-প্যানেল)

```
app/Providers/Filament/
  AdminPanelProvider.php    → /admin    → guard: web, role: admin
  TeacherPanelProvider.php  → /teacher  → guard: web, role: teacher
  StaffPanelProvider.php    → /staff    → guard: web, role: staff
  StudentPanelProvider.php  → /student  → guard: web, role: student
```

- একটাই `users` টেবিল, `role` কলাম + Spatie permission দিয়ে fine-grained control।
- প্রতিটা Panel Provider-এ ভুল role-এর ইউজার ঢুকতে না পারার auth guard।
- **নোট:** Filament v5 → v3-এর তুলনায় Form/Table গঠন বদলেছে (`Filament\Schemas` namespace) — কোড লেখার সময় সবসময় v5-এর অফিসিয়াল ডক দেখে নেওয়া উচিত।

---

## ১২. Security ও Access Control নোট

- Role-based middleware প্রতিটা panel/route-এ বাধ্যতামূলক।
- Question visibility filtering সবসময় query-level এ (Resource scope + Policy ডাবল-লেয়ার)।
- **Bank account/mobile banking নম্বর — encrypted cast বাধ্যতামূলক**, admin panel-এও masked format-এ দেখানো ভালো (শেষ ৪ ডিজিট বাদে বাকি hide)।
- Payment/Subscription transaction-এ **idempotency key** ব্যবহার করা উচিত যাতে gateway callback দুইবার এলে ডাবল সাবস্ক্রিপশন/ডাবল earning না তৈরি হয়।
- Guest exam attempt route-এ rate limiting + (ঐচ্ছিক) captcha, যাতে script দিয়ে বারবার submit করে ফলাফল ম্যানিপুলেট করা না যায়।
- Exam attempt চলাকালীন সময়-ট্র্যাকিং সার্ভার সাইডে (client manipulation ঠেকাতে)।
- সব approve/reject ও payout অ্যাকশন লগ হবে (audit trail) — আর্থিক অ্যাকশন হওয়ায় `staff_payouts`/`payments`-এর জন্য আলাদা audit sensitivity রাখা উচিত।
