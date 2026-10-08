# Question Bank

স্কুল-কলেজের প্রশ্ন জমা রাখা, সেই প্রশ্ন দিয়ে পরীক্ষা (exam) বানানো এবং শিক্ষার্থীদের অনলাইনে পরীক্ষা নেওয়ার একটি প্ল্যাটফর্ম।

বিস্তারিত ডিজাইন জানতে দেখুন [System-Design.md](System-Design.md)।

## সূচিপত্র

- [এই প্রোজেক্টে কী আছে](#এই-প্রোজেক্টে-কী-আছে)
- [যা যা লাগবে](#যা-যা-লাগবে)
- [লোকাল মেশিনে সেটআপ](#লোকাল-মেশিনে-সেটআপ)
- [Google Login সেটআপ](#google-login-সেটআপ)
- [লগইন তথ্য](#লগইন-তথ্য)
- [লাইভ সার্ভার বা cPanel-এ সেটআপ](#লাইভ-সার্ভার-বা-cpanel-এ-সেটআপ)
- [টেস্ট চালানো](#টেস্ট-চালানো)
- [সাধারণ সমস্যা ও সমাধান](#সাধারণ-সমস্যা-ও-সমাধান)

## এই প্রোজেক্টে কী আছে

প্রোজেক্টে চারটি আলাদা প্যানেল আছে। প্রতিটি প্যানেল আলাদা ধরনের ব্যবহারকারীর জন্য।

| প্যানেল | ঠিকানা | কে ব্যবহার করে | মূল কাজ |
| ------- | ------ | -------------- | ------- |
| Admin   | `/admin`   | Super Admin, Admin | প্রশ্ন ও বোর্ড-পেপার approve/reject, Staff একাউন্ট approve, Staff-এর পেমেন্ট, Subscription plan |
| Teacher | `/teacher` | শিক্ষক | প্রশ্ন যোগ করা, exam বানানো, শেয়ার-লিংক তৈরি |
| Staff   | `/staff`   | প্রশ্ন লেখক | শুধু প্রশ্ন যোগ করা এবং নিজের আয় দেখা |
| Student | `/student` | শিক্ষার্থী | শেয়ার-লিংকে exam দেওয়া, নিজে practice exam বানানো |

কয়েকটি গুরুত্বপূর্ণ নিয়ম:

- **প্রশ্ন দুই ধরনের:** MCQ এবং CQ (সৃজনশীল)।
- **Approve না হলে প্রশ্ন গোপন থাকে।** Teacher বা Staff-এর যোগ করা প্রশ্ন Admin approve না করা পর্যন্ত অন্য কেউ দেখতে বা exam-এ ব্যবহার করতে পারে না।
- **Staff টাকা পায় approve হলে।** প্রশ্ন approve হওয়ার সাথে সাথে Staff-এর আয়ের খাতায় টাকা যোগ হয়। Reject হলে কিছু যোগ হয় না।
- **Guest হিসেবেও exam দেওয়া যায়।** শেয়ার-লিংকে গিয়ে শিক্ষার্থী লগইন করে অথবা শুধু নাম দিয়ে exam দিতে পারে।
- **ফ্রি লিমিট আছে।** Teacher-এর exam বানানো এবং Student-এর practice exam মাসিক ফ্রি-লিমিট বা subscription-এর মধ্যে সীমাবদ্ধ।

## যা যা লাগবে

- PHP 8.3 বা তার বেশি
- Composer
- MySQL
- Node.js ও npm
- একটি Google OAuth Client (Teacher, Staff ও Student-এর লগইনের জন্য)

প্রোজেক্টটি Laravel 13, Filament 5, Tailwind CSS 4 এবং Pest দিয়ে তৈরি।

## লোকাল মেশিনে সেটআপ

**১. কোড নামান**

```sh
git clone https://github.com/Mirza-Md-Golam-Nabi/question-bank.git
cd question-bank
```

**২. PHP প্যাকেজ ইনস্টল করুন**

```sh
composer install
```

**৩. `.env` ফাইল বানান**

```sh
cp .env.example .env
php artisan key:generate
```

**৪. ডেটাবেস বানান**

MySQL-এ `question_bank` নামে একটি ডেটাবেস তৈরি করুন। এরপর `.env` ফাইলে নিজের তথ্য বসান:

```env
DB_DATABASE=question_bank
DB_USERNAME=root
DB_PASSWORD=
```

**৫. টেবিল ও প্রাথমিক ডেটা তৈরি করুন**

```sh
php artisan migrate --seed
```

এই কমান্ড টেবিল বানায় এবং সাথে role, একজন Super Admin, শ্রেণি ও বিষয়ের তালিকা যোগ করে।

**৬. ফ্রন্টএন্ড ফাইল বানান**

```sh
npm install
npm run build
```

**৭. প্রোজেক্ট চালু করুন**

```sh
php artisan serve
```

এখন ব্রাউজারে `http://localhost:8000` খুলুন।

> কোড লেখার সময় `php artisan serve`-এর বদলে `composer run dev` চালাতে পারেন। এতে সার্ভার, queue এবং Vite একসাথে চালু হয়, আর ফ্রন্টএন্ডের পরিবর্তন সাথে সাথে দেখা যায়।

## Google Login সেটআপ

Teacher, Staff ও Student শুধু Google একাউন্ট দিয়ে লগইন করে। তাই এই ধাপ বাদ দিলে ওই তিন প্যানেলে ঢোকা যাবে না।

**১.** [Google Cloud Console](https://console.cloud.google.com/)-এ যান → **APIs & Services** → **Credentials** → **Create Credentials** → **OAuth client ID** (ধরন: Web application)।

**২.** **Authorized redirect URIs**-এ নিচের ঠিকানা যোগ করুন:

```
http://localhost:8000/auth/google/callback
```

**৩.** Google থেকে পাওয়া Client ID ও Secret `.env` ফাইলে বসান:

```env
APP_URL=http://localhost:8000

GOOGLE_CLIENT_ID=আপনার-client-id
GOOGLE_CLIENT_SECRET=আপনার-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

## লগইন তথ্য

### Admin প্যানেল

Seeder চালালে একজন Super Admin তৈরি হয়। তিনি `/admin` ঠিকানায় email ও password দিয়ে লগইন করেন।

| Email | Password |
| ----- | -------- |
| `superadmin@example.com` | `password` |

নিজের পছন্দের তথ্য দিতে চাইলে seeder চালানোর **আগে** `.env` ফাইলে এগুলো যোগ করুন:

```env
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=you@example.com
SUPER_ADMIN_PASSWORD=একটি-শক্ত-password
```

> ⚠️ ডিফল্ট password শুধু লোকাল বা টেস্টের জন্য। লাইভ সার্ভারে অবশ্যই নিজের শক্ত password ব্যবহার করুন।

Admin প্যানেলে নিজে নিজে রেজিস্ট্রেশন করার সুযোগ নেই। নতুন Admin শুধু Super Admin তৈরি করতে পারেন।

### Teacher, Staff ও Student প্যানেল

এই তিন প্যানেলে কোনো password নেই। লগইন পেজে **Continue with Google** বাটনে ক্লিক করলেই হবে।

- প্রথমবার লগইন করলে একাউন্ট নিজে থেকেই তৈরি হয়।
- **Teacher ও Student** একাউন্ট সাথে সাথে চালু হয়ে যায়।
- **Staff** একাউন্ট প্রথমে "অনুমোদনের অপেক্ষায়" থাকে। Admin approve করার পর Staff কাজ শুরু করতে পারে।

## লাইভ সার্ভার বা cPanel-এ সেটআপ

### প্রথমবার সেটআপ

**১. কোড নামান**

যে ফোল্ডারে সাইট চলবে সেখানে গিয়ে চালান (শেষের `.` খেয়াল করুন, এতে কোড বর্তমান ফোল্ডারেই নামে):

```sh
git clone https://github.com/Mirza-Md-Golam-Nabi/question-bank.git .
```

ফোল্ডার খালি না থাকলে এই কমান্ড কাজ করবে না। তখন আগে ফোল্ডার খালি করতে হবে:

```sh
find . -mindepth 1 -delete
```

> ⚠️ এই কমান্ড বর্তমান ফোল্ডারের **সব ফাইল স্থায়ীভাবে মুছে ফেলে**। চালানোর আগে `pwd` দিয়ে নিশ্চিত হোন যে আপনি সঠিক ফোল্ডারে আছেন।

**২. `.htaccess` ফাইল যোগ করুন**

প্রোজেক্টের মূল ফোল্ডারে `.htaccess` নামে একটি ফাইল বানিয়ে এই কোড লিখুন। এতে সব অনুরোধ `public` ফোল্ডারে চলে যায়:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**৩. Composer আছে কিনা দেখুন**

```sh
composer -v
```

"Composer Not Found" দেখালে এই গাইড অনুসরণ করুন: [Composer install in cPanel](https://github.com/Mirza-Md-Golam-Nabi/tips/blob/master/laravel/composer/README.md#composer-install-in-cpanel-%EF%B8%8F)

**৪. `.env` ফাইল ঠিক করুন**

```sh
cp .env.example .env
php artisan key:generate
```

এরপর `.env` ফাইলে লাইভ সার্ভারের তথ্য বসান:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_DATABASE=আপনার-ডেটাবেস
DB_USERNAME=আপনার-ইউজার
DB_PASSWORD=আপনার-password

SUPER_ADMIN_EMAIL=you@example.com
SUPER_ADMIN_PASSWORD=একটি-শক্ত-password

GOOGLE_CLIENT_ID=আপনার-client-id
GOOGLE_CLIENT_SECRET=আপনার-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

**৫. Google Console-এ লাইভ ঠিকানা যোগ করুন**

**Authorized redirect URIs**-এ লাইভ সাইটের ঠিকানাও যোগ করতে হবে:

```
https://yourdomain.com/auth/google/callback
```

**৬. বাকি কমান্ড চালান**

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --seed --force
php artisan optimize
php artisan filament:optimize
```

**৭. ফ্রন্টএন্ড ফাইল**

সার্ভারে Node.js থাকলে `npm install` ও `npm run build` চালান। না থাকলে লোকাল মেশিনে `npm run build` চালিয়ে `public/build` ফোল্ডারটি সার্ভারের একই জায়গায় আপলোড করুন।

### পরে কোড আপডেট করা

প্রতিবার নতুন কোড তোলার জন্য [deploy.sh](deploy.sh) স্ক্রিপ্ট আছে:

```sh
bash deploy.sh
```

স্ক্রিপ্টটি নিজে থেকে এই কাজগুলো করে:

1. `dev` ব্রাঞ্চ থেকে নতুন কোড নামায়
2. Composer প্যাকেজ ইনস্টল করে
3. সাইট maintenance mode-এ নেয়
4. Migration চালায়
5. পুরনো cache মুছে নতুন cache বানায়
6. Queue worker রিস্টার্ট করে
7. সাইট আবার চালু করে

> স্ক্রিপ্টটি cPanel-এর PHP 8.3 (`/opt/cpanel/ea-php83/root/usr/bin/php`) এবং প্রোজেক্ট ফোল্ডারে রাখা `composer.phar` ব্যবহার করে। আপনার সার্ভারে পথ আলাদা হলে স্ক্রিপ্টের শুরুতে `PHP` ভ্যারিয়েবল বদলে নিন।
>
> স্ক্রিপ্ট ফ্রন্টএন্ড build করে না। CSS বা JS বদলালে `public/build` ফোল্ডার আলাদাভাবে আপডেট করতে হবে।

## টেস্ট চালানো

সব টেস্ট চালাতে:

```sh
php artisan test --compact
```

নির্দিষ্ট একটি টেস্ট চালাতে:

```sh
php artisan test --compact --filter=টেস্টের-নাম
```

কোডের ফরম্যাট ঠিক করতে:

```sh
vendor/bin/pint --dirty
```

## সাধারণ সমস্যা ও সমাধান

### Google লগইনে `Error 400: redirect_uri_mismatch`

আপনার সাইট Google-কে যে ঠিকানা পাঠাচ্ছে, সেটি Google Console-এর **Authorized redirect URIs** তালিকায় নেই।

- `.env` ফাইলে `APP_URL` সঠিক আছে কিনা দেখুন।
- একই ঠিকানা (শেষে `/auth/google/callback` সহ) Google Console-এ যোগ করুন।
- ঠিকানা হুবহু মিলতে হবে। `http` ও `https`, `www` থাকা ও না থাকা, শেষে বাড়তি `/` — এগুলো আলাদা ধরা হয়।
- `.env` বদলানোর পর `php artisan config:clear` চালান।

### `.env` বদলেছি কিন্তু কাজ হচ্ছে না

লাইভ সার্ভারে config cache করা থাকে। তাই প্রতিবার `.env` বদলানোর পর চালান:

```sh
php artisan optimize:clear
php artisan optimize
```

### `Unable to locate file in Vite manifest`

ফ্রন্টএন্ড ফাইল build করা হয়নি। `npm run build` চালান।

### ফ্রন্টএন্ডের পরিবর্তন দেখা যাচ্ছে না

`npm run build` আবার চালান, অথবা কাজ করার সময় `composer run dev` চালু রাখুন।
