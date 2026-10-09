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
- **Admin Panel** — প্রশ্ন/বোর্ড-পেপার approve/reject, নতুন Staff একাউন্ট approve, Staff payout, Subscription plan ম্যানেজমেন্ট
- **Teacher Panel** — প্রশ্ন যোগ (pending), exam বানানো ও শেয়ার-লিংক জেনারেট (subscription-গেটেড)
- **Staff Panel** — শুধু প্রশ্ন যোগ করা (content-only), নিজের earning দেখা
- **Student Panel** — শেয়ার-লিংকে (Login/Guest) exam দেওয়া, নিজের subscription দিয়ে self-practice exam জেনারেট করা

বিস্তারিত ডিজাইনের জন্য দেখুন: `System-Design.md`

## ⭐ মূল বিজনেস রুলসমূহ (কখনো ভাঙা যাবে না)

### ১. Authentication — Google OAuth (Teacher/Staff/Student) + Filament Default (Admin/Super Admin)
> **Teacher/Staff/Student পুরোপুরি Google OAuth-only — কোনো password ফর্ম, রেজিস্ট্রেশন পেজ, বা forget-password ফ্লো নেই। শুধু Admin/Super Admin Filament-এর ডিফল্ট email/password Login ব্যবহার করবে, আর সেখানে Registration পাবলিকলি বন্ধ।**

1. Teacher/Staff/Student প্যানেলের Login page-এ শুধু "Continue with Google" বাটন — `/auth/google/redirect/{role}` → Google callback-এ email/`google_id` match করে existing user login, না পেলে নতুন `users` row তৈরি (session-এ রাখা intended role অনুযায়ী)।
2. নতুন **Teacher/Student** একাউন্ট সাথে সাথে `status=active`।
3. নতুন **Staff** একাউন্ট `status=pending`-এ তৈরি হয় — Admin approve না করা পর্যন্ত Staff Panel middleware/policy তাকে ব্লক করবে ("অনুমোদনের অপেক্ষায়" পেজ দেখাবে), `QuestionResource`-এ কিছু করতে দেবে না।
4. **Admin Panel-এ পাবলিক Registration বন্ধ** (`->registration(false)`) — যে কেউ সাইনআপ করে Admin হতে পারবে না। প্রথম Super Admin `SuperAdminSeeder` দিয়ে তৈরি, এরপরের Admin Super Admin নিজে `AdminResource` থেকে বানাবে।
5. `users.password_hash` শুধু `admin`/`super_admin`-এর জন্য filled থাকবে; বাকি রোলে সবসময় `null`। `users.google_id` উল্টোটা — শুধু Teacher/Staff/Student-এ filled।
6. **`users.status` চারটা মান নিতে পারে:** `pending` (Staff-এর জন্য, Admin approve-এর অপেক্ষায়), `active`, `suspended`, `permanent_suspend`। Teacher/Staff দুই ক্ষেত্রেই Admin `suspended` করলে প্যানেলে ঢুকতে পারবে (Dashboard/Earning-এর মতো নিজের পেজ দেখতে পারবে), কিন্তু নতুন প্রশ্ন **add করতে পারবে না** (`QuestionPolicy::create()`-এ হার্ডকোড থাকবে)। `permanent_suspend` করলে পুরো প্যানেলে ঢোকাই ব্লক (`User::canAccessPanel()`-এ হার্ডকোড)।

### ২. Question Approval Visibility
> **Teacher/Staff-এর তৈরি প্রশ্ন Admin approve করার আগ পর্যন্ত অন্য কোনো Teacher/Staff দেখতে বা exam-এ ব্যবহার করতে পারবে না — শুধু owner ও Admin দেখবে।**

1. প্রতিটা প্রশ্নের `status`: `pending | approved | rejected`
2. Admin-এর নিজের আপলোড সরাসরি `approved`; Teacher/Staff-এরটা ডিফল্টভাবে `pending`
3. **প্রতিটা Filament Resource-এর `getEloquentQuery()`-তে filter হার্ডকোড থাকতে হবে**, শুধু UI hide না। Teacher Panel-এর "আমার প্রশ্ন" ও Staff Panel দুটোই শুধু নিজের প্রশ্ন দেখায় (`Question::ownedBy()`)। Teacher অন্যদের approved প্রশ্ন দেখে ও বাছে শুধু "প্রশ্ন বাছাই" পেজে, যা সরাসরি approved pool (`Question::approvedPool()` — `status='approved' AND is_latest=true`) পড়ে।
4. Exam-এ প্রশ্ন attach করার সময় (relationship field ও model-level Observer উভয় জায়গায়) নিশ্চিত করতে হবে `status='approved' AND is_latest=true`। **একমাত্র ব্যতিক্রম:** Teacher নিজের `pending` (ও `is_latest`) প্রশ্ন **নিজের** exam-এ ব্যবহার করতে পারে, যাতে আজ লেখা প্রশ্নে আজই পরীক্ষা নেওয়া যায় — অন্য কেউ সেটা approve না হওয়া পর্যন্ত দেখে না, আর Student-এর self-practice সবসময় শুধু approved pool। `rejected` প্রশ্ন নতুন exam-এ যায় না, কিন্তু যে exam-এ আগে থেকে আছে সেখানে থেকে যায়। এই ব্যতিক্রম শুধু `Question::scopeUsableInExamBy()`-এ লেখা।
5. `QuestionPolicy`-তেও owner+status ডাবল-চেক থাকবে। Teacher/Staff নিজের প্রশ্ন ডিলিট করতে পারে শুধু যতক্ষণ সেটা `approved` না এবং কোনো exam-এ নেই (`isOnAnExam()`); আর কোনো student ওই প্রশ্নসহ পরীক্ষা শুরু করার পর approved-নয় এমন প্রশ্ন কেউ (Admin-ও না) এডিট করতে পারে না (`Question::isFrozenByAttempts()`) — approved প্রশ্নের এডিট নতুন version বানায় বলে সেটা চলে।
6. এই ফিল্টার মিস হলে সেটা **critical security bug**।

### ৩. Question Types ও Content Hierarchy
> **শুধু দুই ধরনের প্রশ্ন: `mcq` ও `cq` (সৃজনশীল)। `true_false`/`short`/`descriptive` নেই।**

1. Chain সবসময়: `academic_classes → class_subjects (pivot) → chapters → questions`।
2. `subjects` একটা মাস্টার লিস্ট (class-নির্ভর না) — কোন class-এ কোন subject আছে সেটা `class_subjects` পিভট ট্র্যাক করে। `chapters.class_subject_id` সরাসরি `class_subjects.id`-কে পয়েন্ট করে, `subjects.id`-কে না (একই subject বিভিন্ন class-এ আলাদা chapter সেট রাখে)।
3. CQ প্রশ্নে `questions.question_text`/`question_image` = উদ্দীপক, আর ৪টা সাব-প্রশ্ন `question_cq_parts`-এ (`knowledge`/`comprehension`/`application`/`higher_application`)।
4. CQ-এর `questions.marks` = তার ৪টা `question_cq_parts.marks`-এর যোগফল — **`QuestionObserver`-এ auto-sync**, ম্যানুয়ালি সেট করা যাবে না।
5. `academic_classes`/`subjects`/`class_subjects`/`chapters` — সবগুলো Admin-only reference data (`created_by` নেই, Teacher/Staff শুধু select করবে, নিজে তৈরি করতে পারবে না)।

### ৪. Versioning (Approved প্রশ্ন এডিট)
- একই `questions` টেবিলে `parent_id`, `version`, `is_latest` কলাম দিয়ে versioning।
- Approved প্রশ্ন এডিট করলে: পুরনো row অক্ষত + `is_latest=false`, নতুন row `status=pending`, `version+=1`।
- CQ হলে `question_cq_parts`-এর ৪টা row-ও নতুন `question_id`-এর সাথে কপি হবে, পুরনো version-এর parts অক্ষত থাকবে।
- লজিক `QuestionObserver`-এ কেন্দ্রীভূত রাখতে হবে, কোথাও ডুপ্লিকেট করা যাবে না।

### ৫. Board Question Papers — সম্পূর্ণ আলাদা সাবসিস্টেম
> **বোর্ড প্রশ্ন (`boards`/`board_question_papers`/`board_mcq_questions`/`board_cq_questions`/`board_cq_question_parts`) কখনো সাধারণ `questions` পুলের সাথে মিশবে না — আলাদা টেবিল, আলাদা approval workflow।**

1. `board_question_papers` = একটা নির্দিষ্ট board + `class_subject_id` + `year`-এর পুরো প্রশ্নপত্র, পুরোটা একসাথে `pending`/`approved`/`rejected` হয় (individual question-level approval না)।
2. `unique(board_id, class_subject_id, year)` — একই বোর্ড+সাল+সাবজেক্টের পেপার দুইবার তৈরি করা যাবে না।
3. `board_cq_questions.marks` একইভাবে তার `board_cq_question_parts`-এর যোগফল থেকে auto-sync।
4. Self-practice/Teacher exam-এর question picker-এ board question papers কখনো approved pool-এর সাথে mix করা যাবে না — এটা আলাদা browsing/UI flow।

### ৬. Staff Payment
> **Staff-এর প্রশ্ন `approved` হওয়ার মুহূর্তেই তার পারিশ্রমিক `staff_earnings` ledger-এ যোগ হবে — rejected প্রশ্নের জন্য কোনো টাকা যোগ হবে না।**
- Rate `question_rates` টেবিল থেকে (subject-ভিত্তিক, না থাকলে default) নেওয়া হবে এবং **approve করার মুহূর্তের rate `staff_earnings.amount`-এ snapshot হিসেবে সেভ থাকবে** — পরে rate বদলালেও পুরনো entry অপরিবর্তিত থাকবে।
- `staff_profiles.bank_account_number` ও `mobile_banking_number` **অবশ্যই `encrypted` cast দিয়ে** স্টোর করতে হবে — plain column-এ কখনো না।
- Payout একটা batch action (`staff_payouts`), individual earning row-কে সরাসরি "paid" না বানিয়ে payout batch-এর মাধ্যমে।

### ৭. Subscription / Freemium
> **Teacher exam তৈরি/publish করার আগে, ও Student self-practice exam জেনারেট করার আগে (Auto-Generate বা Manual Selection — দুই মোডেই) — সক্রিয় subscription বা মাসিক ফ্রি-লিমিটের মধ্যে আছে কিনা চেক করতে হবে।**
- লিমিট চেক করার সময় আলাদা counter টেবিল না রেখে সরাসরি query দিয়ে গণনা (`whereMonth`/`whereYear`) — সরল ও accurate। Auto ও Manual দুই মোডের exam-ই `exam_type='self_practice'` হওয়ায় একই কাউন্টে ধরা হবে (মোড আলাদা করে গণনা করার দরকার নেই)।
- **Guest-রা exam attempt দেওয়ার সময় কোনো subscription চেক লাগবে না** — এটা Teacher-এর subscription-এর আওতায় আগেই কভার হয়ে গেছে (exam publish করার সময়েই চেক হয়েছে)।
- Payment gateway callback-এ **idempotency** মাথায় রাখতে হবে — দুইবার callback এলে যেন ডাবল subscription active না হয়।

### ৮. Exam Sharing (Guest + Login)
> **শেয়ার-লিংকে Student সবসময় দুটো অপশন পাবে: Login করে ঢোকা অথবা Guest হিসেবে (শুধু নাম দিয়ে) ঢোকা — এটা টগল করার কোনো সেটিং নেই, সবসময় দুটোই থাকবে।**
- `exams.share_token` — random, যথেষ্ট লম্বা (কমপক্ষে ৩২ ক্যারেক্টার), অনুমানযোগ্য না।
- Guest route Filament panel-এর বাইরে, plain Laravel/Livewire route — কারণ unauthenticated (Google OAuth guard-এর সাথে মিশবে না)।
- Guest submission route-এ **rate limiting বাধ্যতামূলক** (spam/multiple-attempt ঠেকাতে)।
- **Teacher-এর exam-এ জমা দেওয়ার পর student শুধু স্কোর দেখে** — প্রশ্ন, অপশন বা সঠিক উত্তর কিছুই না। নইলে খালি খাতা জমা দিয়ে উত্তরপত্র জেনে নিয়ে আবার পরীক্ষা দেওয়া যায় (Guest-এর শুধু আরেকটা নাম লাগে)। Teacher "উত্তর প্রকাশ করুন" চাপলে (`exams.answers_released_at`), অথবা Teacher-এর ঠিক করে দেওয়া সময় এলে (`exams.answers_release_at`) তবেই অপশনসহ সঠিক উত্তর ও নিজের উত্তর দেখা যায়। নির্ধারিত সময়ের জন্য কোনো cron/scheduled job নেই — ফলাফল দেখানোর মুহূর্তে সময়টা মিলিয়ে দেখা হয় (subscription লিমিটের মতোই)। এই সিদ্ধান্ত শুধু `Exam::showsAnswersToStudents()`-এ থাকবে; Self-practice exam-এ উত্তর সাথে সাথেই দেখায় (নিজের exam, ফাঁস হওয়ার কেউ নেই)।
- **Guest-এর পরিচয় = নাম + ফোন/ইমেইল** (`guest_contact` তাই বাধ্যতামূলক, normalize করে স্টোর হয়)। কোনো একাউন্ট না থাকায় Guest পরে শেয়ার-লিংকে ফিরে এই দুটো দিয়েই নিজের ফলাফল খোঁজে (`ExamAttempt::findGuestResult()`) — লিংক বন্ধ হয়ে গেলেও। একই পরিচয়ে একাধিকবার পরীক্ষা দিলে **প্রথম attempt-টাই** দেখানো হয়। এই lookup route-ও rate-limited।

### ৯. Self-Practice Exam — দুই মোডই বাধ্যতামূলক
> **Student self-practice exam বানাতে পারবে দুইভাবে: Auto-Generate (সিস্টেম subject/difficulty/সংখ্যা অনুযায়ী random প্রশ্ন বেছে দেবে) এবং Manual Selection (Student নিজে approved pool থেকে প্রশ্ন বেছে নেবে)। দুটোই থাকতে হবে, একটা বাদ দেওয়া যাবে না।**
- `exams.generation_mode` ফিল্ড (`manual`/`auto`) দিয়ে আলাদা করা হবে, দুটোই `exam_type='self_practice'`।
- Auto মোডের random selection query সবসময় `status='approved' AND is_latest=true` ফিল্টার সহ (approval rule এখানেও প্রযোজ্য)।
- Manual মোডের UI Teacher-এর exam builder-এর মতোই (searchable multi-select), শুধু Student Panel-এর নিজস্ব সংস্করণ।
- দুই মোডের exam-ই monthly free-limit-এর একই কাউন্টে ধরা হবে, আলাদা limit না।

### ১০. Teacher প্রশ্ন বাছাই (Select Questions) → Exam
> **Teacher exam বানায় "প্রশ্ন বাছাই" পেজ থেকে: Class → Subject → Chapter → Topic ফিল্টার করে approved প্রশ্ন টিক দিয়ে বাছে, তারপর ফাইনাল ভিউ থেকে exam হিসেবে সেভ করে। হাজার হাজার Teacher একসাথে এই কাজ করবে — তাই টিক দেওয়ার সময় সার্ভারে কোনো অনুরোধ যাবে না।**

1. **পরীক্ষার ধরন আগে ঠিক হয়** (`exams.delivery_mode`): `online` (শুধু MCQ, শেয়ার-লিংক), `offline` (MCQ/CQ, প্রিন্ট/PDF), `both` (প্রিন্টে সব প্রশ্ন, অনলাইনে শুধু MCQ)। **CQ কখনো অনলাইনে নেওয়া হয় না** — সিস্টেমে CQ-এর উত্তর নেই, তাই উত্তরও দেখানো হয় না।
2. **এক exam = এক Class + Subject** (`exams.class_subject_id`)। একই subject-এর যত খুশি chapter থেকে প্রশ্ন মেশানো যায়; Class বা Subject বদলাতে গেলে সতর্কবার্তা দিয়ে অনুমতি নিয়ে আগের সব বাছাই মুছতে হবে।
3. **Topic ফিল্টার শুধু MCQ-তে খাটে।** CQ topic অনুযায়ী ভাগ হয় না — ধরন CQ হলে ওই chapter-এর সব CQ পেজিনেশনসহ দেখাতে হবে।
4. **কতগুলো প্রশ্ন লাগবে তা Teacher নিজে ঠিক করে** (MCQ ও CQ-র আলাদা লক্ষ্য সংখ্যা); লক্ষ্য পূরণ হলে আর বাছা যায় না। সিস্টেমে কোনো নির্দিষ্ট ব্যবসায়িক সীমা নেই (কিছু পরীক্ষায় ১২০টা প্রশ্ন লাগে) — শুধু `TeacherExamBuilder::MAX_QUESTIONS` নামে একটা কারিগরি সুরক্ষা-সীমা আছে।
5. **বাছাই শুধু ব্রাউজারের `localStorage`-এ থাকে, আর সেখানে শুধু প্রশ্নের ID** (লেখা না)। টিক, গণনা, chapter-ভিত্তিক সারাংশ — সব client-side (Alpine); সার্ভারে যায় শুধু ফিল্টার/পেজ বদলালে আর শেষে সেভ করার সময়। প্রশ্নের তালিকা সবসময় paginated — কখনো পুরো subject/chapter একবারে লোড করা যাবে না।
6. **ব্রাউজারের ডেটা বিশ্বাস করা যাবে না।** সেভের সময় `TeacherExamBuilder` সার্ভারে আবার যাচাই করে: প্রতিটা ID Teacher-এর ব্যবহারযোগ্য (`Question::usableInExamBy()` — approved pool অথবা নিজের pending), একই `class_subject`-এর, আর `online` হলে সব MCQ। এই যাচাই ও exam তৈরির লজিক শুধু ওই service-এ থাকবে।
7. **Subscription লিমিট সেভ করার মুহূর্তে একবারই গোনা হয়** — অনলাইনে প্রকাশ হোক বা শুধু PDF। আগে থেকে গোনা exam পরে publish করতে গেলে আবার লিমিটে আটকাবে না (`SubscriptionLimitService::isWithinMonthlyAllowance()`)।
9. **Exam এডিটও এই পেজ দিয়েই হয়** (`?exam=`; `TeacherExamBuilder::update()`), তৈরি করার মতো একই যাচাইসহ, আর লিমিটে আবার গোনা হয় না। **কোনো student পরীক্ষা শুরু করার পর প্রশ্ন বদলানো যায় না** (`canBeEdited()`) — নইলে তার নম্বর/পজিশন আর প্রশ্নপত্রের সাথে মিলবে না। তখন একমাত্র পথ **"পরীক্ষা বাতিল"** (`Exam::cancel()`): ওই exam-এর সব attempt ও উত্তর স্থায়ীভাবে মুছে exam-কে draft-এ ফেরায় (লিংক বন্ধ থাকে, যাতে এডিটের মাঝে কেউ শুরু না করে); পরে একই লিংকে আবার publish করা যায়। এটা ফেরানো যায় না, তাই confirmation ও log বাধ্যতামূলক।
8. **PDF বানানো হয় ব্রাউজারের Print → "Save as PDF" দিয়ে** (প্রিন্ট-উপযোগী পেজ, "শুধু প্রশ্ন" ও "উত্তরসহ" দুই রূপে) — সার্ভারে PDF বানানো হয় না, কারণ বাংলা যুক্তাক্ষর/গণিতের সূত্র ভাঙে আর সার্ভারে চাপ পড়ে।

## Roles & Permissions (সংক্ষেপে)

| Role | পারে | পারে না |
|---|---|---|
| Super Admin / Admin | সব প্রশ্ন/বোর্ড-পেপার দেখা/approve/reject, নতুন Admin ও Staff approve, Staff payout, Subscription plan ম্যানেজ | Student হিসেবে exam দেওয়া, Google flow দিয়ে সাইনআপ |
| Teacher | নিজের প্রশ্ন CRUD ("আমার প্রশ্ন"), approved pool থেকে প্রশ্ন বাছা ("প্রশ্ন বাছাই"), exam তৈরি/শেয়ার (subscription-গেটেড) | Staff earning দেখা, অন্যের pending প্রশ্ন দেখা |
| Staff | নিজের প্রশ্ন CRUD (pending/rejected), নিজের earning দেখা — Admin approve করার আগে **কিছুই না** (`status=pending`); `suspended` হলে Dashboard/Earning দেখা যায় কিন্তু প্রশ্ন add করা যায় না | exam তৈরি করা, অন্যের প্রশ্ন দেখা, approved pool ব্রাউজ করা, `permanent_suspend` হলে প্যানেলে ঢোকা |
| Student (Login) | নিজের attempt/result, নিজের subscription দিয়ে self-practice exam | প্রশ্ন দেখা exam-এর বাইরে, অন্যের result দেখা |
| Student (Guest) | শুধু নির্দিষ্ট share-link-এর exam attempt দেওয়া | self-practice exam, লগইন-নির্ভর যেকোনো ফিচার |

## Tech Stack

- Backend: **Laravel 11**
- Panel/Admin framework: **Filament v5** (Livewire v4-ভিত্তিক, `Filament\Schemas` namespace), ৪টা Panel Provider (`/admin`, `/teacher`, `/staff`, `/student`)
- Database: **MySQL**
- Role/Permission: **Spatie `laravel-permission`** + **Filament Shield**
- Authentication: **Laravel Socialite** (Google OAuth — Teacher/Staff/Student), Filament ডিফল্ট auth (Admin/Super Admin, Registration বন্ধ)
- Rich Text: **CKEditor 5** + math plugin (KaTeX) — Bangla plain text, math শুধু inline widget-এ (MathLive বাদ দেওয়া হয়েছে বাংলা conjunct/spacing সমস্যার কারণে)
- Encryption: Laravel `encrypted` cast (bank info-র জন্য)
- Payment Gateway: **এখনো চূড়ান্ত হয়নি** — SSLCommerz/bKash/Nagad-এর মধ্যে যেটা ঠিক হবে এখানে আপডেট করুন
- Reports: `maatwebsite/laravel-excel`
- PDF: `spatie/laravel-pdf` বা DomPDF

## Folder Structure (প্রস্তাবিত)

```
app/
  Providers/Filament/
    AdminPanelProvider.php      ← registration(false)
    TeacherPanelProvider.php    ← Google-only login
    StaffPanelProvider.php      ← Google-only login
    StudentPanelProvider.php    ← Google-only login
  Filament/
    Admin/
      Resources/
        QuestionResource.php
        AcademicClassResource.php   ✅ তৈরি হয়ে গেছে
        SubjectResource.php
        ChapterResource.php
        BoardResource.php
        BoardQuestionPaperResource.php
        TeacherResource.php
        StaffResource.php            ← approve/suspend/permanentSuspend/reactivate actions
        QuestionRateResource.php
        StaffPayoutResource.php
        SubscriptionPlanResource.php
        SubscriptionResource.php
        PaymentResource.php
        ExamResource.php
      Pages/
        Auth/Login.php               ← Filament default (password)
    Teacher/
      Resources/
        QuestionResource.php
        ExamResource.php
      Pages/
        Auth/Login.php               ← custom, "Continue with Google" only
        MySubscription.php
    Staff/
      Resources/
        QuestionResource.php
      Pages/
        Auth/Login.php               ← custom, "Continue with Google" only
        MyEarnings.php
        PendingApprovalNotice.php    ← status=pending হলে দেখানো হবে
    Student/
      Pages/
        Auth/Login.php               ← custom, "Continue with Google" only
        JoinExam.php                 ← share-link entry (login/guest choice)
        TakeExamPage.php
        ExamResultPage.php
        GeneratePracticeExam.php     ← Auto-Generate মোড
        BuildPracticeExam.php        ← Manual Selection মোড
        MySubscription.php
  Models/
    User.php                     ← google_id, avatar, status
    AcademicClass.php
    Subject.php
    ClassSubject.php
    Chapter.php
    Question.php                 ← parent_id/version/is_latest, question_type(mcq|cq)
    QuestionCqPart.php
    QuestionApprovalLog.php
    Board.php
    BoardQuestionPaper.php
    BoardMcqQuestion.php
    BoardCqQuestion.php
    BoardCqQuestionPart.php
    Exam.php                     ← share_token, exam_type, generation_mode
    ExamAttempt.php               ← nullable student_id, is_guest, guest_name
    AttemptAnswer.php
    StaffProfile.php              ← encrypted bank fields
    QuestionRate.php
    StaffEarning.php
    StaffPayout.php
    SubscriptionPlan.php
    Subscription.php
    Payment.php
  Policies/
    QuestionPolicy.php
    BoardQuestionPaperPolicy.php
    ExamPolicy.php                 ← subscription/limit check এখানেও রাখা যায়
    StaffEarningPolicy.php
  Observers/
    QuestionObserver.php           ← versioning + CQ marks auto-sync + approve হলে earning trigger
    BoardCqQuestionObserver.php    ← CQ marks auto-sync (board version)
  Http/Controllers/
    Auth/GoogleAuthController.php  ← redirect + callback (role-aware, find-or-create)
    GuestExamController.php        ← unauthenticated guest attempt flow (Filament-এর বাইরে)
database/
  migrations/
  seeders/
    SuperAdminSeeder.php
System-Design.md
CLAUDE.md
```

## Coding Conventions

### DRY — কোথাও কোনো কোড ডুপ্লিকেট লেখা যাবে না (Don't Repeat Yourself)

> **একই লজিক, একই মার্কআপ বা একই কনফিগারেশন দুই জায়গায় লেখা নিষেধ। নতুন কিছু লেখার আগে দেখুন সেটা আগে থেকে আছে কি না; থাকলে সেটাই ব্যবহার করুন, আর দ্বিতীয়বার দরকার পড়লে কপি না করে এক জায়গায় তুলে এনে দুই জায়গা থেকে ব্যবহার করুন।**

1. **লেখার আগে খুঁজুন।** নিচের টেবিলের শেয়ার করা অংশগুলো আগে দেখুন, তারপর `grep` করুন। একই রকম কিছু পেলে সেটাকেই বাড়ান (প্যারামিটার/slot/abstract method দিয়ে), নতুন কপি বানাবেন না।
2. **প্যানেল-ভেদে পার্থক্য শুধু "কোনটা" — "কীভাবে" না।** Admin/Teacher/Staff/Student-এর একই ধরনের পেজ একটা abstract base class থেকে আসবে; প্যানেলের নিজের ক্লাসে থাকবে শুধু তার resource/URL/লেবেল।
3. **Blade-এ একই ব্লক দুইবার দেখা গেলেই সেটা component** (`resources/views/components/`) বা partial হবে — Guest পেজ (plain HTML) আর Filament পেজ দুটোই একই component ব্যবহার করবে।
4. **Tailwind-এর লম্বা class-স্ট্রিং বারবার কপি করবেন না।** বারবার লাগা স্টাইল `resources/css/question-display.css`-এ একটা `qb-*` ক্লাস হবে (এই ফাইল `app.css` ও Filament theme দুই bundle-এই যায়)। রঙ/gradient-এর তালিকা PHP-তে এক জায়গায় (পুরো ক্লাস-নাম literal হিসেবে, কারণ Tailwind জোড়া-লাগানো নাম ধরতে পারে না)।
5. **ব্যবসায়িক নিয়ম সবসময় Model/Service-এ একটাই মেথডে** — controller, Livewire page আর Blade শুধু সেটা ডাকবে, নিজে শর্ত লিখবে না।
6. **ব্যতিক্রম:** Filament-এর ঘোষণামূলক কনফিগ যেখানে প্রতিটা লাইনই আলাদা তথ্য (যেমন একটা resource-এর কলামের তালিকা) সেটা ডুপ্লিকেট না। টেস্টে পড়ার সুবিধার জন্য সেটআপ পুনরাবৃত্তি চলতে পারে।

**শেয়ার করা অংশগুলো (নতুন কপি না বানিয়ে এগুলোই ব্যবহার করুন):**

| কী দরকার | কোথায় আছে |
|---|---|
| চার প্যানেলের অভিন্ন কনফিগ (theme, middleware, navigation) | `App\Filament\Support\PanelDefaults::apply()` |
| প্রতিটা প্যানেলের `QuestionResource`-এর অভিন্ন অংশ (model, form, table) | `Support\Resources\QuestionResourceBase` — `getEloquentQuery()` কিন্তু প্রতিটা প্যানেলে নিজেই লিখতে হবে (নিয়ম ২) |
| প্রশ্ন তৈরি/এডিট পেজ (সব প্যানেল) | `Support\Pages\Questions\CreateQuestionPage`, `EditQuestionPage` |
| Class → Subject → Chapter → Topic select | `Support\ContentHierarchySchema` (`classSelect()` … `topicSelect()`) |
| MCQ অপশনের ফিল্ড + "একটাই সঠিক উত্তর" নিয়ম | `Support\McqOptionsSchema` |
| প্রশ্নের ফর্ম / টেবিল / View | `Support\QuestionFormSchema`, `QuestionsTable`, `QuestionInfolist` |
| JSON পেস্ট করে একসাথে অনেক প্রশ্ন যোগ (Teacher/Admin; Staff না) — যাচাই, গণিত (`$...$` → KaTeX embed) ও তৈরি | `App\Services\QuestionJsonImporter`, `QuestionImportText`; বাটন `Support\QuestionJsonImportAction`; অনুমতি `QuestionPolicy::import()`; একবারের সীমা `BillingSetting.question_import_max` |
| টেবিলের edit/delete/bulk-delete অ্যাকশন | `Support\TableActions` |
| ফর্মে "কোন user" সিলেক্ট | `Support\UserSelect` |
| নাম + ক্রমসহ আইটেম (class/chapter/topic) যোগ-এডিট-ডিলিট | `Support\Concerns\ManagesOrderedItems` |
| Browse পেজ (class/subject/chapter) ও কার্ড | `Support\Pages\Questions\Browse*Page`, `<x-browse.grid>`, `<x-browse.card>`, `<x-browse.meta>`, `<x-browse.edit-delete-menu>` |
| কার্ডের রঙের palette | `Support\CardPalette` |
| Dashboard-এর stat কার্ড, "Recent questions" | `Support\Widgets\DashboardStat`, `RecentQuestionsWidget` |
| Self-practice পেজ (লিমিট চেক + শুরু) | `Support\Pages\SelfPracticeExamPage` |
| Staff-এর প্রশ্ন breakdown পেজ | `Support\Pages\QuestionStatusBreakdownPage` |
| approve/reject-এর status বদল | `Models\Concerns\HasApprovalStatus` |
| শেয়ার-লিংকের exam খোঁজা, লিংক সচল কি না | `Exam::findPublishedByShareToken()`, `isAcceptingAttempts()` |
| একটা subject-এর approved প্রশ্ন | `Question::scopeOfSubject()`, `approvedOptionsForSubject()` |
| পড়ার জন্য প্রশ্ন দেখানো (অপশন/CQ অংশ) | `filament.support.questions.question-body`, `Support\QuestionDisplay` |
| পরীক্ষার খাতা (শিরোনাম + নাম + ঘড়ি + প্রশ্ন + সতর্কবার্তা) — Guest ও লগইন করা student হুবহু একই দেখে | `<x-exam-paper>`, `<x-exam-heading>`, `<x-exam-participant>`, `<x-exam-timer>`, `<x-exam-submit-warning>`, `resources/js/exam-timer.js` |
| ফলাফল | `<x-exam-result>`, `<x-exam-result-question>` |
| পরীক্ষার ফলাফল-তালিকা ও পজিশন (Teacher) | `App\Services\ExamResultSheet` (র‍্যাংকিং-এর একমাত্র জায়গা), `teacher.partials.result-sheet-table` |
| Guest-এর নাম + ফোন/ইমেইল ফিল্ড | `<x-guest-identity-fields>` |
| ভাষা বদলানোর বাটন (সব জায়গায়) | `<x-language-switcher variant="light|dark|panel">` |
| গণিতের সূত্র রেন্ডার (দুই JS bundle-এই) | `resources/js/katex-embeds.js` |

### অন্যান্য

- **Filament v5 কনভেনশন মেনে চলুন** — v3-এর পুরনো Form/Table syntax v4/v5-এ কাজ নাও করতে পারে; কোড লেখার আগে official v5 docs চেক করুন।
- প্রতিটা নতুন Resource/Page লেখার আগে কোন role/panel-এর জন্য তা ঠিক করে সেই অনুযায়ী middleware/policy লাগান।
- Teacher/Staff/Student প্যানেলে কখনো password-based login/registration কম্পোনেন্ট যোগ করবেন না — Google OAuth-ই একমাত্র পথ।
- আর্থিক লজিক (`staff_earnings`, `payments`) কখনো UI/controller-এ ছড়িয়ে না রেখে Observer/Service class-এ কেন্দ্রীভূত রাখুন — টাকার হিসাব দুই জায়গায় লেখা থাকলে একটা জায়গা মিস হয়ে বাগ হওয়ার ঝুঁকি বেশি।
- CQ marks calculation (`questions` ও `board_cq_questions` দুই জায়গাতেই) Observer-এ রাখুন, ফর্ম submit handler-এ ম্যানুয়ালি যোগ করবেন না।
- সংবেদনশীল ডেটা (bank info) কখনো log/dump/exception message-এ প্লেইন টেক্সটে না যায় সেটা নিশ্চিত করুন।
- Guest-related কোড Filament-এর auth boundary-র বাইরে রাখুন — Filament panel middleware-এর ভেতরে guest access কখনো ঢোকাবেন না।

## Commands

```bash
composer install
php artisan migrate
php artisan db:seed              # SuperAdminSeeder সহ
php artisan serve
php artisan test
php artisan make:filament-panel <name>
php artisan make:filament-resource <Name> --panel=<panel>
php artisan make:observer QuestionObserver --model=Question
```

## Testing Priorities

1. **Google auth flow:** নতুন email দিয়ে প্রথমবার Google login করলে সঠিক role-এ user তৈরি হয় (Teacher/Student → active, Staff → pending); existing email হলে নতুন row তৈরি না হয়ে login হয়; role mismatch (Student একাউন্ট দিয়ে Teacher panel-এ ঢোকার চেষ্টা) ব্লক হয়। Suspended Teacher/Staff Dashboard/Earning দেখতে পারে কিন্তু প্রশ্ন add করতে পারে না; permanently suspended হলে প্যানেলে ঢোকাই ব্লক হয়।
2. **Staff pending approval:** নতুন Staff একাউন্ট approve না হওয়া পর্যন্ত Staff Panel-এর কোনো action করতে পারে না; Admin approve করার পর সব কাজ করতে পারে।
3. **Question visibility:** Teacher A/Staff A-এর প্রশ্ন Teacher B/Staff B-এর কাছে অদৃশ্য থাকে যতক্ষণ না approve হয়।
4. **Versioning:** Approved প্রশ্ন এডিট করলে নতুন pending row তৈরি হয় (CQ হলে parts-ও কপি হয়), পুরনো exam-গুলো ভাঙে না।
5. **CQ marks sync:** `question_cq_parts`/`board_cq_question_parts`-এর marks বদলালে parent `questions.marks`/`board_cq_questions.marks` automatically আপডেট হয়।
6. **Board question paper:** পুরো পেপার একসাথে approve/reject হয় (individual question-level না); approved board question কখনো সাধারণ approved pool/self-practice-এ মিশে যায় না।
7. **Staff earning:** প্রশ্ন approve হলেই ঠিক rate অনুযায়ী `staff_earnings` row তৈরি হয়; reject হলে হয় না; rate পরে বদলালেও পুরনো entry-র amount অপরিবর্তিত থাকে।
8. **Payout:** batch payout করলে সংশ্লিষ্ট সব earning `paid` হয়ে যায়, দ্বিতীয়বার payout করলে ডাবল পেমেন্ট না হয়।
9. **Subscription limit:** ফ্রি লিমিট (যেমন ৩টা/মাস) শেষ হলে নতুন exam/practice-exam তৈরি ব্লক হয়; পরের মাসে আবার রিসেট হয় (কোনো cron/reset job ছাড়াই, কারণ query মাস অনুযায়ী গণনা করে)।
10. **Self-practice দুই মোড:** Auto-Generate শুধু approved+latest প্রশ্ন থেকে random বাছে; Manual Selection-এ Student শুধু approved+latest প্রশ্ন দেখতে/বাছতে পারে (pending/rejected কখনো না); দুই মোডের exam-ই একসাথে monthly limit-এ গোনা হয়।
11. **Payment idempotency:** একই gateway callback দুইবার এলে ডাবল subscription/payment তৈরি না হয়।
12. **Exam sharing:** share_token দিয়ে Login ও Guest দুই পথেই attempt দেওয়া যায়; link expire/deactivate হলে ব্লক হয়; guest submission rate-limited।
13. **Non-approved question:** exam-এ attach করার চেষ্টা (UI ও সরাসরি Eloquent/Tinker দুইভাবেই) ব্লক হয়।
14. **Admin registration:** `/admin` panel-এর পাবলিক registration route accessible না (404/disabled)।

## যা করা যাবে না

- Teacher/Staff/Student প্যানেলে কোনো password-based login/registration/forget-password ফর্ম যোগ করা যাবে না — শুধু Google OAuth।
- Admin Panel-এ public registration কখনো খোলা রাখা যাবে না।
- Question visibility-এর approval-check শুধু frontend-এ রাখা যাবে না।
- Bank account/mobile banking নম্বর কখনো plain (unencrypted) column-এ রাখা যাবে না।
- Rejected প্রশ্নের জন্য কোনো `staff_earnings` তৈরি করা যাবে না।
- Board question paper-এর কোনো অংশ সাধারণ `questions` পুল/approved pool/self-practice-এর সাথে মেশানো যাবে না।
- Guest exam attempt route rate-limit ছাড়া open রাখা যাবে না।
- Subscription limit চেক bypass করে "quick fix" হিসেবে exam creation খোলা রাখা যাবে না।
- Payment/payout সংক্রান্ত কোনো অ্যাকশন log/audit trail ছাড়া করা যাবে না।
- Self-practice exam থেকে Auto-Generate বা Manual Selection — কোনো একটা মোড বাদ দিয়ে শুধু একটা রাখা যাবে না, দুটোই থাকতে হবে।
- CQ-এর marks কোনো ফর্মে ম্যানুয়ালি টাইপ করানো যাবে না — সবসময় parts থেকে auto-calculate।

</question-bank-guidelines>
