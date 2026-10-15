# ০৮ — Subscription, পেমেন্ট, Referral, Wallet ও Staff-এর আয়

নিয়মের উৎস: CLAUDE.md নিয়ম ৬ ও ৭। এখানকার প্রতিটা সংখ্যা (শতাংশ, দিন, বোনাস) Admin-এর সেটিং — [09-admin-settings.md](09-admin-settings.md)।

টাকার লজিক কখনো পেজ বা controller-এ থাকে না; সবই নিচের service ও model-এ।

## Plan ও মাসিক লিমিট

- `subscription_plans`: প্রতিটা plan একটা role-এর জন্য (`teacher` বা `student`), দাম, billing cycle (`free`/`monthly`/`yearly`), আর `monthly_exam_limit` (ফাঁকা = সীমাহীন)।
- প্রতি role-এর একটা **ডিফল্ট ফ্রি plan** থাকতে পারে (`is_default_free`)।

**কার কোন plan খাটে** (`SubscriptionLimitService::planSummaryFor()`):
1. সক্রিয় subscription থাকলে তার plan।
2. নইলে role-এর ডিফল্ট ফ্রি plan।
3. সেটাও না থাকলে কোনো plan নেই, আর তখন লিমিটও নেই।

**মাসিক লিমিট** = plan-এর `monthly_exam_limit` + (user ফোন নম্বর দিয়ে থাকলে) Admin-এর ঠিক করা বোনাস। সীমাহীন plan-এ বোনাস প্রযোজ্য না।

**গণনা:** আলাদা কোনো counter নেই। ওই মাসে ওই user কতগুলো পরীক্ষা বানিয়েছে তা সরাসরি query করে গোনা হয়, তাই মাস বদলালে নিজে থেকেই শূন্য থেকে শুরু। Teacher-এর পরীক্ষা আর student-এর self-practice আলাদা গোনা হয়; self-practice-এর তিন মোড একসাথে।

**কোথায় দেখা হয়:**
- Teacher পরীক্ষা সেভ করার সময় (`TeacherExamBuilder::build()`); publish-এর সময় দেখা হয় এই পরীক্ষাটা অনুমোদিতগুলোর মধ্যে ছিল কি না।
- Student self-practice শুরু করার আগে।
- Guest-এর পরীক্ষা দেওয়ায় কোনো চেক নেই; সেটা Teacher-এর লিমিটেই ধরা।

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
| `refund` | Admin | টাকা ফেরত দেওয়া হয়েছে (সিস্টেমের বাইরে): subscription এখনই শেষ; wallet credit ফেরত; referrer-এর reward বাতিল। **শুধু refund-এর সময়সীমার ভেতরে।** |

- **Idempotency:** প্রতিটা ধাপ একটা database transaction, যেটা আগে পেমেন্ট lock করে তার status আবার দেখে। দুইবার ক্লিক বা (ভবিষ্যতে) gateway-র callback দুইবার এলে দ্বিতীয়বার কিছু হয় না। একই transaction id দুইবার জমা দেওয়াও যায় না (unique)।
- **মেয়াদ জোড়া লাগে:** একই plan-এর সক্রিয় subscription থাকলে নতুনটার মেয়াদ আগেরটার শেষ থেকে গোনা হয়।
- **OTP:** Admin চালু করলে wallet credit খরচের সময় ফোনে কোড চাওয়া হয় (`OtpService`)। SMS provider যুক্ত না হওয়া পর্যন্ত এটা বন্ধ রাখার কথা।
- **Gateway এলে** শুধু `approve` ডাকার পথটা বদলাবে (Admin-এর জায়গায় callback)।
- প্রতিটা ধাপ log হয়।

Admin-এর পেজ: `PaymentResource` (approve, reject, refund), `SubscriptionResource`, `SubscriptionPlanResource`।

## Referral

নিয়মগুলো `App\Services\ReferralService`-এ।

- **কে refer করতে পারে:** যেকোনো Teacher বা Student; আর সক্রিয় Staff।
- **কাকে refer করা যায়:** যারা subscription কেনে — Teacher ও Student।
- **কোড:** প্রত্যেকের নিজের ৮ অক্ষরের কোড (গুলিয়ে ফেলার মতো অক্ষর ছাড়া), প্রথমবার চাওয়ার সময় তৈরি। শেয়ার করার লিংক `/r/{code}`।
- **কোড কখন যুক্ত হয়:** লিংক দিয়ে এলে একাউন্ট তৈরির মুহূর্তে; অথবা প্রোফাইল পেজে হাতে লিখে, **প্রথম কেনার আগ পর্যন্ত**।
- **এক স্তর:** A যদি B-কে আনে আর B আনে C-কে, A শুধু B-র জন্য reward পায়।

**নতুন গ্রাহকের সুবিধা:** প্রথম কেনায় discount (শতাংশ)।

**Referrer-এর reward** (`rewardFor()`), পেমেন্ট approve হওয়ার সময়:
- শুধু ক্রেতার **প্রথম সফল** পেমেন্টে।
- শুধু **নগদে দেওয়া** অংশের ওপর; wallet credit দিয়ে দেওয়া অংশে না (credit থেকে যেন আরও credit না জন্মায়)।
- শতাংশ সেই মুহূর্তের সেটিং থেকে স্থির হয়ে যায়; পরে সেটিং বদলালে পুরনো reward বদলায় না।
- Teacher/Student referrer-এর আর Staff referrer-এর শতাংশ আলাদা।

**Reward-এর অবস্থা** (`referral_rewards`; তারিখ থেকে পড়া, সংরক্ষিত না):

| অবস্থা | মানে |
|---|---|
| maturing | পেমেন্টের refund-এর সময়সীমা এখনো চলছে |
| available | সময়সীমা শেষ; এখন এটা referrer-এর |
| paid | (Staff) payout-এ দেওয়া হয়েছে |
| cancelled | সময়সীমার ভেতরে refund হয়েছে; reward কখনো কারও হয়নি |

Refund শুধু সময়সীমার ভেতরে হয় বলে, পাকা হয়ে যাওয়া reward কখনো ফেরত নিতে হয় না।

**Reward কোথায় যায়:**
- Teacher/Student → wallet credit।
- Staff → নগদ, পরের payout-এ earning-এর সাথে।

**জালিয়াতির সংকেত:** পেমেন্ট যে নম্বর থেকে এসেছে সেটা referrer-ও ব্যবহার করলে Admin-এর পেমেন্ট তালিকায় চিহ্নিত হয়।

Admin-এর রিপোর্ট: `ReferralReport` পেজ — প্রতি referrer কতজনকে এনেছে, কতজন কিনেছে, কত নগদ এসেছে, reward-এ কত খরচ।

## Wallet

`App\Services\WalletService` — credit যোগ, খরচ ও গণনার একমাত্র জায়গা।

- Wallet শুধু Teacher ও Student-এর। Credit কখনো নগদ হয়ে সিস্টেম থেকে বের হয় না; শুধু নিজের subscription কিনতে খরচ হয়।
- **Balance সংরক্ষিত না।** প্রতিবার হিসাব হয়: পাকা হওয়া (ও মেয়াদ না-ফুরানো) reward − খরচ + ফেরত। ফলে কোনো সংখ্যা তাল হারায় না, আর reward পাকানো বা মেয়াদ শেষ করার জন্য কোনো scheduled job লাগে না।
- `wallet_transactions`-এ শুধু সারি যোগ হয় (`purchase`, `purchase_return`); ভুল সংশোধনও নতুন সারি দিয়ে।
- Credit-এর মেয়াদ Admin-এর সেটিং (মাসে); ফাঁকা রাখলে মেয়াদ নেই।

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
| লিমিট | `app/Services/SubscriptionLimitService.php` |
| কেনা | `app/Services/SubscriptionPurchaseService.php`, `app/Models/Payment.php`, `Subscription.php`, `SubscriptionPlan.php` |
| Referral | `app/Services/ReferralService.php`, `app/Models/ReferralReward.php`, `app/Http/Controllers/ReferralLinkController.php` |
| Wallet | `app/Services/WalletService.php`, `app/Models/WalletTransaction.php` |
| OTP | `app/Services/OtpService.php`, `app/Services/Sms/` |
| Staff | `app/Models/StaffEarning.php`, `StaffPayout.php`, `StaffProfile.php`, `QuestionRate.php` |
| পেজ | `app/Filament/Support/Pages/MySubscriptionPage.php`, `MyProfilePage.php` |

টেস্ট: `SubscriptionLimitServiceTest`, `SubscriptionPurchaseServiceTest`, `SubscriptionPurchaseFlowTest`, `SubscriptionResourcesTest`, `ReferralTest`, `StaffReferralTest`, `ReferralReportTest`, `WalletServiceTest`, `OtpServiceTest`, `StaffPayrollTest`, `StaffPayoutTest`, `StaffPayrollResourcesTest`।
