# ০৩ — Database

এই তালিকা migration চালানো ডেটাবেজ থেকে নেওয়া (২০২৬-১০-১০)। প্রতিটা টেবিলে `id`, `created_at`, `updated_at` আছে ধরে নিন, যদি না অন্য কিছু বলা থাকে। Laravel-এর নিজস্ব টেবিল (`cache`, `jobs`, `sessions`, `migrations`, `password_reset_tokens` ইত্যাদি) এখানে লেখা হয়নি।

লোকাল ও টেস্টে SQLite, লাইভে MySQL। ডেটাবেজ-নির্ভর SQL লিখলে মনে রাখুন লাইভের আগে সেটা MySQL-এ পরীক্ষিত না। → [10-testing-and-deploy.md](10-testing-and-deploy.md)

## সম্পর্কের ছবি

```
academic_classes ─┐
                  ├─ class_subjects ── chapters ─┬─ topics
subjects ─────────┘                              └─ questions ── question_cq_parts
                                                       │
users ─┬─ questions (created_by)                       ├─ exam_questions ── exams ── exam_attempts ── attempt_answers
       ├─ exams (created_by)                           ├─ student_question_mistakes
       ├─ subscriptions ── subscription_plans          └─ teacher_question_stats
       ├─ payments ── referral_rewards
       ├─ wallet_transactions
       └─ staff_profiles / staff_earnings / staff_payouts

boards ── board_question_papers ─┬─ board_mcq_questions
                                 └─ board_cq_questions ── board_cq_question_parts
```

## User

### `users`

| কলাম | টীকা |
|---|---|
| `name`, `email` (unique) | |
| `password` (nullable) | শুধু Admin/Super Admin-এর; Google-only role-এ `null` |
| `google_id` (unique, nullable), `avatar` | শুধু Teacher/Staff/Student-এ |
| `role` | `UserRole`; ডিফল্ট `student` |
| `status` | `UserStatus`; ডিফল্ট `active` |
| `phone`, `phone_verified_at` | প্রোফাইলে দেওয়া ফোন নম্বর |
| `referral_code` (unique, nullable) | নিজের কোড; প্রথমবার চাওয়ার সময় তৈরি হয় |
| `referred_by_id` → `users` | যে refer করেছে |

Role-এর নাম `spatie/laravel-permission`-এর টেবিলেও (`roles`, `model_has_roles` ইত্যাদি) থাকে; `RoleSeeder` সেগুলো তৈরি করে।

### `reactivation_requests`

Suspended Teacher/Staff-এর একাউন্ট ফেরত চাওয়ার আবেদন: `user_id`, `message`, `status` (`pending`/`approved`/`declined`), `admin_note`, `reviewed_by`, `reviewed_at`।

## Content hierarchy (Admin-only reference data)

| টেবিল | কলাম |
|---|---|
| `academic_classes` | `name`, `order_index` |
| `subjects` | `name`, `name_bn`, `short_name` — class-নির্ভর না, একটা মাস্টার তালিকা |
| `class_subjects` | `academic_class_id`, `subject_id`, `order_index`; unique(class, subject) |
| `chapters` | `class_subject_id`, `name`, `order_index` |
| `topics` | `chapter_id`, `name`, `order_index` |

Chapter সরাসরি `class_subjects`-এর সাথে যুক্ত, `subjects`-এর সাথে না: একই subject ভিন্ন class-এ ভিন্ন chapter রাখে।

## প্রশ্ন

### `questions`

| কলাম | টীকা |
|---|---|
| `chapter_id`, `topic_id` (nullable) | |
| `question_type` | `mcq` / `cq` |
| `question_text` | HTML। CQ-তে এটা উদ্দীপক। সূত্র: `<span class="qb-katex-embed">latex</span>` |
| `question_image` | public disk-এর পাথ |
| `options` (JSON) | শুধু MCQ: `[{option, image, is_correct}]`; ঠিক একটা `is_correct` |
| `marks` | MCQ-তে লেখক দেয়; CQ-তে অংশগুলোর যোগফল, নিজে থেকে বসে |
| `difficulty` | লেখকের বাছাই; যথেষ্ট উত্তর জমলে সিস্টেম বদলায় |
| `answer_count`, `correct_answer_count` | difficulty-র গণনা (শুধু Teacher-এর পরীক্ষা থেকে) |
| `editor_mode` | `richtext` / `ckeditor` |
| `status` | `pending` / `approved` / `rejected` |
| `created_by`, `approved_by`, `rejection_reason` | |
| `parent_id`, `version`, `is_latest` | versioning; `parent_id` সবসময় প্রথম সংস্করণকে নির্দেশ করে |
| `deleted_at` | soft delete |

Index: `(status, is_latest)`, `(chapter_id, status, is_latest)`, `(created_by)`।

### `question_cq_parts`

CQ-র চার অংশ: `question_id`, `part_type`, `part_order`, `part_text`, `part_image`, `marks`; unique(question, part_type)।

### `question_approval_logs`

প্রতিটা approve/reject/resubmit-এর রেকর্ড: `question_id`, `action`, `performed_by`, `reason`।

## বোর্ড প্রশ্নপত্র (আলাদা সাবসিস্টেম)

| টেবিল | কলাম |
|---|---|
| `boards` | `name`, `short_name`, `order_index` |
| `board_question_papers` | `board_id`, `class_subject_id`, `year`, `status`, `created_by`, `approved_by`, `rejection_reason`; unique(board, class_subject, year) |
| `board_mcq_questions` | `board_question_paper_id`, `question_text`, `question_image`, `options`, `marks`, `order_index` |
| `board_cq_questions` | `board_question_paper_id`, `question_text`, `question_image`, `marks`, `order_index` |
| `board_cq_question_parts` | `board_cq_question_id`, `part_type`, `part_order`, `part_text`, `part_image`, `marks` |

এগুলোর সাথে `questions` টেবিলের কোনো সম্পর্ক নেই, ইচ্ছা করেই।

## পরীক্ষা

### `exams`

| কলাম | টীকা |
|---|---|
| `title`, `created_by` | Teacher-এর পরীক্ষায় Teacher, self-practice-এ student |
| `exam_type` | `teacher_exam` / `self_practice` |
| `generation_mode` | `manual` / `auto` / `mistakes` |
| `delivery_mode` | `online` / `offline` / `both` |
| `subject_id`, `class_subject_id` (nullable) | পুরনো পরীক্ষায় `class_subject_id` ফাঁকা থাকতে পারে |
| `duration_minutes`, `start_time`, `end_time` | |
| `total_marks` | অনলাইনে নেওয়া পরীক্ষায় শুধু অনলাইনের প্রশ্নগুলোর; `recalculateTotalMarks()` বসায় |
| `status` | `draft` / `published` / … |
| `share_token` (unique) | publish-এর সময় তৈরি, ৩২ অক্ষর |
| `link_expires_at`, `is_link_active` | শেয়ার-লিংক কখন বন্ধ |
| `answers_released_at`, `answers_release_at` | উত্তর প্রকাশ হয়েছে / কখন নিজে থেকে হবে |

### `exam_questions`

`exam_id`, `question_id`, `marks_override`, `order_index`; unique(exam, question)।

### `exam_attempts`

| কলাম | টীকা |
|---|---|
| `exam_id` | |
| `student_id` (nullable), `is_guest`, `guest_name`, `guest_contact` | Guest-এ `student_id` ফাঁকা; `guest_contact` normalize করে রাখা |
| `started_at`, `submitted_at`, `status`, `total_score` | |
| `difficulty_counted_at` | difficulty-র গণনায় তোলা হয়েছে (শুধু Teacher-এর পরীক্ষা) |
| `statistics_recorded_at` | ভুলের তালিকা ও Teacher-এর পরিসংখ্যানে তোলা হয়েছে |

শেষ দুটো কলাম নিশ্চিত করে একই খাতা দুইবার গোনা না হয়।

### `attempt_answers`

`attempt_id`, `question_id`, `student_answer`, `is_correct`, `obtained_marks`; unique(attempt, question)। শুধু উত্তর দেওয়া প্রশ্নের সারি থাকে; ফাঁকা রাখা প্রশ্নের কোনো সারি নেই।

## উত্তর থেকে জমানো হিসাব

### `student_question_mistakes`

একজন student-এর ভুলের তালিকা: `student_id`, `question_id` (প্রশ্নের **প্রথম সংস্করণের** id), `correct_streak`, `locked_by_exam_id` (যে Teacher-এর পরীক্ষার উত্তর এখনো লুকানো); unique(student, question)।

### `teacher_question_stats`

একজন Teacher-এর ছাত্ররা একটা প্রশ্নে কেমন করেছে: `teacher_id`, `question_id` (প্রথম সংস্করণের id), `answer_count`, `wrong_count`; unique(teacher, question)।

## Subscription ও পেমেন্ট

| টেবিল | কলাম |
|---|---|
| `subscription_plans` | `name`, `target_role` (`teacher`/`student`), `price`, `billing_cycle`, `monthly_exam_limit` (null = সীমাহীন), `is_default_free` |
| `subscriptions` | `user_id`, `plan_id`, `status`, `starts_at`, `ends_at`, `auto_renew` |
| `payments` | নিচে |
| `wallet_transactions` | `user_id`, `type`, `amount`, `payment_id`, `expires_at` — শুধু যোগ হয়, কখনো বদলায় না |
| `referral_rewards` | `referrer_id`, `payment_id` (unique), `amount`, `matures_at`, `expires_at`, `cancelled_at`, `payout_id` |

### `payments`

| কলাম | টীকা |
|---|---|
| `user_id`, `plan_id`, `subscription_id` | |
| `list_price`, `discount_amount`, `wallet_amount`, `amount` | তালিকার দাম থেকে discount ও wallet বাদ দিয়ে `amount` = নগদে দেওয়া টাকা |
| `gateway`, `gateway_transaction_id` (unique) | এখন `manual`; transaction id দুইবার ব্যবহার করা যায় না |
| `payer_provider`, `payer_number` | কোন মাধ্যম ও নম্বর থেকে পাঠানো |
| `status`, `paid_at` | |
| `reviewed_by`, `reviewed_at`, `review_note` | কোন Admin যাচাই করেছে |
| `refundable_until`, `refunded_at` | refund-এর সময়সীমা |

## Staff-এর আয়

| টেবিল | কলাম |
|---|---|
| `staff_profiles` | `user_id` (unique), `payment_method`, `bank_name`, `branch_name`, `account_holder_name`, `bank_account_number` (encrypted), `mobile_banking_provider`, `mobile_banking_number` (encrypted), `total_questions_approved`, `total_earned`, `total_paid` |
| `question_rates` | `subject_id` (null = ডিফল্ট), `rate_amount`, `effective_from` |
| `staff_earnings` | `staff_id`, `question_id`, `amount` (approve-এর মুহূর্তের rate), `status`, `payout_id` |
| `staff_payouts` | `staff_id`, `total_amount`, `status`, `paid_at`, `reference_note`, `paid_by` |

## সেটিংস

### `billing_settings`

একটাই সারি; `BillingSetting::current()` দিয়ে পড়া হয়। প্রতিটা কলামের অর্থ ও ডিফল্ট: [09-admin-settings.md](09-admin-settings.md)।
