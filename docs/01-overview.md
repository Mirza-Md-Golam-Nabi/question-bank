# ০১ — এক নজরে পুরো সিস্টেম

## প্ল্যাটফর্মটা কী

একটা প্রশ্ন ব্যাংক + অনলাইন পরীক্ষা + subscription প্ল্যাটফর্ম। মূল ধারণা তিনটা:

1. **প্রশ্নের একটা যাচাই-করা ভাণ্ডার।** Teacher ও Staff প্রশ্ন জমা দেয়, Admin approve করলে সেটা সবার ব্যবহারের ভাণ্ডারে (approved pool) যায়।
2. **সেই ভাণ্ডার থেকে পরীক্ষা।** Teacher প্রশ্ন বেছে পরীক্ষা বানায়, শেয়ার-লিংক দিয়ে student-দের দেয় বা প্রিন্ট করে। Student নিজেও অনুশীলনের পরীক্ষা বানাতে পারে।
3. **আয়।** Teacher ও Student মাসে নির্দিষ্ট সংখ্যক পরীক্ষা ফ্রি পায়; বেশি চাইলে subscription কেনে। Staff প্রতিটা approved প্রশ্নের জন্য টাকা পায়।

## চার প্যানেল

| প্যানেল | URL | কারা | লগইন |
|---|---|---|---|
| Admin | `/admin` | Super Admin, Admin | email + password |
| Teacher | `/teacher` | Teacher | শুধু Google |
| Staff | `/staff` | Staff | শুধু Google |
| Student | `/student` | Student | শুধু Google |

একজন user-এর একটাই role, আর সে শুধু নিজের role-এর প্যানেলে ঢুকতে পারে। এর বাইরে আছে **Guest**: একাউন্ট ছাড়া, শুধু শেয়ার-লিংক দিয়ে পরীক্ষা দেয় (`/exam/{token}`)।

## কে কী পারে

| Role | পারে | পারে না |
|---|---|---|
| Super Admin / Admin | সব প্রশ্ন ও বোর্ড-পেপার দেখা, approve/reject; Staff approve/suspend; payout; plan ও পেমেন্ট ম্যানেজ; সব সেটিং | Google দিয়ে সাইনআপ, student হিসেবে পরীক্ষা |
| Teacher | নিজের প্রশ্ন যোগ/এডিট; approved pool ও নিজের pending প্রশ্ন দিয়ে পরীক্ষা; ফলাফল দেখা; subscription কেনা; refer করা | অন্যের pending প্রশ্ন দেখা; Staff earning দেখা |
| Staff | নিজের প্রশ্ন যোগ/এডিট; নিজের earning ও payout দেখা; refer করা | পরীক্ষা বানানো; approved pool দেখা; JSON ইমপোর্ট; Admin approve করার আগে কিছুই |
| Student | শেয়ার-লিংকের পরীক্ষা; self-practice (তিন মোড); নিজের ফলাফল; subscription কেনা; refer করা | পরীক্ষার বাইরে প্রশ্ন ব্রাউজ; অন্যের ফলাফল |
| Guest | নির্দিষ্ট শেয়ার-লিংকের পরীক্ষা; নাম + ফোন/ইমেইল দিয়ে নিজের ফলাফল খোঁজা | বাকি সব |

বিস্তারিত নিয়ম: [04-authentication.md](04-authentication.md)।

## পুরো ফ্লো

```
Admin  ─ class / subject / chapter / topic সাজায়
           │
Teacher/Staff ─ প্রশ্ন যোগ করে (হাতে বা JSON দিয়ে) ──► status = pending
           │
Admin  ─ approve ──► approved pool        (Staff হলে earning যোগ হয়)
       └ reject  ──► লেখক ঠিক করে আবার জমা দেয়
           │
Teacher ─ "প্রশ্ন বাছাই": approved pool + নিজের pending থেকে টিক ──► exam (draft)
           │                                  (মাসিক লিমিট এখানে একবার গোনা হয়)
           ├─ publish ──► শেয়ার-লিংক ──► Student (লগইন) / Guest পরীক্ষা দেয়
           └─ প্রিন্ট / PDF
           │
খাতা জমা ─► auto-grade ─► স্কোর
           ├─ প্রশ্নের difficulty-র গণনা বাড়ে
           ├─ student-এর ভুলের তালিকা হালনাগাদ হয়
           └─ Teacher-এর "ছাত্ররা কোথায় ভুল করে" গণনা বাড়ে
           │
Teacher ─ উত্তর প্রকাশ ──► student প্রশ্ন, সঠিক উত্তর ও নিজের উত্তর দেখে
           │
Student ─ self-practice: Auto / Manual / নিজের ভুলগুলো
```

## মূল ধারণাগুলো

- **Approved pool:** `status = approved` এবং `is_latest = true` এমন প্রশ্ন। অন্যের প্রশ্ন শুধু এই অবস্থাতেই দেখা ও ব্যবহার করা যায়। → [05-question-bank.md](05-question-bank.md)
- **Version:** approved প্রশ্ন এডিট করলে পুরনোটা অক্ষত থাকে, নতুন একটা pending সংস্করণ তৈরি হয়; তাই পুরনো পরীক্ষা কখনো বদলায় না।
- **Delivery mode:** পরীক্ষা `online` (শুধু MCQ), `offline` (প্রিন্ট, MCQ + CQ) বা `both`। CQ কখনো অনলাইনে নেওয়া হয় না। → [06-exams.md](06-exams.md)
- **উত্তর প্রকাশ:** Teacher-এর পরীক্ষায় জমা দেওয়ার পর student শুধু স্কোর দেখে; Teacher প্রকাশ করলে তবে উত্তর। উত্তর ফাঁস ঠেকানোর এই নিয়মটা ভুলের অনুশীলনেও মানা হয়। → [07-student-practice.md](07-student-practice.md)
- **হিসাব জমা থাকে, বারবার গোনা হয় না:** difficulty, ভুলের তালিকা আর Teacher-এর পরিসংখ্যান খাতা জমার সময়ই হালনাগাদ হয়, তাই পুরনো পরীক্ষার হিস্টোরি মুছলেও এগুলো থাকে।
- **কোনো cron নেই:** মাসিক লিমিট, উত্তর প্রকাশের সময়, wallet credit-এর মেয়াদ, referral reward পাকা হওয়া — সবই দরকারের মুহূর্তে তারিখ মিলিয়ে দেখা হয়; ব্যাকগ্রাউন্ডে কিছু চালাতে হয় না।
- **ব্যবসায়িক সংখ্যা কোডে নেই:** শতাংশ, দিন, সীমা — সব Admin-এর settings-এ। → [09-admin-settings.md](09-admin-settings.md)
