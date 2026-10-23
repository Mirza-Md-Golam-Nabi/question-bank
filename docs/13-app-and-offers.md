# ১৩ — App (PWA), অফার ও Push নোটিফিকেশন

ফোনে "Install" করা যায় এমন **একটাই app**, নাম **Next Fusion**। এটা সবার জন্য: Student, Teacher, Staff, School আর Admin। খোলে `/app`-এ, যেখান থেকে লগইন করা মানুষ নিজের ড্যাশবোর্ডে যায়। Admin অফার বা খবর লেখেন; সেটা হোম পেজে আর যাদের জন্য তাদের ড্যাশবোর্ডে দেখায়, চাইলে বেলে আর ফোনের নোটিফিকেশনেও যায়। নিয়মের উৎস CLAUDE.md-এর নিয়ম ১৩।

## App

- **Manifest:** `GET /manifest.webmanifest` (`PwaController::manifest()`), সব তথ্য `config/pwa.php` থেকে: নাম, ছোট নাম, বর্ণনা ("প্রশ্নব্যাংক, পরীক্ষা ও অনুশীলন"), রঙ, icon। `start_url` = `/app`, `scope` = `/`, `display` = `standalone`।
- **Icon:** `config/pwa.php`-এর `icons`-এ তিনটা লিংক বসাতে হবে: ১৯২×১৯২, ৫১২×৫১২, আর ৫১২×৫১২ "maskable" (লোগোর চারপাশে ফাঁকা জায়গাসহ, যাতে ফোন গোল/চারকোনা কাটতে পারে)। এখন সেখানে dummy লেখা (`LOGO-LINK-...-HERE`) আছে। **Icon না বসানো পর্যন্ত Chrome "Install" প্রস্তাব দেখাবে না।**
- **কোথায় যুক্ত:** `<x-pwa-head>` (manifest-এর লিংক, রঙ, iPhone-এর icon, service worker চালু করা) সব প্যানেলের `<head>`-এ (`PanelDefaults`), হোম পেজে আর `/app`-এ।
- **`/app`** (`PwaController::start()`): লগইন করা থাকলে নিজের প্যানেলের ড্যাশবোর্ডে (`UserRole::panelId()`)। না থাকলে "কোন প্যানেলে লগইন করবেন" (চার কার্ড, `<x-panel-choices>`, হোম পেজের সাথে অভিন্ন) আর এখনকার অফার।
- **Service worker:** `public/sw.js`, শুধু push নোটিফিকেশন দেখায় আর চাপলে তার লিংক খোলে (খোলা জানালা থাকলে সেখানেই)। কিছু cache করে না; পরীক্ষা ও প্রশ্ন সবসময় অনলাইন।

## অফার

- **লেখা:** Admin প্যানেল → Billing → "অফার" (`OfferResource`)।
  - **লেখা ও লিংক:** শিরোনাম, লেখা, ঐচ্ছিক ছবি, ঐচ্ছিক বাটন (লেখা + লিংক; লিংক `/`-দিয়ে শুরু বা পুরো ঠিকানা)।
  - **কাদের জন্য:** Student / Teacher / School / Staff, এক বা একাধিক। Student বাছলে চাইলে নির্দিষ্ট শ্রেণি; ফাঁকা মানে সব শ্রেণি।
  - **কখন:** শুরু ও শেষের সময় (ফাঁকা = এখনই / বন্ধ না করা পর্যন্ত), আর চালু/বন্ধ।
- **চালু কি না:** চালু আর সময়ের ভেতরে (`Offer::scopeLive()`, `isLive()`)। কোনো job নেই, দেখানোর মুহূর্তে সময় মেলানো হয়।
- **হোম পেজ ও `/app`:** সব চালু অফার, কাদের জন্য তা ছোট ট্যাগে (`audienceLabels()`)।
- **ড্যাশবোর্ডের ব্যানার:** Student/Teacher/Staff/School-এর ড্যাশবোর্ডের ওপরে (`PanelDefaults`, view `filament.support.offer-banner`)।
  - দেখায় তার role-এর চালু অফার; Student হলে শুধু তার শ্রেণির (অথবা সব-শ্রেণির) অফার (`Offer::scopeInBannerOf()`)।
  - "×" চাপলে সেই মানুষের কাছে আর আসে না (`offer_dismissals`, `POST /offers/{offer}/dismiss`)।
  - Admin-এর ড্যাশবোর্ডে আসে না।
- **কার্ড:** তিন জায়গায় একই `<x-offer-card>`।

## বেলে ও ফোনে পাঠানো

- অফারের সারিতে "পাঠান" বাটন, শুধু অফার চালু থাকলে আর এখনো পাঠানো না হলে দেখায়।
  - জানালায় লেখা থাকে কতজনের জন্য আর তাদের কতজনের push চালু (`OfferNotifier::reach()`)।
  - Admin বাছেন: বেলে, push নোটিফিকেশনে, বা দুটোই।
- **একবারই:** `offers.notified_at` এক statement-এ দাবি করা হয়, তাই দুজন Admin একসাথে চাপলেও দুবার যায় না। পাঠানোর log থাকে।
- **কাদের কাছে:** `Offer::audience()` অনুযায়ী, অর্থাৎ সেই role-এর active user, Student হলে বাছাই করা শ্রেণির।
  - **বেল:** `BellNotifier` দিয়ে।
  - **Push:** শুধু যাদের কোনো ডিভাইসে push চালু (`OfferPushNotification`, `WebPushChannel`)।
- **কীভাবে চলে:** পাঠানো চলে Admin-এর পেজ উত্তর দেওয়ার পরে (`defer()`), ৫০০ জন করে। তাই অনেক user হলেও Admin অপেক্ষা করেন না, আর queue worker লাগে না।

## Push নোটিফিকেশন চালু করা

- Student/Teacher/Staff/School-এর ড্যাশবোর্ডে "অফার ও নতুন খবর ফোনে পেতে নোটিফিকেশন চালু করুন" (`<x-push-prompt>`)।
  - ব্রাউজার অনুমতি চায় শুধু "চালু করুন" চাপলে (iPhone এটাই চায়)।
  - "পরে" চাপলে এক সপ্তাহ দেখায় না।
  - অনুমতি দেওয়া থাকলে বা বন্ধ করা থাকলে, অথবা ব্রাউজার push না পারলে দেখায় না।
- অনুমতি পেলে ডিভাইসটা user-এর নামে রাখা হয় (`POST /push-subscriptions`, `push_subscriptions` টেবিল; `DELETE` দিয়ে সরানো)। চালু থাকলে প্রতিবার ড্যাশবোর্ড খোলার সময় নিঃশব্দে আবার নথিভুক্ত হয়।
- **iPhone:** push পায় শুধু হোম স্ক্রিনে app যোগ করার পরে (iOS 16.4+), Safari-র ট্যাবে না।
- **VAPID key:** `.env`-এ `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` (`php artisan webpush:vapid` একবারই চালাতে হবে; বদলালে আগের সব ডিভাইসের push বন্ধ হয়)। key না থাকলে "চালু করুন" দেখায় না।

## কোড কোথায়

| কী | কোথায় |
|---|---|
| App-এর তথ্য ও icon | `config/pwa.php` |
| Manifest, `/app` | `PwaController` (`pwa.manifest`, `pwa.start`) |
| `<head>`-এর অংশ | `<x-pwa-head>` (প্যানেলে `PanelDefaults`-এর render hook) |
| প্যানেল বাছাইয়ের কার্ড | `<x-panel-choices>` (হোম পেজ ও `/app`) |
| Service worker | `public/sw.js` |
| অফারের নিয়ম (চালু কি না, কার ব্যানারে, কাদের কাছে পাঠানো) | `Models\Offer` (`scopeLive()`, `scopeInBannerOf()`, `audience()`, `canBeSent()`), `OfferDismissal` |
| Admin-এর অফার পেজ | `Filament\Resources\Offers\OfferResource`, `Pages\ManageOffers` |
| পাঠানো | `App\Services\OfferNotifier`, `App\Notifications\OfferPushNotification` |
| Push চালু করা | `<x-push-prompt>`, `PushSubscriptionController` |
| ব্যানার ও কার্ড | `filament.support.offer-banner`, `<x-offer-card>`; বন্ধ করা `OfferDismissalController` |

## টেস্ট

`OffersAndAppTest`:
- **App:** manifest (Next Fusion, `/app`, icon), `/app`-এ নিজের ড্যাশবোর্ড বা প্যানেল বাছাই, হোম পেজে manifest।
- **অফার দেখানো:**
  - হোম পেজে শুধু চালু অফার (শেষ হওয়া/আসন্ন/বন্ধ না)।
  - কার ব্যানারে (role ও শ্রেণি), শ্রেণির id সংখ্যা হিসেবে রাখা।
  - ব্যানার "×" চাপলে চলে যায়।
- **Admin ও পাঠানো:**
  - Admin-এর অফার লেখা।
  - বেলে একবারই পাঠানো।
  - Push শুধু চালু ডিভাইসে।
  - বন্ধ অফার বা কিছু না বাছলে পাঠায় না।
  - তালিকা থেকে পাঠানো ও তারপর বাটন লুকানো।
- **Push subscription:** রাখা ও সরানো (লগইন ছাড়া না)।
