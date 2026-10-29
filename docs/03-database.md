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
| `google_id` (unique, nullable), `avatar` | শুধু Teacher/Staff/Student-এ; `avatar` = Google-এর ছবির লিংক, প্রতি Google লগইনে হালনাগাদ — প্যানেলের মেনু ও প্রোফাইলে দেখায় (`User::getFilamentAvatarUrl()`), না থাকলে নামের অক্ষর |
| `role` | `UserRole`; ডিফল্ট `student` |
| `status` | `UserStatus`; ডিফল্ট `active` |
| `phone`, `phone_verified_at` | প্রোফাইলে দেওয়া ফোন নম্বর |
| `referral_code` (unique, nullable) | নিজের কোড; প্রথমবার চাওয়ার সময় তৈরি হয় |
| `referred_by_id` → `users` | Teacher-কে যে refer করেছে (স্থায়ী); student-এর referrer `student_referrals`-এ |
| `academic_class_id` → `academic_classes` (nullable) | Student-এর শ্রেণি; self-practice ও কেনার শুরু এখান থেকে |

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

লোকালে শুরু করার শ্রেণি ও বিষয়: `AcademicClassSeeder`, তারপর `SubjectSeeder` — দুটোই শুধু `APP_ENV=local`-এ চলে, লাইভে কিছু করে না। `SubjectSeeder` প্রতিটা বিষয়ের পাশে লেখা শ্রেণিগুলোতে তাকে assign করে (`class_subjects`, তালিকার ক্রমে); আবার চালালে আগের assign (Admin-এর করা হলেও) যেমন আছে তেমন থাকে। লাইভে শ্রেণিতে বিষয় যোগ করেন Admin।

Chapter সরাসরি `class_subjects`-এর সাথে যুক্ত, `subjects`-এর সাথে না: একই subject ভিন্ন class-এ ভিন্ন chapter রাখে।

## প্রশ্ন

### `questions`

| কলাম | টীকা |
|---|---|
| `chapter_id`, `topic_id` (nullable) | |
| `stimulus_id` (nullable) → `question_stimuli`, `stimulus_order` | উদ্দীপকভিত্তিক MCQ: কোন উদ্দীপকের, তার নিচে কত নম্বরে |
| `question_type` | `mcq` / `cq` |
| `question_text` | HTML। CQ-তে এটা উদ্দীপক। সূত্র: `<span class="qb-katex-embed">latex</span>` |
| `question_image` | public disk-এর পাথ |
| `options` (JSON) | শুধু MCQ: `[{option, image, is_correct}]`; ঠিক একটা `is_correct` |
| `explanation` (nullable) | শুধু MCQ: সঠিক উত্তরের ব্যাখ্যা (HTML); দেখায় শুধু যেখানে সঠিক উত্তর দেখায় |
| `explanation_editor_mode` | `richtext` / `ckeditor` — ব্যাখ্যা কোন এডিটরে লেখা (ডিফল্ট `richtext`; আগের সব ব্যাখ্যা ও JSON-এর ব্যাখ্যা `ckeditor`) |
| `marks` | MCQ-তে লেখক দেয়; CQ-তে অংশগুলোর যোগফল, নিজে থেকে বসে |
| `difficulty` | লেখকের বাছাই; যথেষ্ট উত্তর জমলে সিস্টেম বদলায় |
| `answer_count`, `correct_answer_count` | difficulty-র গণনা (শুধু Teacher-এর পরীক্ষা থেকে) |
| `editor_mode` | `richtext` / `ckeditor` |
| `status` | `pending` / `approved` / `rejected` |
| `created_by`, `approved_by`, `rejection_reason` | |
| `parent_id`, `version`, `is_latest` | versioning; `parent_id` সবসময় প্রথম সংস্করণকে নির্দেশ করে |
| `is_private` | School-এর নিজের প্রশ্ন (School লিখলে নিজে থেকে; নতুন সংস্করণেও): শুধু সেই School দেখে, pool/Admin-এর রিভিউতে কখনো না, status সবসময় `pending` — [12-school-link.md](12-school-link.md) |
| `deleted_at` | soft delete |
| `duplicate_status` | পেছনের ডুপ্লিকেট যাচাই: `checking` / `clear` / `suspected` (ডিফল্ট `clear`; নতুন বা বদলানো প্রশ্ন `checking`, School-এর নিজেরটা সবসময় `clear`)। শুধু `clear` pool-এ, পরীক্ষায় ও approve-এ যায় — [05-question-bank.md](05-question-bank.md#পেছনের-ডুপ্লিকেট-যাচাই-queue) |
| `duplicate_guard` (nullable), `duplicate_size` (nullable) | যাচাইয়ের "fingerprint": সংখ্যা/সূত্র/না-বাচক শব্দের sha1, আর প্রশ্নের তিন-অক্ষরের টুকরোর সংখ্যা — শুধু এই দুটো মেলা প্রশ্নই পড়া হয়। যাচাই হলে বসে; ফাঁকা থাকলে সবসময় পড়া হয় |

Index: `(status, is_latest)`, `(chapter_id, status, is_latest)`, `(created_by)`, `(created_by, is_private, is_latest)`, `(duplicate_status)`, `(chapter_id, question_type, duplicate_guard, duplicate_size)`।

### `question_duplicate_matches`

পেছনের যাচাইয়ে একটা প্রশ্ন অধ্যায়ের কোন প্রশ্নগুলোর মতো দেখতে (সবচেয়ে বেশি মিলের ৫টা): `question_id`, `matched_question_id`, `percent`, `is_exact`। Admin "রাখুন" বললে মুছে যায়; দুই প্রশ্নের যেকোনোটা স্থায়ীভাবে মুছলে সাথে মুছে যায়।

### `question_cq_parts`

CQ-র চার অংশ: `question_id`, `part_type`, `part_order`, `part_text`, `part_image`, `marks`; unique(question, part_type)।

### `question_approval_logs`

প্রতিটা approve/reject/resubmit-এর রেকর্ড: `question_id`, `action`, `performed_by`, `reason`।

## বোর্ড প্রশ্নপত্র (আলাদা সাবসিস্টেম)

| টেবিল | কলাম |
|---|---|
| `boards` | `name`, `name_bn`, `short_name`, `short_name_bn` (ইংরেজি ও বাংলা, পূর্ণ ও সংক্ষিপ্ত), `type` (`board`/`medical`/`university`/`job`/`school_test` — প্রশ্নপত্রের উৎসের ধরন), `order_index` |
| `board_question_papers` | `board_id`, `class_subject_id` (nullable — ভর্তি/চাকরির পেপারে নেই), `year`, `unit` ('' = নেই), `duration_minutes`, `negative_marks` (nullable), `status`, `created_by`, `approved_by`, `rejection_reason`; unique(board, class_subject, year, unit) — class_subject ফাঁকা পেপারের ডুপ্লিকেট `BoardQuestionPaper::isTaken()` ধরে |
| `question_stimuli` | `chapter_id`, `text`, `editor_mode`, `image`, `created_by` — একাধিক MCQ-র অভিন্ন উদ্দীপক; নিজের status নেই, প্রশ্নের সাথেই দেখা যায় |
| `board_mcq_stimuli` | `board_question_paper_id`, `text`, `editor_mode`, `image` — প্রশ্নপত্রের উদ্দীপক; মুছলে তার প্রশ্নও মোছে |
| `board_mcq_questions` | `board_question_paper_id`, `stimulus_id` (nullable), `stimulus_order`, `question_text`, `editor_mode`, `question_image`, `options`, `explanation` (nullable), `explanation_editor_mode`, `marks`, `order_index` |
| `board_cq_questions` | `board_question_paper_id`, `question_text`, `editor_mode`, `question_image`, `marks`, `order_index` |
| `board_cq_question_parts` | `board_cq_question_id`, `part_type`, `part_order`, `part_text`, `part_image`, `answer` (nullable), `marks` |

এগুলোর সাথে `questions` টেবিলের কোনো সম্পর্ক নেই, ইচ্ছা করেই।

## পরীক্ষা

### `exams`

| কলাম | টীকা |
|---|---|
| `title`, `created_by` | Teacher-এর পরীক্ষায় Teacher, self-practice-এ student |
| `subscription_id` → `subscriptions` (nullable) | কোন subscription-এর সীমায় গোনা হয়েছে; ফাঁকা = ফ্রি plan |
| `negative_marks` (nullable) | প্রতি ভুল MCQ উত্তরে কাটা নম্বর; ফাঁকা = নেগেটিভ মার্কিং নেই (শুধু অনলাইনে নেওয়া পরীক্ষায়) |
| `max_participants` (nullable) | সর্বোচ্চ কতজন পরীক্ষাটা শুরু করতে পারে — সেভের সময় Teacher-এর plan থেকে; ফাঁকা = সীমা নেই |
| `exam_type` | `teacher_exam` / `self_practice` |
| `generation_mode` | `manual` / `auto` / `mistakes` / `past_paper` |
| `delivery_mode` | `online` / `offline` / `both` |
| `subject_id`, `class_subject_id` (nullable) | পুরনো পরীক্ষায় `class_subject_id` ফাঁকা থাকতে পারে; নতুন self-practice-এও এখন থাকে। ভর্তি পরীক্ষার পেপারের পরীক্ষায় দুটোই ফাঁকা |
| `board_question_paper_id` (nullable) → `board_question_papers` | পুরো একটা বিগত সালের প্রশ্নপত্র — তখন প্রশ্ন `exam_questions`-এ না, পেপারের নিজের; পেপার মোছা যায় না (restrict) |
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

School-এর পরীক্ষার খাতায় আরও: `school_class_id`, `school_class_name`, `school_section_id`, `school_section_name` (স্কুলের software-এর id, নাম কপি হিসেবে), `school_roll` (এক রূপে); index (`exam_id`, `school_class_id`, `school_roll`) — নাম `guest_name`-এ, `is_guest` সত্য, `student_id` ফাঁকা ([12-school-link.md](12-school-link.md))। Index (`student_id`, `submitted_at`): student-এর পারফরম্যান্স বিশ্লেষণ নতুন থেকে পুরনো খাতা পড়ে ([11-performance-analytics.md](11-performance-analytics.md))।

### `attempt_answers`

`attempt_id`, `question_id` (nullable), `board_mcq_question_id` (nullable), `student_answer`, `is_correct`, `obtained_marks`; unique(attempt, question), unique(attempt, board_mcq_question)। প্রশ্নপত্রের পরীক্ষায় উত্তর `board_mcq_question_id`-এ, অন্যটায় `question_id`-এ (`Exam::answerColumn()`)। শুধু উত্তর দেওয়া প্রশ্নের সারি থাকে; ফাঁকা রাখা প্রশ্নের কোনো সারি নেই।

### `school_profiles`

একটা School একাউন্টের তথ্য ও স্কুলের software-এর সংযোগ, প্রতিটা School-এর একটা (`user_id` unique) — [12-school-link.md](12-school-link.md)।

| কলাম | টীকা |
|---|---|
| `user_id` | School user |
| `name`, `eiin`, `address`, `phone` | School approve-এর আগে দেয়, Admin দেখে বদলাতে পারেন |
| `connection_code` | unique, `QB-` + ১০ অক্ষর; স্কুলের software নিজেকে এটা দিয়ে চেনায় |
| `connection_secret` | `encrypted`; দুই দিকের অনুরোধ সই করে, কখনো পাঠানো হয় না |
| `installation_url`, `connected_at` | স্কুলের software শেষ যেখান থেকে যুক্ত হলো; নতুন secret বানালে ফাঁকা |

### `school_result_pushes`

School প্রতিবার স্কুলের software-এর রেজাল্টে MCQ নম্বর পাঠালে একটা সারি (audit) — [12-school-link.md](12-school-link.md)।

| কলাম | টীকা |
|---|---|
| `exam_id`, `sent_by` | কোন পরীক্ষার নম্বর, কোন School user পাঠাল |
| `school_class_id`/`_name`, `school_exam_id`/`_name`, `school_subject_id`/`_name` | স্কুলের software-এর id, নাম কপি হিসেবে |
| `school_full_marks`, `in_proportion` | স্কুলের MCQ পূর্ণমান; অনুপাতে বসানো হলো কি না |
| `overwrite` | আগের নম্বর বদলানোর অনুমতি ছিল কি না |
| `sent_count`, `saved_count`, `skipped` | কয়টা গেল, কয়টা সেভ হলো; বাদের তালিকা (json: ছাত্র, কারণ) |

## উত্তর থেকে জমানো হিসাব

### `student_question_mistakes`

একজন student-এর ভুলের তালিকা: `student_id`, `question_id` (প্রশ্নের **প্রথম সংস্করণের** id), `correct_streak`, `locked_by_exam_id` (যে Teacher-এর পরীক্ষার উত্তর এখনো লুকানো); unique(student, question)।

### `teacher_question_stats`

একজন Teacher-এর ছাত্ররা একটা প্রশ্নে কেমন করেছে: `teacher_id`, `question_id` (প্রথম সংস্করণের id), `answer_count`, `wrong_count`; unique(teacher, question)।

## Subscription ও পেমেন্ট

| টেবিল | কলাম |
|---|---|
| `subscription_plans` | `name`, `target_role` (`teacher`/`student`), `price`, `duration_unit` (`days`/`months`/`years`/`until_date`/`unlimited`), `duration_length`, `ends_on`, `conditions` (JSON, শর্তের তালিকা — [08-billing.md](08-billing.md)), `is_default_free`, `is_trial`, `is_active` |
| `subscriptions` | `user_id`, `plan_id`, `conditions` (কেনার সময়ের শর্তের কপি), `selections` (ক্রেতার বাছাই করা `class_ids`/`subject_ids`), `status`, `starts_at`, `ends_at`, `auto_renew` |
| `payments` | নিচে |
| `wallet_transactions` | `user_id`, `type`, `amount`, `payment_id`, `withdrawal_id`, `expires_at` — শুধু যোগ হয়, কখনো বদলায় না |
| `referral_rewards` | `referrer_id`, `payment_id` (unique), `amount`, `percent` (দেওয়ার মুহূর্তের শতাংশ), `matures_at`, `expires_at` (আর বসানো হয় না), `cancelled_at`, `payout_id` |
| `student_referrals` | `student_id`, `referrer_id`, `source` (`code`/`link`/`exam`), `exam_id`, `starts_at`, `changeable_until` (নিজে লেখা কোড এই পর্যন্ত বদলানো যায়), `ends_at` — একজন student একবারে একটা চালু; শেষ হলে মোছা হয় না |
| `subscription_members` | `subscription_id`, `email` (সদস্যের Google email; একাউন্ট খোলার আগেও যোগ করা যায়), `removed_at` — group plan-এর অন্য সদস্যরা; মোছা হয় না, শুধু চিহ্নিত |
| `withdrawals` | `user_id` (Teacher বা Student), `amount`, `provider`, `account_number` (`encrypted`), `number_used_by_referred_buyer` (জালিয়াতির সংকেত), `status` (`requested`/`paid`/`rejected`), `processed_by`, `processed_at`, `transaction_id`, `note` |

### `payments`

| কলাম | টীকা |
|---|---|
| `user_id`, `plan_id`, `subscription_id` | |
| `referrer_id`, `student_referral_id` | কেনার মুহূর্তে ক্রেতার referrer (ও student হলে কোন referral-এর অধীনে) — reward ও রিপোর্ট এখান থেকে |
| `selections` | কেনার সময় ক্রেতা যে শ্রেণি/বিষয় বেছেছে; approve হলে subscription-এ যায় |
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
| `staff_earnings` | `staff_id`, `question_id` বা `board_question_paper_id` (একটা; পেপারপ্রতি একবারই — unique), `amount` (approve-এর মুহূর্তের rate), `status`, `payout_id` |
| `staff_payouts` | `staff_id`, `total_amount`, `status`, `paid_at`, `reference_note`, `paid_by` |

## সেটিংস

### `billing_settings`

একটাই সারি; `BillingSetting::current()` দিয়ে পড়া হয়। প্রতিটা কলামের অর্থ ও ডিফল্ট: [09-admin-settings.md](09-admin-settings.md)।

## নোটিফিকেশন

### `notifications`

Laravel-এর নিজস্ব টেবিল — প্রতিটা প্যানেলের বেলে যা দেখায় (Filament database notifications): `id` (uuid), `type`, `notifiable_type`/`notifiable_id` (user), `data` (JSON: title, body, icon, বোতাম), `read_at`। লেখা হয় শুধু `BellNotifier` দিয়ে। → [08-billing.md](08-billing.md#বেলের-নোটিফিকেশন)

### `push_subscriptions`

`laravel-notification-channels/webpush`-এর টেবিল: একজন user-এর যে ডিভাইসে push চালু, তার প্রতিটার একটা সারি — `subscribable_type`/`subscribable_id` (user), `endpoint` (unique), `public_key`, `auth_token`, `content_encoding`। → [13-app-and-offers.md](13-app-and-offers.md)

### `offers`

Admin-এর লেখা অফার ও খবর — [13-app-and-offers.md](13-app-and-offers.md)।

| কলাম | টীকা |
|---|---|
| `title`, `body`, `image` | শিরোনাম, লেখা, ঐচ্ছিক ছবি (public disk, `offers/`) |
| `button_label`, `button_url` | ঐচ্ছিক বাটন; নোটিফিকেশন চাপলেও এখানে যায় |
| `target_roles` (JSON) | `student` / `teacher` / `school` / `staff`-এর তালিকা |
| `academic_class_ids` (JSON, nullable) | Student-এর জন্য শুধু এই শ্রেণিগুলো (সংখ্যা হিসেবে); ফাঁকা = সব শ্রেণি |
| `starts_at`, `ends_at`, `is_active` | কখন চালু (`Offer::scopeLive()`) |
| `notified_at`, `notified_by` | বেলে/push-এ পাঠানো হলো কখন, কে পাঠালেন — একবারই |
| `created_by` | যে Admin লিখলেন |

Index: `(is_active, starts_at, ends_at)`।

### `guides`

Admin-এর লেখা গাইড — [14-guides.md](14-guides.md)।

| কলাম | টীকা |
|---|---|
| `slug` (nullable, unique) | seeder-এর খসড়ার নিজের চাবি — আবার seed করলে দুবার হয় না |
| `category` | `GuideCategory` |
| `title_bn`, `body_bn` | বাংলা (বাধ্যতামূলক); body rich text HTML, ছবিসহ |
| `title_en`, `body_en` (nullable) | ইংরেজি; না থাকলে বাংলা দেখায় |
| `video_url` (nullable) | YouTube লিংক |
| `target_roles` (JSON) | `student` / `teacher` / `staff` / `school` |
| `page_keys` (JSON, nullable) | যে পেজগুলোর "কীভাবে করবেন?" বাটনে খোলে (`GuidePage`) |
| `sort_order`, `is_active` | বিভাগের ভেতরে ক্রম; চালু/বন্ধ |

Index: `(is_active, category, sort_order)`। `users.onboarding_dismissed_at` — user "শুরু করুন" তালিকা বন্ধ করলেন কখন।

### `offer_dismissals`

`offer_id`, `user_id`; unique(offer, user) — user ড্যাশবোর্ডের ব্যানারে অফারটা বন্ধ করেছেন, আর দেখানো হয় না।
