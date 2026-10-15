# ০৯ — Admin-এর সেটিংস

সব সেটিং এক জায়গায়: Admin প্যানেলের **Billing Settings** পেজ (`App\Filament\Pages\BillingSettings`), যা `billing_settings` টেবিলের একমাত্র সারিতে লেখে। কোডে পড়া হয় `BillingSetting::current()` দিয়ে (অনুরোধপ্রতি একবার)।

নামে "Billing" হলেও এখানে প্রশ্ন ব্যাংকের সেটিংও আছে; আলাদা সেটিংস পেজ এখনো নেই।

**মূলনীতি:** কোনো ব্যবসায়িক সংখ্যা (শতাংশ, দিন, সীমা) কোডে লেখা থাকে না। নতুন কোনো সংখ্যা লাগলে এখানে একটা কলাম যোগ করতে হবে, কোডে constant না। কারিগরি সুরক্ষা-সীমা (যেমন একটা সূত্রের সর্বোচ্চ দৈর্ঘ্য) constant থাকতে পারে।

সেটিং বদলানোর log থাকে (কে, কী বদলেছে)।

## পেমেন্ট গ্রহণ

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| bKash / Nagad / Rocket নম্বর | `bkash_number`, `nagad_number`, `rocket_number` | ফাঁকা | গ্রাহক এই নম্বরে টাকা পাঠায়। নম্বর না দিলে ওই মাধ্যমটা দেখানো হয় না। |
| বাড়তি নির্দেশনা | `payment_instructions` | ফাঁকা | কেনার পেজে গ্রাহককে দেখানো লেখা |

## Referral

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| Referral চালু | `referral_enabled` | বন্ধ | বন্ধ থাকলে কোড দেওয়া, discount ও reward কিছুই হয় না |
| Teacher/Student referrer-এর reward (%) | `referrer_reward_percent` | ০ | নতুন গ্রাহকের প্রথম কেনার নগদ অংশের এত শতাংশ, wallet credit হিসেবে |
| Staff referrer-এর reward (%) | `staff_reward_percent` | ০ | একই, কিন্তু নগদে, payout-এর সাথে |
| নতুন গ্রাহকের discount (%) | `referee_discount_percent` | ০ | refer হয়ে আসা গ্রাহকের প্রথম কেনায় |
| Wallet credit-এর মেয়াদ (মাস) | `credit_expiry_months` | ফাঁকা | ফাঁকা = মেয়াদ নেই |

Reward-এর পরিমাণ পেমেন্ট approve হওয়ার মুহূর্তের শতাংশে স্থির হয়; পরে বদলালে পুরনো reward বদলায় না।

## Refund

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| Refund-এর সময়সীমা (দিন) | `refund_window_days` | ০ | approve-এর পর এত দিন পর্যন্ত পেমেন্ট refund করা যায়; referral reward-ও এত দিন পর পাকা হয়। ০ = refund নেই, reward সাথে সাথে পাকা। |

## ফোন নম্বর

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| ফোন দিলে মাসে বাড়তি ফ্রি পরীক্ষা | `phone_bonus_exams` | ০ | সীমিত plan-এর মাসিক লিমিটে যোগ হয় |
| Wallet credit খরচে OTP | `otp_required_for_credit` | বন্ধ | SMS provider যুক্ত না করা পর্যন্ত বন্ধ রাখুন; নইলে কোড পৌঁছাবে না |

## প্রশ্ন ব্যাংক

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| Teacher-রা JSON দিয়ে প্রশ্ন যোগ করতে পারবে | `teacher_question_import_enabled` | চালু | বন্ধ করলে বাটনটা শুধু Admin দেখে, আর সার্ভারও Teacher-এর ইমপোর্ট আটকায় |
| একবারের JSON ইমপোর্টে সর্বোচ্চ প্রশ্ন | `question_import_max` | ১০০ | ১ থেকে ১০০০ |

## প্রশ্নের কঠিনতা

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| হিসাবের আগে যত উত্তর লাগবে | `difficulty_min_answers` | ৩০ | এর আগে লেখকের দেওয়া difficulty থাকে |
| Hard: এত % পর্যন্ত সঠিক | `difficulty_hard_max_percent` | ৩৩ | Medium-এর সীমার চেয়ে ছোট হতে হবে |
| Medium: এত % পর্যন্ত সঠিক | `difficulty_medium_max_percent` | ৬৬ | এর বেশি হলে Easy |

নতুন সংখ্যা পরের খাতা জমা থেকে কার্যকর হয়, আর শুধু সেই খাতার প্রশ্নগুলোতে; সব প্রশ্ন একসাথে নতুন করে হিসাব হয় না। → [05-question-bank.md](05-question-bank.md)

## ভুলের অনুশীলন

| সেটিং | কলাম | ডিফল্ট | কাজ |
|---|---|---|---|
| পরপর কতবার সঠিক হলে ভুলটা বাদ যাবে | `mistake_clear_streak` | ২ | ১ থেকে ২০। → [07-student-practice.md](07-student-practice.md) |

## সেটিংসের বাইরে Admin যা ঠিক করে

এগুলো আলাদা পেজে, কিন্তু একই রকম "কোডে না, Admin-এর হাতে":

| কী | কোথায় |
|---|---|
| Plan, দাম, মাসিক লিমিট, ডিফল্ট ফ্রি plan | `SubscriptionPlanResource` |
| বিষয়ভিত্তিক ও ডিফল্ট প্রশ্নের rate | `QuestionRateResource` |
| Class, subject, chapter, topic | প্রশ্নের কার্ড পেজ, `SubjectResource` |
| Board | `BoardResource` |

## নতুন সেটিং যোগ করতে

1. `billing_settings`-এ কলাম যোগ করার migration (ডিফল্ট মানসহ)।
2. `App\Models\BillingSetting`: `Fillable`, `$attributes` (ডিফল্ট) ও `casts()`।
3. `App\Filament\Pages\BillingSettings`: ফর্মে ঘর, যাচাইসহ।
4. `lang/bn.json`: লেবেলের বাংলা।
5. এই ফাইলে একটা সারি, আর [03-database.md](03-database.md)-এ কলামটা।

টেস্ট: `SubscriptionPurchaseFlowTest` (সেটিং সেভ), আর প্রতিটা ফিচারের নিজের টেস্টে তার সেটিং।
