# ০৪ — Authentication ও প্যানেলে ঢোকা

নিয়মের উৎস: CLAUDE.md নিয়ম ১।

## দুই রকম লগইন

| কারা | কীভাবে | কোথায় |
|---|---|---|
| Super Admin, Admin | Filament-এর নিজস্ব email + password ফর্ম; password reset আছে; **public registration নেই** | `/admin/login` |
| Teacher, Staff, Student | শুধু "Continue with Google"; কোনো password, registration বা forget-password ফর্ম নেই | `/teacher/login`, `/staff/login`, `/student/login` |

Admin প্যানেলে registration বন্ধ রাখা হয়েছে `->registration()` না ডেকে (`AdminPanelProvider`)। প্রথম Super Admin তৈরি হয় `SuperAdminSeeder` দিয়ে, যার email/নাম/password আসে `config/question-bank.php` থেকে (অর্থাৎ `.env`)।

> **এখনকার অবস্থা:** Admin প্যানেলে নতুন Admin বানানোর কোনো পেজ নেই। আরেকজন Admin লাগলে seeder বা ডেটাবেজ দিয়ে বানাতে হবে।

## Google login-এর ধাপ

কোড: `App\Http\Controllers\Auth\GoogleAuthController`।

1. লগইন পেজের বাটন `/auth/google/redirect/{role}`-এ পাঠায়। role session-এ রাখা হয়; `admin`/`super_admin` দিলে 404।
2. Google থেকে `/auth/google/callback`-এ ফেরে।
3. `google_id` অথবা email মিলিয়ে user খোঁজা হয়।
   - **পাওয়া গেল, কিন্তু role আলাদা:** ঢুকতে দেওয়া হয় না; "This email is already linked to a different role" দেখিয়ে লগইন পেজে ফেরত। একটা email একটাই role-এ থাকে।
   - **পাওয়া গেল, role মিলেছে:** `google_id` ও ছবি হালনাগাদ করে লগইন।
   - **পাওয়া যায়নি:** নতুন user তৈরি।
4. Student যদি পরীক্ষার শেয়ার-লিংক থেকে এসে থাকে, সরাসরি সেই পরীক্ষায় যায়; নইলে নিজের প্যানেলের ড্যাশবোর্ডে।

### নতুন user

- Teacher ও Student: `status = active`।
- Staff: `status = pending` — Admin approve না করা পর্যন্ত কিছু করতে পারে না।
- `email_verified_at` সাথে সাথে বসে (Google যাচাই করেছে)।
- referral লিংক (`/r/{code}`) দিয়ে এসে থাকলে কোডটা session থেকে নিয়ে এই মুহূর্তে যুক্ত হয়। → [08-billing.md](08-billing.md)

## লগইন কতক্ষণ থাকে

সব প্যানেলে একই নিয়ম (Admin-সহ): user নিজে লগআউট না করা পর্যন্ত লগইন থাকে। Admin → Billing Settings → "লগইন থাকার সময়" (`login_stay_days`): দিনের সংখ্যা, অথবা ০ = লাইফটাইম (ডিফল্ট)।

- **গোনা হয় ডিভাইসটা শেষ যেদিন ব্যবহার হয়েছে সেদিন থেকে।** নিয়মিত ব্যবহার হলে কখনো লগআউট হয় না; সংখ্যা কমালে এর চেয়ে বেশি দিন অব্যবহৃত ডিভাইস পরের বার লগইন পেজে যায়।
- **কীভাবে:** global middleware `ApplyLoginDuration` প্রতিটা অনুরোধে `session.lifetime`-এ `BillingSetting::loginDurationInMinutes()` বসায়। Remember-cookie ব্যবহার করা হয় না, কারণ তাতে ডিভাইস সীমায় বা প্রোফাইল থেকে মোছা session নিজে থেকেই আবার লগইন হয়ে যেত।
- **লাইফটাইমের সীমা:** ব্রাউজার (Chrome) cookie রাখে সর্বোচ্চ ৪০০ দিন, প্রতিবার ব্যবহারে নতুন করে গোনা — তাই একটানা ৪০০ দিনের বেশি অব্যবহৃত ব্রাউজারে আবার লগইন লাগতে পারে। ব্রাউজারের ডেটা মুছলেও তাই।
- **`SESSION_LIFETIME` এখন শুধু guest-দের জন্য** (`session.guest_lifetime`): কখনো লগইন না করা ভিজিটরের `sessions` row এর পরে মুছে যায় (`GuestPruningSessionHandler`), শুধু `SESSION_DRIVER=database`-এ।
- Suspend ও role-এর যাচাই প্রতিটা অনুরোধে হয়, তাই লম্বা লগইনে সেগুলো পাশ কাটানো যায় না।

## User-এর চার অবস্থা

| `status` | প্যানেলে ঢোকা | প্রশ্ন যোগ | টীকা |
|---|---|---|---|
| `pending` | শুধু "অনুমোদনের অপেক্ষায়" পেজ | না | শুধু নতুন Staff-এর |
| `active` | হ্যাঁ | হ্যাঁ | |
| `suspended` | হ্যাঁ (ড্যাশবোর্ড, earning ইত্যাদি দেখা যায়) | না | reactivation-এর আবেদন করতে পারে |
| `permanent_suspend` | না | না | |

কোথায় বলবৎ হয়:

- **প্যানেলে ঢোকা:** `User::canAccessPanel()` — role-এর প্যানেল মিলতে হবে এবং `permanent_suspend` হওয়া চলবে না।
- **Pending Staff ও School:** `EnsureAccountIsApproved` middleware (Staff ও School প্যানেলে) প্রতিটা অনুরোধ প্যানেলের `pending-approval` পেজে ফেরত পাঠায় (logout ছাড়া) — `User::isAwaitingApproval()`। School সেই পেজে স্কুলের তথ্য দেয় — [12-school-link.md](12-school-link.md)।
- **প্রশ্ন যোগ:** `QuestionPolicy::create()` শুধু `active` user-কে অনুমতি দেয়।

## Admin-এর হাতে যা আছে

- **Staff:** `StaffResource`-এ approve, suspend, permanent suspend, reactivate।
- **Teacher:** `TeacherResource`।
- **Reactivation-এর আবেদন:** suspended Teacher/Staff নিজের প্যানেলের "Request reactivation" পেজ থেকে আবেদন করে (একবারে একটা); Admin `ReactivationRequestResource`-এ approve বা decline করে। Approve করলেই শুধু user-এর status বদলায়।

## DEV-LOGIN-BYPASS (শুধু লোকাল মেশিন)

`/dev-login/{user}` Google ছাড়া যেকোনো user হিসেবে লগইন করায়, যাতে পরিবর্তন ব্রাউজারে যাচাই করা যায়। এটা নিয়ম ১-এর একমাত্র ব্যতিক্রম, এবং অস্থায়ী।

- শুধু `APP_ENV=local` হলে রুটটা তৈরি হয়; অন্য পরিবেশে এর অস্তিত্ব নেই।
- **লাইভের `.env`-এ `APP_ENV` কখনো `local` রাখা যাবে না।**
- এর সব অংশ ও মুছে ফেলার ধাপ এক ফাইলে: [../DEV-LOGIN-BYPASS.md](../DEV-LOGIN-BYPASS.md)।

বিস্তারিত: [10-testing-and-deploy.md](10-testing-and-deploy.md)।

## কোড ও টেস্ট

| কী | কোথায় |
|---|---|
| Google login | `app/Http/Controllers/Auth/GoogleAuthController.php` |
| প্যানেলের সেটআপ | `app/Providers/Filament/*PanelProvider.php`, `App\Filament\Support\PanelDefaults` |
| প্যানেলে ঢোকার নিয়ম | `App\Models\User::canAccessPanel()` |
| Pending Staff/School | `App\Http\Middleware\EnsureAccountIsApproved`, পেজের base `Support\Pages\PendingApprovalPage` |
| Reactivation | `App\Models\ReactivationRequest` |

টেস্ট: `GoogleAuthTest`, `AdminPanelAccessTest`, `StaffPendingApprovalTest`, `SchoolAccountTest`, `TeacherSuspensionTest`, `ReactivationRequestTest`, `LogoutRedirectTest`, `DevLoginBypassTest`।
