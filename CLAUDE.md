<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== filament/filament/core rules ===

## Filament

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Inspect required options before running, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

<code-snippet name="Reactive field update" lang="php">
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),

</code-snippet>

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

<code-snippet name="Section and Grid layout" lang="php">
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),

</code-snippet>

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

<code-snippet name="Repeater for HasMany" lang="php">
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

<code-snippet name="Table filters" lang="php">
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),

</code-snippet>

Actions are buttons that encapsulate optional modal forms and behavior:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),

</code-snippet>

### Testing

Testing setup (requires `pestphp/pest-plugin-livewire` in `composer.json`):

- Always call `$this->actingAs(User::factory()->create())` before testing panel functionality.
- For edit pages, pass `['record' => $user->id]`, use `->call('save')` (not `->call('create')`), and do not assert `->assertRedirect()` (edit pages do not redirect after save).

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Edit resource test" lang="php">
livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

<code-snippet name="Calling actions" lang="php">
use Filament\Actions\Testing\TestAction;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v4.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes

</laravel-boost-guidelines>

<question-bank-guidelines>

## Project Overview

এটি একটি **৪-প্যানেল Question Bank + Exam + Monetization প্ল্যাটফর্ম**:
- **Admin Panel** — প্রশ্ন approve/reject, Staff payout, Subscription plan ম্যানেজমেন্ট
- **Teacher Panel** — প্রশ্ন যোগ (pending), exam বানানো ও শেয়ার-লিংক জেনারেট (subscription-গেটেড)
- **Staff Panel** — শুধু প্রশ্ন যোগ করা (content-only), নিজের earning দেখা
- **Student Panel** — শেয়ার-লিংকে (Login/Guest) exam দেওয়া, নিজের subscription দিয়ে self-practice exam জেনারেট করা

বিস্তারিত ডিজাইনের জন্য দেখুন: `System-Design.md`

## ⭐ মূল বিজনেস রুলসমূহ (কখনো ভাঙা যাবে না)

### ১. Question Approval Visibility
> **Teacher/Staff-এর তৈরি প্রশ্ন Admin approve করার আগ পর্যন্ত অন্য কোনো Teacher/Staff দেখতে বা exam-এ ব্যবহার করতে পারবে না — শুধু owner ও Admin দেখবে।**

1. প্রতিটা প্রশ্নের `status`: `pending | approved | rejected`
2. Admin-এর নিজের আপলোড সরাসরি `approved`; Teacher/Staff-এরটা ডিফল্টভাবে `pending`
3. **প্রতিটা Filament Resource-এর `getEloquentQuery()`-তে filter হার্ডকোড থাকতে হবে**, শুধু UI hide না। Teacher Panel: `where('created_by', auth()->id())->orWhere(fn($q) => $q->where('status','approved')->where('is_latest', true))`। Staff Panel: শুধু `where('created_by', auth()->id())` (approved pool দেখার দরকারই নেই)।
4. Exam-এ প্রশ্ন attach করার সময় (relationship field ও model-level Observer উভয় জায়গায়) নিশ্চিত করতে হবে `status='approved' AND is_latest=true`।
5. `QuestionPolicy`-তেও owner+status ডাবল-চেক থাকবে।
6. এই ফিল্টার মিস হলে সেটা **critical security bug**।

### ২. Versioning (Approved প্রশ্ন এডিট)
- একই `questions` টেবিলে `parent_id`, `version`, `is_latest` কলাম দিয়ে versioning।
- Approved প্রশ্ন এডিট করলে: পুরনো row অক্ষত + `is_latest=false`, নতুন row `status=pending`, `version+=1`।
- লজিক `QuestionObserver`-এ কেন্দ্রীভূত রাখতে হবে, কোথাও ডুপ্লিকেট করা যাবে না।

### ৩. Staff Payment
> **Staff-এর প্রশ্ন `approved` হওয়ার মুহূর্তেই তার পারিশ্রমিক `staff_earnings` ledger-এ যোগ হবে — rejected প্রশ্নের জন্য কোনো টাকা যোগ হবে না।**
- Rate `question_rates` টেবিল থেকে (subject-ভিত্তিক, না থাকলে default) নেওয়া হবে এবং **approve করার মুহূর্তের rate `staff_earnings.amount`-এ snapshot হিসেবে সেভ থাকবে** — পরে rate বদলালেও পুরনো entry অপরিবর্তিত থাকবে।
- `staff_profiles.bank_account_number` ও `mobile_banking_number` **অবশ্যই `encrypted` cast দিয়ে** স্টোর করতে হবে — plain column-এ কখনো না।
- Payout একটা batch action (`staff_payouts`), individual earning row-কে সরাসরি "paid" না বানিয়ে payout batch-এর মাধ্যমে।

### ৪. Subscription / Freemium
> **Teacher exam তৈরি/publish করার আগে, ও Student self-practice exam জেনারেট করার আগে (Auto-Generate বা Manual Selection — দুই মোডেই) — সক্রিয় subscription বা মাসিক ফ্রি-লিমিটের মধ্যে আছে কিনা চেক করতে হবে।**
- লিমিট চেক করার সময় আলাদা counter টেবিল না রেখে সরাসরি query দিয়ে গণনা (`whereMonth`/`whereYear`) — সরল ও accurate। Auto ও Manual দুই মোডের exam-ই `exam_type='self_practice'` হওয়ায় একই কাউন্টে ধরা হবে (মোড আলাদা করে গণনা করার দরকার নেই)।
- **Guest-রা exam attempt দেওয়ার সময় কোনো subscription চেক লাগবে না** — এটা Teacher-এর subscription-এর আওতায় আগেই কভার হয়ে গেছে (exam publish করার সময়েই চেক হয়েছে)।
- Payment gateway callback-এ **idempotency** মাথায় রাখতে হবে — দুইবার callback এলে যেন ডাবল subscription active না হয়।

### ৫. Exam Sharing (Guest + Login)
> **শেয়ার-লিংকে Student সবসময় দুটো অপশন পাবে: Login করে ঢোকা অথবা Guest হিসেবে (শুধু নাম দিয়ে) ঢোকা — এটা টগল করার কোনো সেটিং নেই, সবসময় দুটোই থাকবে।**
- `exams.share_token` — random, যথেষ্ট লম্বা (কমপক্ষে ৩২ ক্যারেক্টার), অনুমানযোগ্য না।
- Guest route Filament panel-এর বাইরে, plain Laravel/Livewire route — কারণ unauthenticated।
- Guest submission route-এ **rate limiting বাধ্যতামূলক** (spam/multiple-attempt ঠেকাতে)।
- Guest attempt-এর ফলাফল শুধু submit করার সাথে সাথেই দেখানো হবে (পরে ফিরে দেখার অ্যাকাউন্ট নেই) — `guest_contact` থাকলে future-এ email/SMS পাঠানোর সুযোগ রাখা যায়।

### ৬. Self-Practice Exam — দুই মোডই বাধ্যতামূলক
> **Student self-practice exam বানাতে পারবে দুইভাবে: Auto-Generate (সিস্টেম subject/difficulty/সংখ্যা অনুযায়ী random প্রশ্ন বেছে দেবে) এবং Manual Selection (Student নিজে approved pool থেকে প্রশ্ন বেছে নেবে)। দুটোই থাকতে হবে, একটা বাদ দেওয়া যাবে না।**
- `exams.generation_mode` ফিল্ড (`manual`/`auto`) দিয়ে আলাদা করা হবে, দুটোই `exam_type='self_practice'`।
- Auto মোডের random selection query সবসময় `status='approved' AND is_latest=true` ফিল্টার সহ (approval rule এখানেও প্রযোজ্য)।
- Manual মোডের UI Teacher-এর exam builder-এর মতোই (searchable multi-select), শুধু Student Panel-এর নিজস্ব সংস্করণ।
- দুই মোডের exam-ই monthly free-limit-এর একই কাউন্টে ধরা হবে, আলাদা limit না।

## Roles & Permissions (সংক্ষেপে)

| Role | পারে | পারে না |
|---|---|---|
| Admin | সব প্রশ্ন দেখা/approve/reject, Staff payout, Subscription plan ম্যানেজ | Student হিসেবে exam দেওয়া |
| Teacher | নিজের প্রশ্ন CRUD, approved pool দেখা, exam তৈরি/শেয়ার (subscription-গেটেড) | Staff earning দেখা, অন্যের pending প্রশ্ন দেখা |
| Staff | নিজের প্রশ্ন CRUD (pending/rejected), নিজের earning দেখা | exam তৈরি করা, অন্যের প্রশ্ন দেখা, approved pool ব্রাউজ করা |
| Student (Login) | নিজের attempt/result, নিজের subscription দিয়ে self-practice exam | প্রশ্ন দেখা exam-এর বাইরে, অন্যের result দেখা |
| Student (Guest) | শুধু নির্দিষ্ট share-link-এর exam attempt দেওয়া | self-practice exam, লগইন-নির্ভর যেকোনো ফিচার |

## Tech Stack

- Backend: **Laravel 11**
- Panel/Admin framework: **Filament v5** (Livewire v4-ভিত্তিক), ৪টা Panel Provider (`/admin`, `/teacher`, `/staff`, `/student`)
- Database: **MySQL**
- Role/Permission: **Spatie `laravel-permission`** + **Filament Shield**
- Rich Text: **CKEditor 5** + math plugin (KaTeX) — Bangla plain text, math শুধু inline widget-এ (আগের আলোচনা দেখুন)
- Encryption: Laravel `encrypted` cast (bank info-র জন্য)
- Payment Gateway: **এখনো চূড়ান্ত হয়নি** — SSLCommerz/bKash/Nagad-এর মধ্যে যেটা ঠিক হবে এখানে আপডেট করুন
- Reports: `maatwebsite/laravel-excel`
- PDF: `spatie/laravel-pdf` বা DomPDF

## Folder Structure (প্রস্তাবিত)

```
app/
  Providers/Filament/
    AdminPanelProvider.php
    TeacherPanelProvider.php
    StaffPanelProvider.php
    StudentPanelProvider.php
  Filament/
    Admin/
      Resources/
        QuestionResource.php
        TeacherResource.php
        StaffResource.php
        QuestionRateResource.php
        StaffPayoutResource.php
        SubscriptionPlanResource.php
        SubscriptionResource.php
        PaymentResource.php
        ExamResource.php
    Teacher/
      Resources/
        QuestionResource.php
        ExamResource.php
      Pages/
        MySubscription.php
    Staff/
      Resources/
        QuestionResource.php
      Pages/
        MyEarnings.php
    Student/
      Pages/
        JoinExam.php          ← share-link entry (login/guest choice)
        TakeExamPage.php
        ExamResultPage.php
        GeneratePracticeExam.php   ← Auto-Generate মোড
        BuildPracticeExam.php     ← Manual Selection মোড
        MySubscription.php
  Models/
    User.php
    Question.php               ← parent_id/version/is_latest
    Subject.php
    Exam.php                   ← share_token, exam_type
    ExamAttempt.php             ← nullable student_id, is_guest, guest_name
    AttemptAnswer.php
    QuestionApprovalLog.php
    StaffProfile.php            ← encrypted bank fields
    QuestionRate.php
    StaffEarning.php
    StaffPayout.php
    SubscriptionPlan.php
    Subscription.php
    Payment.php
  Policies/
    QuestionPolicy.php
    ExamPolicy.php               ← subscription/limit check এখানেও রাখা যায়
    StaffEarningPolicy.php
  Observers/
    QuestionObserver.php         ← versioning + approve হলে earning trigger
  Http/Controllers/
    GuestExamController.php      ← unauthenticated guest attempt flow (Filament-এর বাইরে)
database/
  migrations/
  seeders/
System-Design.md
CLAUDE.md
```

## Coding Conventions

- **Filament v5 কনভেনশন মেনে চলুন** — v3-এর পুরনো Form/Table syntax v4/v5-এ কাজ নাও করতে পারে; কোড লেখার আগে official v5 docs চেক করুন।
- প্রতিটা নতুন Resource/Page লেখার আগে কোন role/panel-এর জন্য তা ঠিক করে সেই অনুযায়ী middleware/policy লাগান।
- আর্থিক লজিক (`staff_earnings`, `payments`) কখনো UI/controller-এ ছড়িয়ে না রেখে Observer/Service class-এ কেন্দ্রীভূত রাখুন — টাকার হিসাব দুই জায়গায় লেখা থাকলে একটা জায়গা মিস হয়ে বাগ হওয়ার ঝুঁকি বেশি।
- সংবেদনশীল ডেটা (bank info) কখনো log/dump/exception message-এ প্লেইন টেক্সটে না যায় সেটা নিশ্চিত করুন।
- Guest-related কোড Filament-এর auth boundary-র বাইরে রাখুন — Filament panel middleware-এর ভেতরে guest access কখনো ঢোকাবেন না।

## Commands

```bash
composer install
php artisan migrate
php artisan db:seed
php artisan serve
php artisan test
php artisan make:filament-panel <name>
php artisan make:filament-resource <Name> --panel=<panel>
php artisan make:observer QuestionObserver --model=Question
```

## Testing Priorities

1. **Question visibility:** Teacher A/Staff A-এর প্রশ্ন Teacher B/Staff B-এর কাছে অদৃশ্য থাকে যতক্ষণ না approve হয়।
2. **Versioning:** Approved প্রশ্ন এডিট করলে নতুন pending row তৈরি হয়, পুরনো exam-গুলো ভাঙে না।
3. **Staff earning:** প্রশ্ন approve হলেই ঠিক rate অনুযায়ী `staff_earnings` row তৈরি হয়; reject হলে হয় না; rate পরে বদলালেও পুরনো entry-র amount অপরিবর্তিত থাকে।
4. **Payout:** batch payout করলে সংশ্লিষ্ট সব earning `paid` হয়ে যায়, দ্বিতীয়বার payout করলে ডাবল পেমেন্ট না হয়।
5. **Subscription limit:** ফ্রি লিমিট (যেমন ৩টা/মাস) শেষ হলে নতুন exam/practice-exam তৈরি ব্লক হয়; পরের মাসে আবার রিসেট হয় (কোনো cron/reset job ছাড়াই, কারণ query মাস অনুযায়ী গণনা করে)।
6. **Self-practice দুই মোড:** Auto-Generate শুধু approved+latest প্রশ্ন থেকে random বাছে; Manual Selection-এ Student শুধু approved+latest প্রশ্ন দেখতে/বাছতে পারে (pending/rejected কখনো না); দুই মোডের exam-ই একসাথে monthly limit-এ গোনা হয়।
7. **Payment idempotency:** একই gateway callback দুইবার এলে ডাবল subscription/payment তৈরি না হয়।
8. **Exam sharing:** share_token দিয়ে Login ও Guest দুই পথেই attempt দেওয়া যায়; link expire/deactivate হলে ব্লক হয়; guest submission rate-limited।
9. **Non-approved question:** exam-এ attach করার চেষ্টা (UI ও সরাসরি Eloquent/Tinker দুইভাবেই) ব্লক হয়।

## যা করা যাবে না

- Question visibility-এর approval-check শুধু frontend-এ রাখা যাবে না।
- Bank account/mobile banking নম্বর কখনো plain (unencrypted) column-এ রাখা যাবে না।
- Rejected প্রশ্নের জন্য কোনো `staff_earnings` তৈরি করা যাবে না।
- Guest exam attempt route rate-limit ছাড়া open রাখা যাবে না।
- Subscription limit চেক bypass করে "quick fix" হিসেবে exam creation খোলা রাখা যাবে না।
- Payment/payout সংক্রান্ত কোনো অ্যাকশন log/audit trail ছাড়া করা যাবে না।
- Self-practice exam থেকে Auto-Generate বা Manual Selection — কোনো একটা মোড বাদ দিয়ে শুধু একটা রাখা যাবে না, দুটোই থাকতে হবে।

</question-bank-guidelines>
