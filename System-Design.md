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

### Content Hierarchy — Class → Subject → Chapter → Question

> প্রশ্ন সরাসরি Subject-এর সাথে যুক্ত হয় না। প্রতিটা প্রশ্ন একটা **Chapter**-এর অধীনে, Chapter একটা **Subject**-এর অধীনে, আর Subject একটা **Class**-এর অধীনে থাকে। প্রশ্ন এড/এডিট করার ফর্মে চারটা স্তরই cascading dropdown (Class → Subject → Chapter → Question) হিসেবে সিলেক্ট করতে হয় — Class না বাছা পর্যন্ত Subject dropdown, আর Subject না বাছা পর্যন্ত Chapter dropdown খালি/disabled থাকবে।
>
> `Class` PHP-তে reserved word হওয়ায় মডেলের নাম **`AcademicClass`** (টেবিল: `academic_classes` — Laravel কনভেনশন অনুযায়ী মডেলের নাম থেকেই অটো-ডিরাইভড, কাস্টম `$table` override করার দরকার নেই)। Class/Subject/Chapter — তিনটাই বর্তমান `Subject`-এর মতো **Admin-only ম্যানেজড রেফারেন্স ডেটা** (Teacher/Staff প্রশ্ন যোগ করার সময় শুধু সিলেক্ট করবে, নিজে তৈরি করতে পারবে না)।

> **আপডেট:** `subjects` এখন কোনো নির্দিষ্ট class-এর সাথে সরাসরি বাঁধা না — একটা মাস্টার লিস্ট (যেমন "Mathematics" একটা মাত্র row, Class 6 থেকে HSC পর্যন্ত সব ক্লাসেই reuse হবে)। কোন class-এ কোন subject আছে সেটা আলাদা pivot টেবিল `class_subjects` দিয়ে ট্র্যাক হয়। এই তিনটা টেবিলই Admin-only reference data হওয়ায় `created_by` রাখা হয়নি।

**academic_classes** ✅ *তৈরি হয়ে গেছে — model: `App\Models\AcademicClass`, migration, factory, ও Admin Panel resource (`App\Filament\Resources\AcademicClasses\AcademicClassResource`, nav label "Classes")। এই আপডেটেড ডিজাইন অনুযায়ী migration আপডেট করা বাকি (name-only, created_by বাদ)।*
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| name | string | যেমন "Class 6", "SSC", "HSC 1st Year" |
| order_index | int, default 0 | UI-তে ক্রম সাজানোর জন্য (Class 6, 7, 8... ঠিক ক্রমে দেখাতে) |

**subjects**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| name | string | যেমন "Mathematics", "Biology" — মাস্টার লিস্ট, একবারই তৈরি হবে |

**class_subjects** (pivot — কোন class-এ কোন subject আছে সেটা ট্র্যাক করে)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| academic_class_id | FK → academic_classes.id | |
| subject_id | FK → subjects.id | |
| order_index | int, default 0 | ওই class-এর মধ্যে subject-এর ক্রম |
| — | unique(`academic_class_id`, `subject_id`) | একই class-এ একই subject দুইবার assign না হয় |

> **✅ কনফার্মড চেইন:** `academic_classes` → `class_subjects` (pivot) → `chapters` → `questions`। অর্থাৎ `chapters.class_subject_id` সরাসরি `class_subjects.id`-কে পয়েন্ট করে, `subjects.id`-কে না — কারণ একই subject (যেমন Math) বিভিন্ন class-এ আলাদা chapter সেট রাখে (Class 6 vs Class 9-এর Math chapter সম্পূর্ণ আলাদা), আর `class_subject_id` থেকেই `class_subjects.academic_class_id` + `class_subjects.subject_id` দুটোই relation দিয়ে বের করা যায়।

**chapters**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| class_subject_id | FK → class_subjects.id | এখান থেকেই academic_class + subject দুটোই বের করা যায় |
| name | string | যেমন "Algebra", "কোষ ও কোষ বিভাজন" |
| order_index | int, default 0 | ওই subject-এর মধ্যে chapter-এর ক্রম (Chapter 1, 2, 3...) |

---

### Core Tables

**users**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| name | string | |
| email | string, unique | |
| password_hash | string, nullable | শুধু `admin`/`super_admin`-এর জন্য filled (Filament default password auth); Teacher/Staff/Student-এর জন্য সবসময় `null` (Google auth) |
| google_id | string, unique, nullable | Google-এর `sub` — শুধু Teacher/Staff/Student-দের জন্য filled |
| avatar | string, nullable | Google প্রোফাইল ছবির URL |
| role | enum(`super_admin`,`admin`,`teacher`,`staff`,`student`) | |
| status | enum(`pending_approval`,`active`,`suspended`) | নিচে "Authentication" সেকশনে বিস্তারিত |
| email_verified_at | timestamp, nullable | Google callback-এ auto-set; Admin/Super Admin Filament-এর normal flow অনুযায়ী |
| created_at | timestamp | |

### Authentication — Google OAuth (Teacher/Staff/Student) + Filament Default (Admin/Super Admin)

> শুধু `super_admin` ও `admin` রোল **Filament-এর ডিফল্ট email/password Login + Forget Password** ব্যবহার করবে (Admin Panel, `/admin`) — Registration পাবলিকলি বন্ধ থাকবে (নিচে দেখুন)। বাকি তিনটা প্যানেল (Teacher/Staff/Student) সম্পূর্ণ **Google OAuth-only** — কোনো password ফর্ম/forget-password ফ্লো থাকবে না।

**Google login ফ্লো (Teacher/Staff/Student):**
1. প্রতিটা প্যানেলের কাস্টম Login page-এ শুধু "Continue with Google" বাটন থাকে, যা `/auth/google/redirect/{role}` (role = `teacher`|`staff`|`student`) রুটে যায় — intended role session-এ সেভ হয়।
2. Google callback (`/auth/google/callback`)-এ email/`google_id` দিয়ে existing user খোঁজা হয়:
   - **পাওয়া গেলে** → সরাসরি লগইন করিয়ে সংশ্লিষ্ট প্যানেলে redirect (role মিসম্যাচ হলে, অর্থাৎ Student হিসেবে সাইনআপ করা কেউ Teacher প্যানেলে ঢুকতে চাইলে, এরর দেখিয়ে আটকানো হবে)।
   - **না পাওয়া গেলে** → নতুন `users` row তৈরি (session-এ রাখা intended role অনুযায়ী `role` সেট), `google_id`/`avatar`/`email_verified_at` ফিল করে দেয়া হয়।
3. **Status অনুযায়ী আচরণ:**
   - `role = teacher` অথবা `role = student` → নতুন একাউন্ট সাথে সাথে `status = active`, সরাসরি প্যানেলে ঢুকে যাবে।
   - `role = staff` → নতুন একাউন্ট `status = pending_approval`-এ তৈরি হবে — লগইন হবে (authenticated) কিন্তু Staff Panel middleware/policy-তে চেক করে "আপনার একাউন্ট Admin অনুমোদনের অপেক্ষায় আছে" পেজ দেখানো হবে, `QuestionResource`-এ কিছু করতে পারবে না যতক্ষণ না Admin `AdminResource`-এ গিয়ে approve করে `status = active` করে দেয়।

**Admin/Super Admin:**
- একাউন্ট Google flow-এর সম্পূর্ণ বাইরে, `password_hash` filled থাকবে, `google_id` সবসময় `null`।
- **✅ সিদ্ধান্ত: Admin Panel-এ পাবলিক Registration বন্ধ থাকবে** (`->registration(false)`) — নিরাপত্তার কারণে যে কেউ সাইনআপ করে Admin হয়ে যেতে পারবে না। শুধু **Login** ও **Forget Password** পেজ চালু থাকবে।
- প্রথম **Super Admin** একাউন্ট তৈরি হবে `php artisan db:seed` (একটা dedicated seeder, যেমন `SuperAdminSeeder`) দিয়ে — deploy করার পর একবারই রান হবে।
- এরপর নতুন Admin/Staff-manager লাগলে **Super Admin নিজে Admin Panel-এর ভিতর থেকে** (`AdminResource` — নাম, ইমেইল, password সেট করে) তৈরি করে দেবে, পাবলিক রুট দিয়ে না।

**questions**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| chapter_id | FK → chapters.id | Subject/Class সরাসরি কলাম নয় — `chapter → class_subject → (subject + class)` রিলেশন দিয়ে বের করা হয় |
| question_type | enum(`mcq`,`cq`) | শুধু MCQ ও CQ (সৃজনশীল) — true_false/short/descriptive রাখা হয়নি |
| question_text | text (rich text, CKEditor output) | MCQ-এর জন্য মূল প্রশ্ন, CQ-এর জন্য **উদ্দীপক (stimulus)** |
| question_image | string, nullable | উদ্দীপক/প্রশ্নের সাথে আলাদা ডায়াগ্রাম/ছবি (path) |
| options | JSON, nullable | **শুধু MCQ**: `[{"option":"...","image":null}, ...]` — কোনো আলাদা key/id নেই, option-এর টেক্সটই তার নিজের identifier |
| correct_answer | text, nullable | **শুধু MCQ**: সঠিক option-এর `option` টেক্সট (হুবহু), key/index না — এতে Repeater reorder করলেও সঠিক উত্তর ঠিক থাকে |
| marks | decimal | MCQ-এর জন্য সরাসরি মার্কস; CQ-এর জন্য সাব-পার্টগুলোর যোগফল (auto-synced) |
| difficulty | enum(`easy`,`medium`,`hard`) | |
| **status** | enum(`pending`,`approved`,`rejected`) | ⭐ মূল ফিল্ড |
| created_by | FK → users.id (admin/teacher/staff) | |
| approved_by | FK → users.id (nullable) | |
| rejection_reason | text (nullable) | |
| parent_id | FK → questions.id (nullable) | versioning (root প্রশ্নের id) |
| version | int, default 1 | |
| is_latest | boolean, default true | |
| created_at / updated_at | timestamp | |

**question_cq_parts** (শুধু `question_type = 'cq'`-এর জন্য, প্রতি CQ-তে ৪টা row)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| question_id | FK → questions.id | |
| part_type | enum(`knowledge`,`comprehension`,`application`,`higher_application`) | জ্ঞানমূলক/অনুধাবনমূলক/প্রয়োগ/উচ্চতর দক্ষতা |
| part_order | int | ১, ২, ৩, ৪ ক্রম |
| part_text | text (rich text, CKEditor output) | সাব-প্রশ্নের টেক্সট |
| part_image | string, nullable | ওই সাব-প্রশ্নের নিজস্ব ছবি/ডায়াগ্রাম |
| marks | decimal | সাধারণত ১, ২, ৩, ৪ |

> CQ-এর `questions.marks` = তার ৪টা `question_cq_parts.marks`-এর যোগফল, model observer দিয়ে auto-sync হবে। Versioning-এ নতুন version তৈরি হলে `question_cq_parts`-এর ৪টা row-ও নতুন `question_id`-এর সাথে কপি হয়ে যাবে, পুরনো version-এর parts অক্ষত থাকে।

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

### Board Question Papers

> Board প্রশ্ন সাধারণ MCQ/CQ প্রশ্নপুলের (`questions`/`question_cq_parts`) সাথে মেশে না — এটা সম্পূর্ণ আলাদা **পুরো প্রশ্নপত্র (paper)** হিসেবে ডিজাইন করা, যার নিজস্ব approval workflow ও টেবিল সেট আছে। কাঠামো ইচ্ছাকৃতভাবে `questions`-এর মতোই (text/image/options/marks) — normalization-এর দিক থেকে কিছুটা ডুপ্লিকেট, কিন্তু দুই সিস্টেমের approval/versioning লজিক আলাদা রাখাই এখানে সহজ ও পরিষ্কার।

**boards**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| name | string | পুরো নাম, যেমন "রাজশাহী শিক্ষা বোর্ড" |
| short_name | string | সংক্ষিপ্ত রূপ, যেমন "RAJ" — Admin নিজে বসাবে, dropdown/badge-এ কম জায়গায় দেখানোর জন্য |
| order_index | int, default 0 | |

> `year`-এর জন্য আলাদা কোনো "সংক্ষিপ্ত রূপ" কলাম লাগবে না — `board_question_papers.year` একটা সাধারণ integer (যেমন `2023`), প্রয়োজনে shorthand (`23`) মডেল accessor দিয়ে কোড থেকেই বের করা হবে, ডাটাবেজে ডুপ্লিকেট স্টোর করার দরকার নেই।

**board_question_papers** (একটা নির্দিষ্ট বোর্ড + সাল + subject-এর পুরো প্রশ্নপত্র)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| board_id | FK → boards.id | কোন বোর্ড |
| class_subject_id | FK → class_subjects.id | কোন class + subject-এর প্রশ্নপত্র |
| year | year/int | যেমন 2023 |
| status | enum(`pending`,`approved`,`rejected`) | পুরো পেপারটা একসাথে approve/reject হবে |
| created_by | FK → users.id | |
| approved_by | FK → users.id, nullable | |
| rejection_reason | text, nullable | |
| created_at / updated_at | timestamp | |
| — | unique(`board_id`,`class_subject_id`,`year`) | একই বোর্ড+সাল+সাবজেক্টের পেপার দুইবার তৈরি না হয় |

**board_mcq_questions** (পেপারের MCQ অংশ)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| board_question_paper_id | FK → board_question_papers.id | |
| question_text | text (CKEditor) | |
| question_image | string, nullable | |
| options | JSON | `[{"option":"...","image":null}, ...]` |
| correct_answer | string | সঠিক option-এর `option` টেক্সট (হুবহু) |
| marks | decimal | |
| order_index | int | পেপারে প্রশ্নের ক্রম |

**board_cq_questions** (পেপারের CQ অংশ)
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| board_question_paper_id | FK → board_question_papers.id | |
| question_text | text (CKEditor) | উদ্দীপক |
| question_image | string, nullable | |
| marks | decimal | সাব-পার্টের যোগফল, auto-synced |
| order_index | int | |

**board_cq_question_parts**
| Field | Type | নোট |
|---|---|---|
| id | PK | |
| board_cq_question_id | FK → board_cq_questions.id | |
| part_type | enum(`knowledge`,`comprehension`,`application`,`higher_application`) | |
| part_order | int | |
| part_text | text | |
| part_image | string, nullable | |
| marks | decimal | |

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
| subject_id | FK → subjects.id | Exam এখনও subject-level-এই থাকে (chapter-level নয়) — নির্দিষ্ট chapter(গুলো) শুধু Auto-Generate-এর একটা ঐচ্ছিক ফিল্টার প্যারামিটার (সেকশন ৭ দেখুন), আলাদা কলাম না |
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
- (ঐচ্ছিক) নির্দিষ্ট Chapter(গুলো) — না দিলে পুরো Subject-এর সব Chapter থেকে বাছা হবে
- Difficulty (easy/medium/hard, বা mixed)
- মোট প্রশ্ন সংখ্যা
- (ঐচ্ছিক) question_type filter (mcq/short/descriptive)

```php
Question::where('status', 'approved')
    ->where('is_latest', true)
    ->whereHas('chapter', fn ($q) => $q
        ->where('subject_id', $subjectId)
        ->when($chapterIds, fn ($q) => $q->whereIn('id', $chapterIds)))
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
- `QuestionResource` — সব status; `ApproveAction`/`RejectAction` (approve হলে earning trigger — Staff owner হলে); ফর্মে Class → Subject → Chapter cascading select।
- `AcademicClassResource` (model: `AcademicClass`, nav label "Classes") ✅ *তৈরি হয়ে গেছে*, `SubjectResource` (class-এর অধীনে হবে — এখনো `class_id` যোগ হয়নি), `ChapterResource` (subject-এর অধীনে, এখনো তৈরি হয়নি) — তিনটাই Admin-only রেফারেন্স ডেটা ম্যানেজমেন্ট।
- `TeacherResource`, `StaffResource`
- `QuestionRateResource` — subject-wise rate কনফিগার
- `StaffPayoutResource` — pending earnings দেখে batch payout মার্ক করা
- `SubscriptionPlanResource` — প্ল্যান তৈরি/এডিট
- `SubscriptionResource`, `PaymentResource` — সব ইউজারের সাবস্ক্রিপশন ও পেমেন্ট history
- `ExamResource` — সব exam-এর overview/report (read-only)

### Teacher Panel (`/teacher`)
- `QuestionResource` — নিজের সব status + সবার approved pool (আগের ডিজাইনের মতোই `getEloquentQuery()` scope); ফর্মে Class → Subject → Chapter cascading select (Class/Subject/Chapter নিজে তৈরি করতে পারবে না, শুধু Admin-এর তৈরি করা থেকে বাছবে)।
- `ExamResource` — exam তৈরি, question picker (শুধু approved+latest), publish action **(subscription limit চেক সহ)**, share-link জেনারেট/কপি বাটন, ফলাফল/গ্রেডিং পেজ
- `Pages\MySubscription` — বর্তমান প্ল্যান, ব্যবহার (X/Y exams this month), upgrade বাটন

### Staff Panel (`/staff`)
- `QuestionResource` — শুধু নিজের প্রশ্ন (সব status), owner-only scope; ফর্মে একই Class → Subject → Chapter cascading select।
- `Pages\MyEarnings` — subject-wise breakdown টেবিল + total approved/earned/paid, `staff_profiles` এডিট ফর্ম (bank info)

### Student Panel (`/student`)
- `Pages\JoinExam` — share link/code দিয়ে ঢোকার এন্ট্রি পয়েন্ট (login বা guest চয়েস)
- `Pages\TakeExamPage`, `Pages\ExamResultPage`
- `Pages\GeneratePracticeExam` — subject/(ঐচ্ছিক chapter)/difficulty/সংখ্যা দিয়ে **Auto-Generate** মোড **(subscription limit চেক সহ)**
- `Pages\BuildPracticeExam` — approved pool ব্রাউজ করে (Class/Subject/Chapter দিয়ে ফিল্টার করে) **Manual Selection** মোড **(subscription limit চেক সহ)**
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
