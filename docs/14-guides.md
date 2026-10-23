# ১৪ — গাইড: "সাহায্য", "কীভাবে করবেন?" ও "শুরু করুন"

Student, Teacher, Staff আর School-কে সফটওয়্যার শেখানোর তিনটা ব্যবস্থা আছে, শুধু প্যানেলের ভেতরে। নিয়মের উৎস CLAUDE.md-এর নিয়ম ১৪।

## গাইড

- **লেখেন Admin:** Admin প্যানেল → Account → "গাইড" (`GuideResource`)। প্রতিটা গাইডে থাকে:
  - **লেখা:** বাংলা ও ইংরেজি শিরোনাম ও ধাপ (rich text, লেখার ভেতরে ছবি আপলোড, public disk `guides/`)। ইংরেজি ঐচ্ছিক।
  - **ভিডিও:** ঐচ্ছিক YouTube লিংক।
  - **কোথায় দেখাবে:** বিভাগ (`GuideCategory`), কাদের জন্য (Student / Teacher / Staff / School), কোন পেজের "কীভাবে করবেন?" বাটনে খুলবে (`GuidePage`, ঐচ্ছিক)।
  - **ক্রম ও অবস্থা:** ক্রম, চালু/বন্ধ।
- **ভাষা:** দর্শক বাংলা বাছলে (ডিফল্ট) বাংলা, English বাছলে ইংরেজি; ইংরেজি না থাকলে বাংলাটাই (`Guide::title()`, `bodyHtml()`)।
- **ভিডিও:** YouTube-এর watch / youtu.be / shorts / embed লিংক থেকে ভিডিও বসে (`youtube-nocookie.com`); অন্য লিংক হলে ভিডিও দেখায় না (`Guide::videoEmbedUrl()`)।
- **কে দেখে:** শুধু চালু গাইড, শুধু নিজের role-এর (`Guide::scopeFor()`)। Admin-এর প্যানেলে "সাহায্য" নেই।

## "সাহায্য" পেজ

Student/Teacher/Staff/School প্যানেলের মেনুতে "সাহায্য" (`Support\Pages\HelpCenter`, `/{panel}/help`; `PanelDefaults` চার প্যানেলে যোগ করে):
- নিজের গাইডগুলো বিভাগ অনুযায়ী, ওপরে খোঁজার ঘর (শিরোনাম ও লেখায়, দুই ভাষায় — `Guide::scopeMatching()`)।
- চাপলে গাইড খোলে (`?guide=`), "সব গাইড" দিয়ে ফেরা।
- `?page=` দিয়ে একটা পেজের গাইডগুলো আলাদা ("শুরু করুন"-এর "কীভাবে?" লিংক এভাবে খোলে)।

## "কীভাবে করবেন?" বাটন

- কোন পেজে বাটন থাকতে পারে, তা কোডে স্থির (`GuidePage`, প্রতিটার পেজের ক্লাস `pageClasses()`, প্যানেলের নিজের ক্লাস বা অভিন্ন base class):
  - সাবস্ক্রিপশন
  - আমার প্রশ্ন (তালিকা/লেখা/এডিট)
  - প্রশ্ন তৈরি করুন (পরীক্ষা বানানো)
  - পরীক্ষার তালিকা
  - প্র্যাকটিস (তিনটা পেজ)
  - বিগত সালের প্রশ্ন
  - রেফারেল ও কমিশন
  - Staff-এর আয়
  - School-এর সংযোগ
  - স্কুলের রেজাল্টে পাঠানো
- পেজের শিরোনামের পাশে, পেজের নিজের বাটনগুলোর আগে আসে — `PanelDefaults`-এর `PAGE_HEADER_ACTIONS_BEFORE` hook, পেজের scope থেকে `GuidePage::forScopes()`।
- বাটন দেখায় শুধু ওই পেজে দর্শকের role-এর অন্তত একটা গাইড থাকলে (`Guide::scopeOnPage()`)।
- চাপলে পাশ থেকে জানালা খোলে, পেজ না ছেড়েই গাইডগুলো পড়া যায়, নিচে "সব গাইড" (`<x-guides.page-help>`)।

## "শুরু করুন" তালিকা

ড্যাশবোর্ডের ওপরে, অফারের ব্যানারের সাথে (`filament.support.offer-banner`, `<x-guides.onboarding-checklist>`)। ধাপগুলো কোডে স্থির (`App\Services\OnboardingChecklist`)। প্রতিটা ধাপ user-এর আসল কাজ দেখে নিজে টিক পড়ে, কিছু রাখা হয় না:

| Role | ধাপ |
|---|---|
| Student | শ্রেণি বাছুন → প্রথম প্র্যাকটিস পরীক্ষা দিন (জমা দেওয়া) → plan বেছে নিন |
| Teacher | ট্রায়াল/plan → প্রথম প্রশ্ন → প্রথম পরীক্ষা → পরীক্ষার লিংক শেয়ার (published) |
| Staff | টাকা পাওয়ার তথ্য → প্রথম প্রশ্ন → প্রথম approved প্রশ্ন |
| School | সফটওয়্যারের সংযোগ → প্রথম প্রশ্ন → প্রথম পরীক্ষা → রেজাল্টে নম্বর পাঠানো |

- **"plan" ধাপ:** নিজের সক্রিয় subscription বা ট্রায়াল থাকলে টিক পড়ে; role-এর ফ্রি plan এতে গোনা হয় না।
- **"কীভাবে?" লিংক:** প্রতিটা ধাপের পাশে, "সাহায্য" পেজে ওই ধাপের পেজের গাইডগুলো খোলে। কোন গাইড খুলবে, Admin সেটা গাইডের "কোন পেজ" বেছে ঠিক করেন।
- **কখন চলে যায়:** সব ধাপ হয়ে গেলে, অথবা user "আর দেখাবেন না" চাপলে (`users.onboarding_dismissed_at`, `POST /onboarding/dismiss`)।
- **বন্ধ রাখা:** Admin চাইলে সবার জন্য বন্ধ রাখতে পারেন (`BillingSetting.onboarding_checklist_enabled`)।

## শুরুর গাইড

`Database\Seeders\GuideSeeder`-এ ২৬টা খসড়া, চার role-এর, বাংলা ও ইংরেজিতে। প্রতিটার পেজের স্ক্রিনশট আছে `database/seeders/guide-images/{slug}.png`; seed করার সময় ছবি public disk-এ কপি হয়ে ধাপগুলোর ওপরে বসে।

**লাইভে একবার চালাতে হবে:**

```bash
php artisan db:seed --class=GuideSeeder --force
php artisan storage:link   # আগে না করা থাকলে, ছবির জন্য
```

প্রতিটা গাইড তার `slug` দিয়ে একবারই তৈরি হয়। তাই আবার চালালে নতুন গাইড যোগ হয়, কিন্তু Admin-এর বদলানো গাইড বদলায় না, দুবারও হয় না।

স্ক্রিনশটগুলো চেক-ডেটাবেজের নমুনা ডেটা দিয়ে তোলা ("Check Teacher", "[O] For everyone" ইত্যাদি নাম দেখা যায়)। আসল ছবি চাইলে Admin গাইড এডিট করে বদলে দেবেন, অথবা ফাইল বদলে নতুন slug দিয়ে seed করতে হবে।

## কোড কোথায়

| কী | কোথায় |
|---|---|
| গাইড: কে দেখে, ভাষা, ভিডিও, খোঁজা | `Models\Guide` |
| বিভাগ; কোন পেজে বাটন | `Enums\GuideCategory`, `Enums\GuidePage` |
| Admin-এর পেজ | `Filament\Resources\Guides\GuideResource`, `Pages\ManageGuides` |
| "সাহায্য" পেজ | `Support\Pages\HelpCenter`, view `filament.support.pages.help-center` |
| গাইড দেখানো (দুই জায়গায় অভিন্ন) | `<x-guides.content>` |
| "কীভাবে করবেন?" বাটন | `<x-guides.page-help>`, `PanelDefaults` |
| "শুরু করুন" | `App\Services\OnboardingChecklist`, `<x-guides.onboarding-checklist>`, `OnboardingDismissalController` |
| শুরুর গাইড ও ছবি | `Database\Seeders\GuideSeeder`, `database/seeders/guide-images/` |

## টেস্ট

`GuidesTest`:
- **গাইড:** role ও চালু অনুযায়ী দেখানো, দুই ভাষা ও বাংলায় ফেরা, YouTube লিংক।
- **পেজ চেনা:** প্যানেলের ক্লাস থেকে পেজ চেনা।
- **"সাহায্য" পেজ:** তালিকা, খোঁজা, খোলা, অন্য role-এর গাইড না খোলা।
- **"কীভাবে করবেন?" বাটন:** গাইড থাকলেই দেখানো, শুধু নিজের role-এর।
- **"শুরু করুন":**
  - Student-এর ধাপ নিজে টিক পড়া ও সব শেষে চলে যাওয়া।
  - Teacher-এর ধাপ।
  - ড্যাশবোর্ডে দেখানো, "আর দেখাবেন না", Admin-এর বন্ধ।
- **Admin ও seeder:** Admin-এর গাইড লেখা; seeder একবারই, সব role, দুই ভাষা।
