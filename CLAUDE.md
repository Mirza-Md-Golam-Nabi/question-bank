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

বিস্তারিত ডকুমেন্টেশন (সিস্টেম কীভাবে কাজ করে, ডেটাবেজ, প্রতিটা ফিচারের কোড ও টেস্ট কোথায়): `docs/README.md`

## ⭐ মূল বিজনেস রুলসমূহ (কখনো ভাঙা যাবে না)

### ১. Authentication — Google OAuth (Teacher/Staff/Student/School) + Filament Default (Admin/Super Admin)
> **Teacher/Staff/Student পুরোপুরি Google OAuth-only — কোনো password ফর্ম, রেজিস্ট্রেশন পেজ, বা forget-password ফ্লো নেই। শুধু Admin/Super Admin Filament-এর ডিফল্ট email/password Login ব্যবহার করবে, আর সেখানে Registration পাবলিকলি বন্ধ।**

1. Teacher/Staff/Student প্যানেলের Login page-এ শুধু "Continue with Google" বাটন — `/auth/google/redirect/{role}` → Google callback-এ email/`google_id` match করে existing user login, না পেলে নতুন `users` row তৈরি (session-এ রাখা intended role অনুযায়ী)।
2. নতুন **Teacher/Student** একাউন্ট সাথে সাথে `status=active`।
3. নতুন **Staff** ও **School** একাউন্ট `status=pending`-এ তৈরি হয় (`UserRole::needsApproval()`) — Admin approve না করা পর্যন্ত প্যানেলের middleware (`EnsureAccountIsApproved`) তাকে ব্লক করবে ("অনুমোদনের অপেক্ষায়" পেজ দেখাবে; School সেখানে স্কুলের নাম/EIIN/ঠিকানা/ফোন দেয়), `QuestionResource`-এ কিছু করতে দেবে না।
4. **Admin Panel-এ পাবলিক Registration বন্ধ** (`->registration(false)`) — যে কেউ সাইনআপ করে Admin হতে পারবে না। প্রথম Super Admin `SuperAdminSeeder` দিয়ে তৈরি, এরপরের Admin Super Admin নিজে `AdminResource` থেকে বানাবে।
5. `users.password_hash` শুধু `admin`/`super_admin`-এর জন্য filled থাকবে; বাকি রোলে সবসময় `null`। `users.google_id` উল্টোটা — শুধু Teacher/Staff/Student-এ filled।
6. **`users.status` চারটা মান নিতে পারে:** `pending` (Staff-এর জন্য, Admin approve-এর অপেক্ষায়), `active`, `suspended`, `permanent_suspend`। Teacher/Staff দুই ক্ষেত্রেই Admin `suspended` করলে প্যানেলে ঢুকতে পারবে (Dashboard/Earning-এর মতো নিজের পেজ দেখতে পারবে), কিন্তু নতুন প্রশ্ন **add করতে পারবে না** (`QuestionPolicy::create()`-এ হার্ডকোড থাকবে)। `permanent_suspend` করলে পুরো প্যানেলে ঢোকাই ব্লক (`User::canAccessPanel()`-এ হার্ডকোড)।

### ২. Question Approval Visibility
> **Teacher/Staff-এর তৈরি প্রশ্ন Admin approve করার আগ পর্যন্ত অন্য কোনো Teacher/Staff দেখতে বা exam-এ ব্যবহার করতে পারবে না — শুধু owner ও Admin দেখবে।**

1. প্রতিটা প্রশ্নের `status`: `pending | approved | rejected`
2. Admin-এর নিজের আপলোড সরাসরি `approved`; Teacher/Staff-এরটা ডিফল্টভাবে `pending`
3. **প্রতিটা Filament Resource-এর `getEloquentQuery()`-তে filter হার্ডকোড থাকতে হবে**, শুধু UI hide না। Teacher Panel-এর "আমার প্রশ্ন" ও Staff Panel দুটোই শুধু নিজের প্রশ্ন দেখায় (`Question::ownedBy()`)। Teacher অন্যদের approved প্রশ্ন দেখে ও বাছে শুধু "প্রশ্ন বাছাই" পেজে, যা সরাসরি approved pool (`Question::approvedPool()` — `status='approved' AND is_latest=true`) পড়ে।
4. Exam-এ প্রশ্ন attach করার সময় (relationship field ও model-level Observer উভয় জায়গায়) নিশ্চিত করতে হবে `status='approved' AND is_latest=true`। **একমাত্র ব্যতিক্রম:** Teacher নিজের `pending` (ও `is_latest`) প্রশ্ন **নিজের** exam-এ ব্যবহার করতে পারে, যাতে আজ লেখা প্রশ্নে আজই পরীক্ষা নেওয়া যায় — অন্য কেউ সেটা approve না হওয়া পর্যন্ত দেখে না, আর Student-এর self-practice সবসময় শুধু approved pool। `rejected` প্রশ্ন নতুন exam-এ যায় না, কিন্তু যে exam-এ আগে থেকে আছে সেখানে থেকে যায়। পেছনের ডুপ্লিকেট যাচাইয়ে `clear` না হওয়া প্রশ্ন (নিয়ম ৩.৭.১) কোনো নতুন exam-এ যায় না। এই ব্যতিক্রম শুধু `Question::scopeUsableInExamBy()`-এ লেখা।
5. `QuestionPolicy`-তেও owner+status ডাবল-চেক থাকবে। Teacher/Staff নিজের প্রশ্ন ডিলিট করতে পারে শুধু যতক্ষণ সেটা `approved` না এবং কোনো exam-এ নেই (`isOnAnExam()`); আর কোনো student ওই প্রশ্নসহ পরীক্ষা শুরু করার পর approved-নয় এমন প্রশ্ন কেউ (Admin-ও না) এডিট করতে পারে না (`Question::isFrozenByAttempts()`) — approved প্রশ্নের এডিট নতুন version বানায় বলে সেটা চলে।
6. এই ফিল্টার মিস হলে সেটা **critical security bug**।

### ৩. Question Types ও Content Hierarchy
> **শুধু দুই ধরনের প্রশ্ন: `mcq` ও `cq` (সৃজনশীল)। `true_false`/`short`/`descriptive` নেই।**

1. Chain সবসময়: `academic_classes → class_subjects (pivot) → chapters → questions`।
2. `subjects` একটা মাস্টার লিস্ট (class-নির্ভর না) — কোন class-এ কোন subject আছে সেটা `class_subjects` পিভট ট্র্যাক করে। `chapters.class_subject_id` সরাসরি `class_subjects.id`-কে পয়েন্ট করে, `subjects.id`-কে না (একই subject বিভিন্ন class-এ আলাদা chapter সেট রাখে)।
3. CQ প্রশ্নে `questions.question_text`/`question_image` = উদ্দীপক, আর ৪টা সাব-প্রশ্ন `question_cq_parts`-এ (`knowledge`/`comprehension`/`application`/`higher_application`)।
4. CQ-এর `questions.marks` = তার ৪টা `question_cq_parts.marks`-এর যোগফল — **`QuestionObserver`-এ auto-sync**, ম্যানুয়ালি সেট করা যাবে না।
4.1. **MCQ-তে ঐচ্ছিক ব্যাখ্যা** (`explanation`; প্রশ্নভাণ্ডার ও প্রশ্নপত্র দুটোতেই, CQ-তে না)। দেখায় **শুধু যেখানে সঠিক উত্তর দেখায়** — self-practice-এর ফলাফল, Teacher-এর পরীক্ষায় উত্তর প্রকাশের পর (`Exam::showsAnswersToStudents()`), প্রিন্টের "উত্তরসহ" কপি; পরীক্ষা চলার সময় কখনো না।
5. **উদ্দীপকভিত্তিক MCQ** (অভিন্ন তথ্যভিত্তিক): একটা উদ্দীপক (লেখা/ছবি) আর তার নিচে ২–৬টা MCQ। প্রতিটা MCQ আগের মতোই পূর্ণ প্রশ্ন (নিজের গ্রেডিং, difficulty, সংস্করণ), শুধু `stimulus_id` + `stimulus_order` জানে — প্রশ্নভাণ্ডারে `question_stimuli`, প্রশ্নপত্রে `board_mcq_stimuli`। গ্রুপ একসাথে approve/reject হয় (`Question::approve()`), approved গ্রুপ এডিট করলে পুরো গ্রুপের নতুন সংস্করণ (`StimulusGroupService`); গ্রুপের প্রশ্ন একা এডিট হয় না। Teacher গ্রুপের যেকোনো কয়টা প্রশ্ন নিতে পারেন; Auto-Generate-এ একটা প্রশ্ন উঠলে উদ্দীপকসহ শুধু সেটাই আসে। "নিচের উদ্দীপকটি পড়ে ৫ ও ৬ নম্বর প্রশ্নের উত্তর দাও" কখনো লেখায় থাকে না — প্রতিবার ক্রম দেখে বসে, আর গ্রুপের প্রশ্ন সবসময় পাশাপাশি (এলোমেলো করার সময়ও এক ব্লক) — সব শুধু `QuestionBlocks`-এ। Staff প্রতিটা MCQ-র জন্য আলাদা প্রশ্ন হিসেবে টাকা পান।
6. `academic_classes`/`subjects`/`class_subjects`/`chapters` — সবগুলো Admin-only reference data (`created_by` নেই, Teacher/Staff শুধু select করবে, নিজে তৈরি করতে পারবে না)।
7. **ডুপ্লিকেট প্রশ্ন:** নতুন প্রশ্ন মেলানো হয় একই অধ্যায়ের (প্রশ্নপত্রে একই পেপারের) সেই প্রশ্নগুলোর সাথে যা user দেখতে পান। হুবহু একই (লেখা + অপশনের সেট, যতিচিহ্ন/ফাঁকা/অঙ্কের রূপ বাদে) ফর্মে সেভ হয় না; লেখা ও অপশন দুটোই Admin-এর % (`BillingSetting.duplicate_similarity_percent`) মিললে "সম্ভাব্য ডুপ্লিকেট" — ফর্মে "তবুও সেভ করুন"; Admin approve-এর সময় দেখেন। **Admin/Teacher-এর JSON-এ হুবহু একই প্রশ্ন বাদ যায় না** — যোগ হয়ে নিয়ম ৭.১-এর যাচাইয়ে Admin-এর কাছে যায় (`QuestionDuplicateScreening::screensQuestionsBy()`, `ImportedQuestions::withoutExact()`); সম্ভাব্য ডুপ্লিকেট আগের মতো প্রিভিউতে সংখ্যাসহ টিক/"Accept all" — টিক দিলে যোগ (তারপর একই যাচাই), না দিলে বাদ। School-এর JSON (পেছনের যাচাই নেই) ও প্রশ্নপত্রের JSON-এ আগের মতো: হুবহু বাদ, সম্ভাব্য হলে টিক/"Accept all"। সংখ্যা, সূত্র বা না-বাচক শব্দ আলাদা হলে কখনো ডুপ্লিকেট না। বিচার শুধু `DuplicateQuestionFinder`-এ।
7.1. **পেছনের ডুপ্লিকেট যাচাই (queue):** তার ওপর প্রশ্নভাণ্ডারের প্রতিটা নতুন বা বদলানো প্রশ্ন queue-তে অধ্যায়ের **সব** চলতি প্রশ্নের সাথে মেলানো হয় (অন্যের pending-সহ; শুধু guard + দৈর্ঘ্য মেলা প্রশ্ন index দিয়ে পড়া হয়)। যাচাই চলার সময় (`checking`) ও মিললে (`suspected`) প্রশ্নটা pool-এ, কোনো পরীক্ষায় (লেখক Teacher-এর নিজেরটাতেও) বা approve-এ যায় না, যতক্ষণ না Admin "সম্ভাব্য ডুপ্লিকেট" পেজে **রাখেন** (→ `clear`) বা **স্থায়ীভাবে মোছেন** (কোনো পরীক্ষায় থাকলে মোছা না, reject)। লেখক শুধু "Admin যাচাই করছেন" দেখেন, কার সাথে মিলেছে না। আগে থেকে থাকা প্রশ্নেও খোঁজা যায় (দুটো একই হলে পরেরটা আটকায়)। বিগত সালের প্রশ্নপত্র ও School-এর নিজের প্রশ্ন বাদ। নিয়ম শুধু `QuestionDuplicateScreening`-এ।

### ৪. Versioning (Approved প্রশ্ন এডিট)
- একই `questions` টেবিলে `parent_id`, `version`, `is_latest` কলাম দিয়ে versioning।
- Approved প্রশ্ন এডিট করলে: পুরনো row অক্ষত + `is_latest=false`, নতুন row `status=pending`, `version+=1`।
- CQ হলে `question_cq_parts`-এর ৪টা row-ও নতুন `question_id`-এর সাথে কপি হবে, পুরনো version-এর parts অক্ষত থাকবে।
- লজিক `QuestionObserver`-এ কেন্দ্রীভূত রাখতে হবে, কোথাও ডুপ্লিকেট করা যাবে না।

### ৫. Board Question Papers — সম্পূর্ণ আলাদা সাবসিস্টেম
> **বোর্ড প্রশ্ন (`boards`/`board_question_papers`/`board_mcq_questions`/`board_cq_questions`/`board_cq_question_parts`) কখনো সাধারণ `questions` পুলের সাথে মিশবে না — আলাদা টেবিল, আলাদা approval workflow।**

1. `board_question_papers` = একটা উৎস + `year` + ঐচ্ছিক `unit`-এর পুরো প্রশ্নপত্র; শিক্ষা বোর্ড/স্কুল টেস্টের পেপারে `class_subject_id`-ও থাকে, ভর্তি/চাকরির পেপারে না (সব বিষয় একসাথে — `PaperSourceType::hasClassSubject()`)। পুরোটা একসাথে `pending`/`approved`/`rejected` হয় (individual question-level approval না)।
2. একই উৎস + সাল + ইউনিট (+ শ্রেণি-বিষয়)-এর পেপার দুইবার তৈরি করা যাবে না — `BoardQuestionPaper::isTaken()` (বোর্ডের পেপারে ডেটাবেজের unique key-ও)।
3. `board_cq_questions.marks` একইভাবে তার `board_cq_question_parts`-এর যোগফল থেকে auto-sync।
4. Self-practice/Teacher exam-এর question picker-এ board question papers কখনো approved pool-এর সাথে mix করা যাবে না — এটা আলাদা browsing/UI flow ("বিগত সালের প্রশ্ন" পেজ)। **প্রশ্নপত্র ব্যবহার হয় শুধু পুরোটা একটা পরীক্ষা হিসেবে** (`exams.board_question_paper_id`; উত্তর `attempt_answers.board_mcq_question_id`-এ): Student নিজে বসে (সাথে সাথে উত্তর ও ব্যাখ্যা), Teacher পুরোটা পরীক্ষা হিসেবে নেন (অনলাইন = MCQ, প্রিন্ট = MCQ + CQ)। লজিক শুধু `PastPaperExamService`-এ। এই প্রশ্নের difficulty/ভুলের তালিকা/Teacher-এর পরিসংখ্যান হয় না।
5. `boards` = প্রশ্নপত্রের **উৎস**, ধরন (`type`, `PaperSourceType`): শিক্ষা বোর্ড, মেডিকেল ভর্তি, বিশ্ববিদ্যালয় ভর্তি, চাকরির পরীক্ষা, স্কুল/কলেজের টেস্ট; বাংলা ও ইংরেজিতে পূর্ণ ও সংক্ষিপ্ত নাম। প্রশ্ন দেখানোর সময় কোথা থেকে এসেছে সংক্ষিপ্ত নাম + সাল দিয়ে দেখায় (`<x-question-source>`)।
6. পেপার তোলেন Admin ও Staff (Staff শুধু নিজেরটা, approve হওয়ার আগ পর্যন্ত বদলাতে পারেন); প্রশ্ন একটা একটা করে প্রশ্নভাণ্ডারের এডিটর দিয়ে, MCQ-তে ঐচ্ছিক ব্যাখ্যা ও CQ-র অংশে ঐচ্ছিক উত্তর। কেউ পরীক্ষা দেওয়ার পর পেপার আর বদলানো যায় না (`hasBeenSat()`)।

### ৬. Staff Payment
> **Staff-এর প্রশ্ন `approved` হওয়ার মুহূর্তেই তার পারিশ্রমিক `staff_earnings` ledger-এ যোগ হবে — rejected প্রশ্নের জন্য কোনো টাকা যোগ হবে না।**
- Rate `question_rates` টেবিল থেকে (subject-ভিত্তিক, না থাকলে default) নেওয়া হবে এবং **approve করার মুহূর্তের rate `staff_earnings.amount`-এ snapshot হিসেবে সেভ থাকবে** — পরে rate বদলালেও পুরনো entry অপরিবর্তিত থাকবে।
- `staff_profiles.bank_account_number` ও `mobile_banking_number` **অবশ্যই `encrypted` cast দিয়ে** স্টোর করতে হবে — plain column-এ কখনো না।
- Payout একটা batch action (`staff_payouts`), individual earning row-কে সরাসরি "paid" না বানিয়ে payout batch-এর মাধ্যমে।
- **বিগত সালের প্রশ্নপত্র:** Staff-এর পেপার approve হলে **পেপারপ্রতি** টাকা — পেপারের ধরন অনুযায়ী Admin-এর রেট (`BillingSetting.paper_rates`), approve-এর মুহূর্তের রেট snapshot, পেপারপ্রতি একবারই (`BoardQuestionPaper::approve()`); rejected পেপারে কিছু না।

### ৭. Subscription / Freemium
> **Teacher exam তৈরি/publish/print করার আগে, ও Student self-practice exam জেনারেট করার আগে (সব মোডে) — user-এর plan-এ সেই কাজটা আছে কিনা সার্ভারে চেক করতে হবে। Plan না থাকলে কিছুই না।**
- **দামের তালিকা পুরোপুরি ডায়নামিক** — কোডে কোনো plan/দাম নেই। Admin প্রতিটা plan-এ মেয়াদ (দিন/মাস/বছর/নির্দিষ্ট তারিখ পর্যন্ত) আর **শর্ত** যোগ-বিয়োগ করেন: শ্রেণি, বিষয় (দুটোই ক্রেতা কেনার সময় বাছতে পারে; বাছাই করা বিষয় plan-এর সব শ্রেণিতে পড়ানো হতে হয় — `PlanConditions::pickableSubjectIds()`), difficulty, সুবিধা (অনলাইন পরীক্ষা / প্রিন্ট / বিগত সালের প্রশ্ন / পারফরম্যান্স বিশ্লেষণ — নিয়ম ১১), দিনে ও মাসে পরীক্ষা, আর বিগত সালের প্রশ্নের শর্ত — ধরন, উৎস, কত বছরের পুরনো, "প্রতি N দিনে X টা" (চলমান গণনা; self-practice/পরীক্ষার সীমা থেকে আলাদা; অর্থ `PastPaperAccess`-এ, যাচাই ও গণনা `SubscriptionLimitService::authorizePastPaper()`-এ)। যে বিষয়ে শর্ত নেই সেখানে সীমা নেই। শর্তের অর্থ শুধু `PlanConditions`-এ।
- একজন user-এর অধিকার = তার সব সক্রিয় subscription + role-এর ফ্রি plan মিলিয়ে; যাচাই ও "কোন plan-এ গোনা হবে" শুধু `SubscriptionLimitService`-এ (`authorize()`, `allows()`)। প্রতিটা exam যে subscription-এ অনুমতি পেল তার সীমাতেই গোনা হয় (`exams.subscription_id`; ফাঁকা = ফ্রি plan)।
- Subscription কেনার মুহূর্তে plan-এর শর্তের কপি ও ক্রেতার বাছাই রেখে দেয় — পরে plan বদলালে কেনা জিনিস বদলায় না।
- Teacher-এর ট্রায়াল একাউন্টপ্রতি একবারই। Student-এর প্রোফাইলে শ্রেণি থাকে; self-practice সবসময় নিজের শ্রেণি থেকে।
- **Group plan:** "আসন" শর্তওয়ালা plan-এর ক্রেতা অন্যদের Google email দিয়ে যোগ করেন (প্রত্যেকে নিজের একাউন্টে, শুধু plan-এর role); ক্রেতার subscription শেষ হলে সবার শেষ; কাউকে সরানো Admin-এর দিনে একবার। নিয়ম শুধু `SubscriptionMemberService`-এ; শেয়ার করা plan `User::activeSubscriptions()`-এ আসে। দৈনিক/মাসিক গণনা প্রত্যেকের আলাদা।
- **ডিভাইস সীমা:** Teacher/Student একসাথে Admin-এর সংখ্যার বেশি ডিভাইসে লগইন থাকতে পারে না — নতুন লগইনে সবচেয়ে পুরনোটা লগআউট (`DeviceSessionService`)।
- লিমিট চেক করার সময় আলাদা counter টেবিল না রেখে সরাসরি query দিয়ে গণনা (দিন/মাসের তারিখ-সীমা) — সরল ও accurate। Self-practice-এর সব মোড `exam_type='self_practice'` হওয়ায় একই কাউন্টে ধরা হবে।
- **Guest-রা exam attempt দেওয়ার সময় কোনো subscription চেক লাগবে না** — এটা Teacher-এর subscription-এর আওতায় আগেই কভার হয়ে গেছে (exam publish করার সময়েই চেক হয়েছে)।
- Payment gateway callback-এ **idempotency** মাথায় রাখতে হবে — দুইবার callback এলে যেন ডাবল subscription active না হয়।
- **Refund:** শুধু Admin-এর সময়সীমার ভেতরে, আর সেই subscription-এ কিছুই ব্যবহার না হলে (কোনো exam/self-practice/প্রশ্নপত্র গোনা হয়নি — group সদস্যদেরসহ) — তখন পুরো টাকা; ব্যবহার হলে refund নেই, আংশিক refund নেই। নিয়ম শুধু `Payment::isRefundable()`-এ, ব্যবহারের হিসাব `Subscription::usage()`; Admin refund-এর জানালায় সংখ্যাগুলো দেখেন।
- **Referral ও কমিশন:** একজন student একবারে **একজনেরই** referral-এ থাকে, Admin-এর দিন পর্যন্ত (ডিফল্ট ৯০, সেট হওয়ার দিন থেকে), তারপর নিজে থেকে শেষ। Teacher-এর পরীক্ষার লিংক খুললে সাথে সাথে লক; নিজে লেখা কোড Admin-এর সময় পর্যন্ত (ডিফল্ট ১ দিন) বদলানো যায়, সেই সময়ের মধ্যে পরীক্ষার লিংক সেটা বদলে লক করে। নিয়ম শুধু `StudentReferralService`-এ; ছাড়/reward `ReferralService`-এ। কেনার মুহূর্তের referrer payment-এ লেখা থাকে (`payments.referrer_id`) — reward ও রিপোর্ট সেখান থেকে, user-এর এখনকার referrer থেকে না।
- **Teacher ও Student কমিশন পায় শুধু নিজের পেইড subscription চালু থাকলে** (ট্রায়াল/ফ্রি plan-এ না), শুধু নগদে দেওয়া অংশের ওপর, refund-এর সময় পার হলে পাকা; শতাংশ দুটোই Admin-এর সেটিং, কোডে কোনো হার নেই। কমিশন wallet-এ মেয়াদ ছাড়া জমে; role অনুযায়ী Admin-এর reserve সবসময় থাকে — শুধু নিজের subscription-এ খরচ হয়, তোলা যায় না; বাকিটা request করে তোলা যায় (`WithdrawalService` একমাত্র জায়গা, নম্বর `encrypted`, প্রতিটা ধাপ log)। Staff-এর reward payout-এর সাথে নগদে।

### ৮. Exam Sharing (Guest + Login)
> **শেয়ার-লিংকে Student সবসময় দুটো অপশন পাবে: Login করে ঢোকা অথবা Guest হিসেবে (শুধু নাম দিয়ে) ঢোকা — এটা টগল করার কোনো সেটিং নেই, সবসময় দুটোই থাকবে। একমাত্র ব্যতিক্রম School-এর পরীক্ষা: সেখানে দুটোর বদলে স্কুলের ফর্ম (নিয়ম ১২)।**
- `exams.share_token` — random, যথেষ্ট লম্বা (কমপক্ষে ৩২ ক্যারেক্টার), অনুমানযোগ্য না।
- Guest route Filament panel-এর বাইরে, plain Laravel/Livewire route — কারণ unauthenticated (Google OAuth guard-এর সাথে মিশবে না)।
- Guest submission route-এ **rate limiting বাধ্যতামূলক** (spam/multiple-attempt ঠেকাতে)।
- **Teacher-এর exam-এ জমা দেওয়ার পর student শুধু স্কোর দেখে** — প্রশ্ন, অপশন বা সঠিক উত্তর কিছুই না। নইলে খালি খাতা জমা দিয়ে উত্তরপত্র জেনে নিয়ে আবার পরীক্ষা দেওয়া যায় (Guest-এর শুধু আরেকটা নাম লাগে)। Teacher "উত্তর প্রকাশ করুন" চাপলে (`exams.answers_released_at`), অথবা Teacher-এর ঠিক করে দেওয়া সময় এলে (`exams.answers_release_at`) তবেই অপশনসহ সঠিক উত্তর ও নিজের উত্তর দেখা যায়। নির্ধারিত সময়ের জন্য কোনো cron/scheduled job নেই — ফলাফল দেখানোর মুহূর্তে সময়টা মিলিয়ে দেখা হয় (subscription লিমিটের মতোই)। এই সিদ্ধান্ত শুধু `Exam::showsAnswersToStudents()`-এ থাকবে; Self-practice exam-এ উত্তর সাথে সাথেই দেখায় (নিজের exam, ফাঁস হওয়ার কেউ নেই)।
- **Guest-এর পরিচয় = নাম + ফোন/ইমেইল** (`guest_contact` তাই বাধ্যতামূলক, normalize করে স্টোর হয়)। কোনো একাউন্ট না থাকায় Guest পরে শেয়ার-লিংকে ফিরে এই দুটো দিয়েই নিজের ফলাফল খোঁজে (`ExamAttempt::findGuestResult()`) — লিংক বন্ধ হয়ে গেলেও। একই পরিচয়ে একাধিকবার পরীক্ষা দিলে **প্রথম attempt-টাই** দেখানো হয়। এই lookup route-ও rate-limited।

### ৯. Self-Practice Exam — দুই মোডই বাধ্যতামূলক
> **Student self-practice exam বানাতে পারবে দুইভাবে: Auto-Generate (সিস্টেম subject/difficulty/সংখ্যা অনুযায়ী random প্রশ্ন বেছে দেবে) এবং Manual Selection (Student নিজে approved pool থেকে প্রশ্ন বেছে নেবে)। দুটোই থাকতে হবে, একটা বাদ দেওয়া যাবে না।**
- `exams.generation_mode` ফিল্ড (`manual`/`auto`) দিয়ে আলাদা করা হবে, দুটোই `exam_type='self_practice'`।
- Auto মোডের random selection query সবসময় `status='approved' AND is_latest=true` ফিল্টার সহ (approval rule এখানেও প্রযোজ্য)।
- Manual মোড Teacher-এর "প্রশ্ন বাছাই"-এর **একই picker** (`Support\Pages\QuestionPickerPage`): নিজের শ্রেণির বিষয় → অধ্যায় → টপিক (টপিক না বাছলে পুরো অধ্যায়), যত খুশি অধ্যায় থেকে, লক্ষ্য সংখ্যা পর্যন্ত (কম বাছলেও শুরু করা যায়), ফাইনাল ভিউ থেকে "পরীক্ষা শুরু করুন"। Student-এর পরীক্ষা সবসময় অনলাইন — শুধু MCQ, প্রিন্ট নেই; বাছার সময় সঠিক উত্তর দেখায় না। যাচাই `SelfPracticeExamService::selectableQuestions()`-এ।
- দুই মোডের exam-ই plan-এর একই সীমায় (দিনে/মাসে) গোনা হবে, আলাদা limit না; plan যে difficulty দেয় শুধু সেগুলো।

### ১০. Teacher প্রশ্ন বাছাই (Select Questions) → Exam
> **Teacher exam বানায় "প্রশ্ন বাছাই" পেজ থেকে: Class → Subject → Chapter → Topic ফিল্টার করে approved প্রশ্ন টিক দিয়ে বাছে, তারপর ফাইনাল ভিউ থেকে exam হিসেবে সেভ করে। হাজার হাজার Teacher একসাথে এই কাজ করবে — তাই টিক দেওয়ার সময় সার্ভারে কোনো অনুরোধ যাবে না।**

1. **পরীক্ষার ধরন আগে ঠিক হয়** (`exams.delivery_mode`): `online` (শুধু MCQ, শেয়ার-লিংক), `offline` (MCQ/CQ, প্রিন্ট/PDF), `both` (প্রিন্টে সব প্রশ্ন, অনলাইনে শুধু MCQ)। **CQ কখনো অনলাইনে নেওয়া হয় না** — সিস্টেমে CQ-এর উত্তর নেই, তাই উত্তরও দেখানো হয় না।
2. **এক exam = এক Class + Subject** (`exams.class_subject_id`)। একই subject-এর যত খুশি chapter থেকে প্রশ্ন মেশানো যায়; Class বা Subject বদলাতে গেলে সতর্কবার্তা দিয়ে অনুমতি নিয়ে আগের সব বাছাই মুছতে হবে।
3. **Topic ফিল্টার শুধু MCQ-তে খাটে।** CQ topic অনুযায়ী ভাগ হয় না — ধরন CQ হলে ওই chapter-এর সব CQ পেজিনেশনসহ দেখাতে হবে।
4. **কতগুলো প্রশ্ন লাগবে তা Teacher নিজে ঠিক করে** (MCQ ও CQ-র আলাদা লক্ষ্য সংখ্যা); লক্ষ্য পূরণ হলে আর বাছা যায় না। সিস্টেমে কোনো নির্দিষ্ট ব্যবসায়িক সীমা নেই (কিছু পরীক্ষায় ১২০টা প্রশ্ন লাগে) — শুধু `TeacherExamBuilder::MAX_QUESTIONS` নামে একটা কারিগরি সুরক্ষা-সীমা আছে।
5. **বাছাই শুধু ব্রাউজারের `localStorage`-এ থাকে, আর সেখানে শুধু প্রশ্নের ID** (লেখা না)। টিক, গণনা, chapter-ভিত্তিক সারাংশ — সব client-side (Alpine); সার্ভারে যায় শুধু ফিল্টার/পেজ বদলালে আর শেষে সেভ করার সময়। প্রশ্নের তালিকা সবসময় paginated — কখনো পুরো subject/chapter একবারে লোড করা যাবে না।
6. **ব্রাউজারের ডেটা বিশ্বাস করা যাবে না।** সেভের সময় `TeacherExamBuilder` সার্ভারে আবার যাচাই করে: প্রতিটা ID Teacher-এর ব্যবহারযোগ্য (`Question::usableInExamBy()` — approved pool অথবা নিজের pending), একই `class_subject`-এর, আর `online` হলে সব MCQ। এই যাচাই ও exam তৈরির লজিক শুধু ওই service-এ থাকবে।
7. **Subscription লিমিট সেভ করার মুহূর্তে একবারই গোনা হয়** — অনলাইনে প্রকাশ হোক বা শুধু PDF; তখন plan-এ শ্রেণি+বিষয়, পরীক্ষার ধরনের সুবিধা ও প্রশ্নের difficulty থাকতে হয়। আগে থেকে গোনা exam পরে publish/print করতে গেলে শুধু plan-এ সেই সুবিধা আছে কিনা দেখা হয়, আবার গোনা হয় না (`SubscriptionLimitService::allows()`)। Exam তৈরি/এডিটের একমাত্র পথ "প্রশ্ন বাছাই" — Teacher-এর `ExamResource`-এ কোনো create/edit ফর্ম-পেজ রাখা যাবে না।
9. **Exam এডিটও এই পেজ দিয়েই হয়** (`?exam=`; `TeacherExamBuilder::update()`), তৈরি করার মতো একই যাচাইসহ, আর লিমিটে আবার গোনা হয় না। **কোনো student পরীক্ষা শুরু করার পর প্রশ্ন বদলানো যায় না** (`canBeEdited()`) — নইলে তার নম্বর/পজিশন আর প্রশ্নপত্রের সাথে মিলবে না। অন্য ছাত্রদের জন্য একই প্রশ্নে আবার পরীক্ষা নিতে **"আবার পরীক্ষা নিন"** (`ExamRepeater`): নতুন লিংক ও ফলাফলসহ নতুন ড্রাফট পরীক্ষা, পুরনোটা অক্ষত; প্রতিটা প্রশ্নের সর্বশেষ ব্যবহারযোগ্য সংস্করণ (reject/অপেক্ষমাণ বাদ), বিগত সালের পেপার পুরোটা; নতুন পরীক্ষার মতোই plan যাচাই ও সীমায় গোনা। পুরনো খাতা মুছে একই পরীক্ষা বদলাতে হলে একমাত্র পথ **"পরীক্ষা বাতিল"** (`Exam::cancel()`): ওই exam-এর সব attempt ও উত্তর স্থায়ীভাবে মুছে exam-কে draft-এ ফেরায় (লিংক বন্ধ থাকে, যাতে এডিটের মাঝে কেউ শুরু না করে); পরে একই লিংকে আবার publish করা যায়। এটা ফেরানো যায় না, তাই confirmation ও log বাধ্যতামূলক।
10. **নেগেটিভ মার্কিং ও আসন-সীমা:** অনলাইন পরীক্ষায় Teacher সেভের সময় নেগেটিভ মার্কিং চালু করতে পারেন (`exams.negative_marks`, শুরুর মান Admin-এর); ভুলে কাটে, ফাঁকায় না, মোট ০-এর নিচে না — গ্রেডিং শুধু `ExamAttempt::submitAndAutoGrade()`-এ। Plan-এ "প্রতি পরীক্ষায় অংশগ্রহণকারী" থাকলে সেভের সময় `exams.max_participants`-এ কপি হয়; পূর্ণ হলে নতুন কেউ ঢোকে না (`ExamAttempt::enter()`), আগে শুরু করা কেউ ফিরতে পারে।
8. **PDF বানানো হয় ব্রাউজারের Print → "Save as PDF" দিয়ে** (প্রিন্ট-উপযোগী পেজ, "শুধু প্রশ্ন" ও "উত্তরসহ" দুই রূপে) — সার্ভারে PDF বানানো হয় না, কারণ বাংলা যুক্তাক্ষর/গণিতের সূত্র ভাঙে আর সার্ভারে চাপ পড়ে।

### ১১. পারফরম্যান্স বিশ্লেষণ (Student ও Teacher)
> **বিশ্লেষণ দেখায় শুধু পেইড plan-এ যার "পারফরম্যান্স বিশ্লেষণ" সুবিধা আছে (`PlanFeature::Analytics`) — role-এর ফ্রি plan কখনো না; যাচাই শুধু `SubscriptionLimitService::allowsAnalytics()`-এ, পুরো একাউন্টের জন্য (শ্রেণি/বিষয়ে বাঁধা না)। Plan না থাকলে লক করা preview (বানানো সংখ্যা, কারো ডেটা পড়া হয় না)। আগের ফ্রি জিনিস ("দুর্বল অধ্যায়", ভুলের অনুশীলন, ফলাফল) ফ্রিই।**

1. **স্কোর** = `total_score / total_marks`; স্কোরের ট্রেন্ড ও মাসের গড় থেকে "আমার ভুলগুলো অনুশীলন" বাদ (আগের ভুল দিয়ে বানানো, কম স্কোর অবনতি না)।
2. **প্রশ্নভিত্তিক হার** (বিষয়, অধ্যায়, কঠিনতা, কভারেজ) — প্রতিটা MCQ-এর **সর্বশেষ উত্তর**টাই, সংস্করণ মিলিয়ে; ফাঁকা = সঠিক না; সব ধরনের খাতা আসে। Admin-এর ন্যূনতম প্রশ্নের কম হলে শতাংশ না, "আরও ডেটা দরকার"। বিগত সালের পেপার স্কোর ও বিষয়ে আসে, অধ্যায়/কঠিনতা/কভারেজে কখনো না (নিয়ম ৫.৪)।
3. **উত্তর ফাঁস:** Student-এর নিজের হিসাবে Teacher-এর পরীক্ষার স্কোর সাথে সাথে, কিন্তু প্রশ্নভিত্তিক হিসাবে শুধু উত্তর প্রকাশের পর (`Exam::whereShowsAnswersToStudents()` — `showsAnswersToStudents()`-এর SQL রূপ, একমাত্র জায়গা)।
4. **Teacher শুধু নিজের পরীক্ষার ডেটা দেখেন** — student-এর self-practice বা অন্য Teacher-এর পরীক্ষা কখনো না; শুধু সেই student-এর বিস্তারিত, যে তাঁর কোনো পরীক্ষা দিয়েছে (`TeacherClassPerformance::hasStudent()`)। ক্লাসের হিসাবে (ট্রেন্ড, দুর্বল অধ্যায়) Guest গোনা হয়; student-ভিত্তিক হিসাবে (তালিকা, "মনোযোগ দরকার", বিস্তারিত) শুধু লগইন করা student — Guest-এর পরিচয় নির্ভরযোগ্যভাবে জোড়া লাগানো যায় না।
5. হিসাব শুধু `StudentPerformance` (কার খাতা — `PerformanceScope`) ও `TeacherClassPerformance`-এ; সংখ্যাগুলো `BillingSetting.performance_*`।

### ১২. School একাউন্ট ও School Management Software-এর সংযোগ
> **প্রতিটা স্কুলের একটাই School একাউন্ট (স্কুলের Google একাউন্ট, কম্পিউটার অপারেটর চালান; `/school` প্যানেল, Admin approve করলে চালু)। শুধু এই একাউন্টই স্কুলের জন্য প্রশ্ন বানায়, স্কুলের ছাত্রদের অনলাইন পরীক্ষা নেয় আর রেজাল্ট স্কুলের software-এ পাঠায় — স্কুলের কোনো Teacher নিজের একাউন্টে এগুলো পারেন না। স্কুলের Teacher ও Student আগের মতোই নিজের Google একাউন্টে, নিজের subscription-এ।**

1. **সংযোগ:** School একাউন্টের "স্কুল সফটওয়্যারের সংযোগ" পেজে Question Bank-এর ঠিকানা, সংযোগ কোড ও secret; স্কুলের software-এর Admin সেটিংসে বসিয়ে "সেভ করে যুক্ত করুন" চাপেন। দুই দিকের প্রতিটা অনুরোধ secret দিয়ে সই (`{timestamp}.{body}`-এর HMAC-SHA256, ৫ মিনিটের বেশি পুরনো না; secret কখনো পাঠানো হয় না) — Question Bank-এ `SchoolConnectionSignature` + `AuthenticateSchoolInstallation`, স্কুলের দিকে `HmacSignature`। Secret `encrypted`; নতুন secret বানালে সংযোগ বন্ধ যতক্ষণ না নতুনটা বসানো হয়। Approve-না-হওয়া বা suspended School-এর অনুরোধ নেওয়া হয় না।
2. **লিংক:** স্কুলের software-এর Admin প্যানেলে Question Bank-এর `/school`, Teacher প্যানেলে `/teacher`, Student প্যানেলে `/student`-এর লিংক (সংযুক্ত হলেই) — লগইন সবাই নিজের Google একাউন্টে।
3. School plan আলাদা (`SubscriptionTargetRole::School`); School referral/wallet-এ নেই। স্কুলের পরীক্ষায় ছাত্রের subscription লাগে না।
4. **স্কুলের পরীক্ষা:** Teacher-এর একই পেজ দিয়ে ("প্রশ্ন বাছাই", পরীক্ষার তালিকা, ফলাফল, প্রিন্ট — School প্যানেলে একই ক্লাস, প্যানেল অনুযায়ী লিংক `CurrentPanel`), তিন ধরন (অনলাইন/প্রিন্ট/দুটোই)। লিংকে Login/Guest-এর বদলে স্কুলের ফর্ম: ক্লাস → সেকশন (ক্লাসে থাকলে, তখন বাধ্যতামূলক) → রোল → নাম — ক্লাস/সেকশন স্কুলের software থেকে (`SchoolRoster`, কয়েক মিনিট পরপর আনা, শেষ তালিকা রাখা থাকে)। পরিচয় = ক্লাস + সেকশন + রোল + নাম (`ExamAttempt::startForSchoolStudent()`; রোল এক রূপে — বাংলা অঙ্ক, শুরুর শূন্য বাদ); একই রোলে অন্য নাম আলাদা খাতা পায়। Account বা subscription লাগে না; পরীক্ষার পাতা Guest-এরটাই। ফলাফল খোঁজা একই লিংকে ক্লাস + সেকশন + রোল দিয়ে (প্রথম জমা দেওয়া খাতা)।
5. **রেজাল্টে পাঠানো** (`School\Pages\SendToSchoolResults`; মেলানো `SchoolResultMatcher`, পাঠানো ও log `SchoolResultPusher` + `school_result_pushes`): শুধু অনলাইন MCQ-এর নম্বর, School নিজে বাটন চেপে, স্কুলের কোন পরীক্ষা ও বিষয় বেছে। মেলানো ক্লাস + সেকশন + রোল দিয়ে বর্তমান সেশনের তালিকার সাথে; নাম না মিললে দুটো নাম পাশাপাশি — School approve করে; রোল না পেলে পাঠানো হয় না; এক রোলে একাধিক খাতা হলে School একটা বাছে; খাতা না থাকলে "অনুপস্থিত" (`is_absent`)। পূর্ণমান না মিললে পপআপ: স্কুলের software-এ পূর্ণমান ঠিক করুন, অথবা অনুপাতে বসান (আগে পূর্বরূপ)। আগে থেকে নম্বর থাকলে জিজ্ঞেস করে; রেজাল্ট প্রকাশ হয়ে গেলে কিছু বদলায় না; একই অনুরোধ দুইবার এলে একবারই বসে; লিখিত/ব্যবহারিক নম্বর থাকলে অনুপস্থিত দাগানো হয় না; প্রতিটা পাঠানোর log।
6. **স্কুলের নিজের প্রশ্ন:** School-এর লেখা প্রশ্ন ("আমার প্রশ্ন", Teacher-এর একই পেজ) নিজে থেকেই `questions.is_private` — শুধু ওই School দেখে ও নিজের পরীক্ষায় দেয়; সাধারণ pool-এ যায় না, Admin-এর তালিকা/রিভিউ/ডুপ্লিকেট-যাচাইয়ে আসে না, কেউ approve/reject করে না (`QuestionPolicy`, `Question::approve()`; status সবসময় `pending`)। ছাত্ররা পরীক্ষা দেওয়ার পর এডিট করলে নতুন সংস্করণ (`Question::editsMakeNewVersion()`), দেওয়া পরীক্ষায় পুরনোটাই থাকে। ওই স্কুলের ছাত্ররা স্কুলের software-এর Student প্যানেলে দেখে (যেকোনো ক্লাসের, যেকোনো সালের, উত্তর ও ব্যাখ্যাসহ) — প্রশ্নটা যে পরীক্ষাগুলোয় আছে তার সব শেষ হওয়ার পরের দিন থেকে; কোনো পরীক্ষায় শেষ সময় না থাকলে (বা কোনো পরীক্ষায় না থাকলে) প্রথম সংস্করণ লেখার Admin-এর দিন (`BillingSetting.school_question_release_days`, ডিফল্ট ৬০) পরে — সব সংস্করণের পরীক্ষা মিলিয়ে, কোনো job ছাড়া দেখার মুহূর্তে হিসাব (`Question::scopeReleasedToSchoolStudents()`)। স্কুলের software অল্প সময়ের সই করা লিংক বানায়, Question Bank-এর পেজ (`SchoolQuestionsController`, `SchoolQuestionLibrary`) সেটা iframe-এ দেখায় — Student-এর আলাদা লগইন লাগে না।

### ১৩. App (PWA), অফার ও Push নোটিফিকেশন
> **সবার জন্য একটাই install করা যায় এমন app — নাম "Next Fusion" (শুধু app-এ; সফটওয়্যারের বাকি জায়গায় নাম আপাতত বদলায়নি)। Admin-এর অফার হোম পেজে সবাই আর ড্যাশবোর্ডের ব্যানারে যাদের জন্য তারা দেখে; বেলে ও ফোনে push যায় শুধু Admin "পাঠান" চাপলে, একবারই।**

1. **App:** manifest `config/pwa.php` থেকে (`PwaController`), খোলে `/app`-এ — লগইন থাকলে নিজের প্যানেলের ড্যাশবোর্ড, না থাকলে প্যানেল বাছাই (`<x-panel-choices>`) ও চালু অফার। Icon-এর লিংক শুধু `config/pwa.php`-এ। Service worker (`public/sw.js`) শুধু push দেখায়, কিছু cache করে না।
2. **অফার কাদের জন্য:** Student / Teacher / School / Staff (এক বা একাধিক); Student হলে চাইলে নির্দিষ্ট শ্রেণি (ফাঁকা = সব)। চালু = `is_active` আর শুরু-শেষের সময়ের ভেতরে — কোনো job নেই। কার ব্যানারে, কাদের কাছে পাঠানো — সব শুধু `Offer`-এ (`scopeLive()`, `scopeInBannerOf()`, `audience()`)। ব্যানার Admin ছাড়া সব প্যানেলের ড্যাশবোর্ডে; user বন্ধ করলে তার কাছে আর আসে না।
3. **পাঠানো:** শুধু চালু অফার, একবারই (`offers.notified_at`), Admin বাছেন বেলে / push / দুটোই; পাঠানো চলে উত্তর দেওয়ার পরে, ভাগে ভাগে, queue worker ছাড়া — নিয়ম শুধু `OfferNotifier`-এ, প্রতিটা পাঠানো log হয়।
4. **Push চালু:** ব্রাউজার অনুমতি চায় শুধু user "চালু করুন" চাপলে (`<x-push-prompt>`); ডিভাইস `push_subscriptions`-এ। VAPID key `.env`-এ, একবার বানিয়ে আর বদলানো যাবে না।

### ১৪. গাইড (সাহায্য)
> **গাইড শুধু প্যানেলের ভেতরে, লেখেন Admin (বাংলা ও ইংরেজি, ছবি, ঐচ্ছিক YouTube ভিডিও), দেখান দর্শকের ভাষায় — ইংরেজি না থাকলে বাংলা। প্রতিটা গাইড নির্দিষ্ট role-এর জন্য; অন্য role কখনো দেখে না।**

1. **"সাহায্য" পেজ** Student/Teacher/Staff/School প্যানেলে (`HelpCenter`) — বিভাগ অনুযায়ী, খোঁজা যায়। Admin-এর প্যানেলে নেই।
2. **"কীভাবে করবেন?" বাটন:** কোন পেজে থাকতে পারে তা কোডে স্থির (`GuidePage`); কোন গাইড খুলবে Admin গাইডেই বাছেন; দর্শকের role-এর গাইড না থাকলে বাটন নেই।
3. **"শুরু করুন" তালিকা:** role-অনুযায়ী ধাপ কোডে স্থির, user-এর আসল কাজ দেখে নিজে টিক পড়ে (কিছু রাখা হয় না) — শুধু `OnboardingChecklist`-এ; সব শেষে বা user বন্ধ করলে চলে যায়; Admin সবার জন্য বন্ধ রাখতে পারেন (`BillingSetting.onboarding_checklist_enabled`)।
4. কে কোন গাইড দেখে ও কোন ভাষায় — শুধু `Guide`-এ (`scopeFor()`, `scopeOnPage()`, `title()`, `bodyHtml()`)।

## Roles & Permissions (সংক্ষেপে)

| Role | পারে | পারে না |
|---|---|---|
| Super Admin / Admin | সব প্রশ্ন/বোর্ড-পেপার দেখা/approve/reject, নতুন Admin ও Staff approve, Staff payout, Subscription plan ম্যানেজ | Student হিসেবে exam দেওয়া, Google flow দিয়ে সাইনআপ |
| Teacher | নিজের প্রশ্ন CRUD ("আমার প্রশ্ন"), approved pool থেকে প্রশ্ন বাছা ("প্রশ্ন বাছাই"), exam তৈরি/শেয়ার (subscription-গেটেড) | Staff earning দেখা, অন্যের pending প্রশ্ন দেখা |
| Staff | নিজের প্রশ্ন CRUD (pending/rejected), নিজের earning দেখা — Admin approve করার আগে **কিছুই না** (`status=pending`); `suspended` হলে Dashboard/Earning দেখা যায় কিন্তু প্রশ্ন add করা যায় না | exam তৈরি করা, অন্যের প্রশ্ন দেখা, approved pool ব্রাউজ করা, `permanent_suspend` হলে প্যানেলে ঢোকা |
| Student (Login) | নিজের attempt/result, নিজের subscription দিয়ে self-practice exam | প্রশ্ন দেখা exam-এর বাইরে, অন্যের result দেখা |
| Student (Guest) | শুধু নির্দিষ্ট share-link-এর exam attempt দেওয়া | self-practice exam, লগইন-নির্ভর যেকোনো ফিচার |
| School | নিজের স্কুলের জন্য প্রশ্ন, স্কুলের ছাত্রদের পরীক্ষা, রেজাল্ট স্কুলের software-এ পাঠানো, সংযোগ (নিয়ম ১২) — Admin approve করার আগে **কিছুই না** | referral/wallet, অন্য স্কুলের কিছু দেখা |

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
docs/                              ← পুরো ডকুমেন্টেশন (আসল ফোল্ডার কাঠামো docs/02-architecture.md-এ)
CLAUDE.md
```

## Coding Conventions

### ডকুমেন্টেশন হালনাগাদ রাখা (বাধ্যতামূলক)

> **কোনো ফিচার বা আচরণ বদলালে একই কাজের অংশ হিসেবে `docs/` হালনাগাদ করতে হবে — কাজ শেষ বলার আগে।**

1. সংশ্লিষ্ট `docs/` ফাইলটা নতুন আচরণ অনুযায়ী বদলান (কোন ফাইল কোন বিষয়ের, `docs/README.md`-এর সূচিতে)।
2. `docs/CHANGELOG.md`-এর ওপরে আজকের তারিখে এক-দুই লাইনে লিখুন কী বদলেছে।
3. নতুন টেবিল/কলাম হলে `docs/03-database.md`, নতুন Admin সেটিং হলে `docs/09-admin-settings.md`-ও।
4. ব্যবসায়িক নিয়মের একমাত্র উৎস এই ফাইল (CLAUDE.md); `docs/` নিয়ম ব্যাখ্যা করে ও কোডের সাথে মেলায়। নিয়ম বদলালে আগে এখানে, তারপর `docs/`-এ।
5. শুধু ভেতরের refactor (আচরণ অপরিবর্তিত) বা টেস্ট যোগ করলে `docs/` বদলানোর দরকার নেই, যদি না কোনো ফাইলের পথ বদলায়।

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
| উদ্দীপকভিত্তিক MCQ: সাজানো, নম্বর, "… নম্বর প্রশ্নের উত্তর দাও" শিরোনাম, পরীক্ষায় এক ব্লকে এলোমেলো | `Support\QuestionBlocks` (`units()`, `arrange()`, `blocks()`, `heading()`); দেখানো `<x-question-stimulus>` |
| উদ্দীপক + তার MCQ লেখার ফর্ম ও বাটন (প্রশ্নভাণ্ডার ও প্রশ্নপত্র) | `Support\StimulusGroupForm`; প্রশ্নভাণ্ডারের বাটন `Support\StimulusGroupActions`, সেভ ও সংস্করণ `App\Services\StimulusGroupService`; প্রশ্নপত্রে `PaperMcqQuestionsRelationManager`; গ্রুপের আকার `QuestionStimulus::MIN_QUESTIONS`/`MAX_QUESTIONS` |
| JSON-এর প্রশ্ন বানানো ও সেভ (উদ্দীপক আগে, ডুপ্লিকেট বাদ, ছবির নাম যাচাই) — দুই importer-এর অভিন্ন অংশ | `App\Services\ImportedQuestions` |
| JSON-এর সাথে দেওয়া ছবি (ZIP/ফাইল খোলা, যাচাই, সীমা, অস্থায়ী রাখা, সেভের সময় কপি, না-নেওয়া ছবির তালিকা) | `App\Services\ImportedImages` (একমাত্র জায়গা); সীমা `BillingSetting.question_import_image_max_kb` / `question_import_images_max_mb` |
| ডুপ্লিকেট প্রশ্ন: হুবহু/সম্ভাব্য বিচার; ফর্মের "তবুও সেভ করুন"; কার সাথে মেলানো হবে; দেখানো | `App\Services\DuplicateQuestionFinder` (+ `DuplicateMatch`); ফর্ম ও approve `Support\DuplicateQuestionCheck`; অধ্যায়ের প্রশ্ন `Question::scopeDuplicateCandidatesIn()`, পেপারের `BoardQuestionPaper::duplicateCandidates()`; `<x-question-duplicate>` |
| পেছনের (queue) ডুপ্লিকেট যাচাই: কখন, কার সাথে, ফল রাখা; Admin-এর রাখা/মোছা; আগে থেকে থাকা প্রশ্নে খোঁজা | `App\Services\QuestionDuplicateScreening` (+ job `ScreenQuestionForDuplicates`, `ScreenChapterForDuplicates`, command `questions:screen-duplicates`); candidate `Question::scopeDuplicateScreeningCandidatesFor()` (fingerprint `DuplicateQuestionFinder::fingerprint()`/`sizeRange()`); পেজ `Filament\Pages\DuplicateQuestions`; queue চালায় সার্ভারের cron-এর `flock` + `queue:work` (`docs/10-testing-and-deploy.md`) |
| প্রশ্ন লেখার ফর্মের অংশ (এডিটর বাছাই + লেখা + ছবি + preview, MCQ অপশন ও ব্যাখ্যা, CQ অংশ) — প্রশ্নভাণ্ডার ও বিগত সালের প্রশ্নপত্র দুটোতেই | `Support\QuestionFormSchema::textImageAndPreview()`, `editorModeRadio()`, `mcqComponents()`, `cqComponents()`; পেপারের জন্য `Support\PaperQuestionForms` |
| অপশন/CQ অংশ ফর্ম ↔ ডেটাবেজে রূপান্তর | `Support\QuestionFormData`; এডিটর পছন্দ মনে রাখা `EditorModePreference::rememberFromForm()` |
| Admin কি না | `UserRole::isAdmin()` |
| বিগত সালের প্রশ্নপত্র: Admin/Staff resource, প্রশ্নের তালিকা, Teacher/Student পেজ, পরীক্ষা বানানো | `Support\Resources\BoardQuestionPaperResourceBase`, `Support\RelationManagers\PaperMcqQuestionsRelationManager` / `PaperCqQuestionsRelationManager`, `Support\Pages\PastPapersPage`, `App\Services\PastPaperExamService` |
| পরীক্ষায় যা বসা হয় / প্রিন্ট হয় (প্রশ্নভাণ্ডারের বা প্রশ্নপত্রের) | `Exam::onlineItems()`, `printedItems()`, `answerColumn()`, `heading()`; `ExamAttempt::savedAnswers()`, `answersByQuestion()` |
| প্রশ্ন কোথা থেকে এসেছে / ব্যাখ্যা দেখানো | `<x-question-source>`, `<x-question-explanation>` |
| Class → Subject → Chapter → Topic select | `Support\ContentHierarchySchema` (`classSelect()` … `topicSelect()`; শ্রেণি/বিষয় সীমিত করতে ids-এর closure দেওয়া যায়) |
| শ্রেণি, একটা শ্রেণির বিষয় বা একটা অধ্যায়ের টপিকের তালিকা (যেকোনো select/ফিল্টারে) | `AcademicClass::options()`, `ClassSubject::subjectOptionsFor()`, `Topic::optionsFor()` |
| সাধারণ (Filament-টেবিল নয় এমন) টেবিল | `<x-data-table :columns>` — স্টাইল `qb-data-table` (question-display.css) |
| রেফারেল পুরস্কারের তালিকা | `<x-referral-rewards-table>`, `User::recentReferralRewards()` |
| Service কিছু আটকালে লাল notification (try/catch লিখবেন না) | `Support\Concerns\NotifiesValidationFailures::rescueWithNotification()` |
| বেলে নোটিফিকেশন পাঠানো (কাকে, প্রাপকের ভাষায়, queue ছাড়া) — নতুন যেকোনো নোটিফিকেশনও এখান দিয়ে | `App\Services\BellNotifier` (`toUser()`, `toAdmins()`); কোন Admin-রা পাবেন `AdminNotificationTopic` + `BillingSetting::adminsToNotify()`; কেনার লেখা `App\Services\PurchaseNotifications`; প্যানেলের ভাষা `UserRole::defaultLocale()`; Admin-রা `User::scopeAdmins()` |
| MCQ অপশনের ফিল্ড + "একটাই সঠিক উত্তর" নিয়ম | `Support\McqOptionsSchema` |
| প্রশ্নের ফর্ম / টেবিল / View | `Support\QuestionFormSchema`, `QuestionsTable`, `QuestionInfolist` |
| JSON পেস্ট করে একসাথে অনেক প্রশ্ন যোগ — অধ্যায়ে (Teacher/Admin; Staff না) ও বিগত সালের প্রশ্নপত্রে (Admin; Staff শুধু Admin চালু করলে — `BillingSetting.staff_paper_import_enabled`) | JSON পড়া, যাচাই ও format/sample-এর একমাত্র জায়গা `App\Services\QuestionJsonReader` (+ `QuestionImportText`, গণিত `$...$` → KaTeX embed); প্রশ্ন বানায় `QuestionJsonImporter` (অধ্যায়) ও `PastPaperJsonImporter` (প্রশ্নপত্র); বাটন ও পপআপ `Support\QuestionJsonImportAction` (`make()`, `forPastPaper()`); অনুমতি `QuestionPolicy::import()`, `BoardQuestionPaperPolicy::import()`; একবারের সীমা `BillingSetting.question_import_max` (দুই জায়গাতেই) |
| MCQ-র difficulty student-দের উত্তর থেকে নিজে ঠিক হওয়া (শুধু Teacher-এর পরীক্ষা, ফাঁকা = ভুল; গণনা প্রশ্নের নিজের `answer_count`/`correct_answer_count`-এ, তাই পুরনো attempt মুছলেও থাকে; "পরীক্ষা বাতিল"-এ গণনা কমে) | `App\Services\QuestionDifficultyTracker` (গণনা ও নিয়মের একমাত্র জায়গা); সংখ্যাগুলো `BillingSetting.difficulty_*`; `Question::hasCalculatedDifficulty()` |
| Student-এর ভুলের তালিকা ও "ভুলগুলো অনুশীলন" (যেকোনো পরীক্ষার ভুল/ফাঁকা MCQ জমে; পরপর `BillingSetting.mistake_clear_streak` বার সঠিক হলে বাদ; **Teacher-এর পরীক্ষার ভুল সেই পরীক্ষার উত্তর প্রকাশের আগে অনুশীলনে আসে না** — নইলে উত্তর ফাঁস হয়) | `App\Services\StudentMistakeTracker` (একমাত্র জায়গা); পরীক্ষা বানায় `SelfPracticeExamService::generateFromMistakes()` (`generation_mode='mistakes'`, একই মাসিক লিমিটে গোনা); পেজ `Student\Pages\PractiseMistakes`, widget `StudentWeakChaptersWidget` |
| Teacher-এর নিজের ছাত্ররা কোন প্রশ্নে কতবার ভুল করেছে (তার সব পরীক্ষা মিলিয়ে) ও "প্রশ্ন বাছাই"-এ সেই ফিল্টার | `App\Services\TeacherQuestionStats` |
| খাতা জমার পর উত্তর থেকে যা যা হিসাব রাখা হয় তার প্রবেশপথ (একই খাতা দুইবার গোনা আটকায়) | `ExamAttempt::recordStatistics()` — difficulty আলাদাভাবে `QuestionDifficultyTracker`-এ |
| টেবিলের edit/delete/bulk-delete অ্যাকশন | `Support\TableActions` |
| ফর্মে "কোন user" সিলেক্ট | `Support\UserSelect` |
| নাম + ক্রমসহ আইটেম (class/chapter/topic) যোগ-এডিট-ডিলিট | `Support\Concerns\ManagesOrderedItems` |
| প্রশ্নের তালিকা কার্ড আকারে (ডিফল্ট) ↔ টেবিল আকারে টগল (`?view=table`) | `Support\Concerns\SwitchesCardAndTableView` (অধ্যায়ের প্রশ্ন-তালিকা, "সম্ভাব্য ডুপ্লিকেট", প্রশ্নপত্রের MCQ/CQ তালিকা) |
| প্রশ্নের তালিকার ক্রম ID অনুযায়ী — "Oldest" (ডিফল্ট) / "Latest" বোতাম (`?order=latest`) | `Support\Concerns\OrdersOldestOrLatest` (অধ্যায়ের প্রশ্ন-তালিকা, "প্রশ্ন বাছাই" picker) |
| Browse পেজ (class/subject/chapter) ও কার্ড; ব্রাউজারে কার্ড খোঁজা | `Support\Pages\Questions\Browse*Page`, `<x-browse.grid>` (`searchable`), `<x-browse.card>` (`search`), `<x-browse.meta>`, `<x-browse.edit-delete-menu>` |
| বিষয়ের নাম চলতি ভাষায় | `Subject::display_name` (সংক্ষিপ্ত `short_label`) |
| কার্ডের রঙের palette | `Support\CardPalette` |
| Dashboard-এর stat কার্ড, "Recent questions" | `Support\Widgets\DashboardStat`, `RecentQuestionsWidget` |
| Self-practice পেজ (নিজের শ্রেণির বিষয় + শুরু) | `Support\Pages\SelfPracticeExamPage` |
| প্রশ্ন বাছাইয়ের picker (Teacher-এর "প্রশ্ন বাছাই" ও Student-এর "নিজের প্র্যাকটিস পরীক্ষা বানান"): ফিল্টার, তালিকা, ব্রাউজারে টিক, এলোমেলো, ফাইনাল ভিউ | `Support\Pages\QuestionPickerPage` + `filament.support.pages.question-picker` (partial `question-picker/selection-summary`, `question-picker/question-card`); প্যানেলের পেজে শুধু কোন প্রশ্ন, কোন শ্রেণি, প্রিন্ট আছে কি না আর শেষ ধাপ |
| Plan-এর শর্ত পড়া, কে কী পারে, কোন plan-এ গোনা হবে | `App\Services\PlanConditions`, `PlanGrant`, `SubscriptionLimitService` (`authorize()`, `allows()`, `allowedDifficultiesFor()`, `constrainToAccessible()`) |
| "আমার plan" (প্রতিটা plan, তার শর্ত ও আজ/এই মাসের ব্যবহার) | `<x-plan-usage>`, `Support\Concerns\ShowsPlanSummary` |
| টেস্টে কাউকে শর্তহীন plan দেওয়া | `subscribeToEverything()` (`tests/Pest.php`) |
| পারফরম্যান্স বিশ্লেষণ: কে দেখে, Student-এর হিসাব, ক্লাসের হিসাব, একটা দলের হার | `SubscriptionLimitService::allowsAnalytics()`; `App\Services\StudentPerformance` + `PerformanceScope` (`ownView()`, `teacherView()`) + `App\Enums\PerformanceSource`; `App\Services\TeacherClassPerformance`; `App\Services\PerformanceRate` |
| বিশ্লেষণের widget (দুই প্যানেলের অভিন্ন), লক করা preview, ফিল্টারওয়ালা dashboard | `Support\Widgets\Performance\*` (`Concerns\ReadsStudentPerformance`, `NeedsAnalyticsPlan`), `AnalyticsLockedWidget`; `Support\Pages\FilteredDashboard` |
| খাতার স্কোর %-এ, জমা পড়া খাতার status (raw SQL-এ) | `ExamAttempt::SCORE_PERCENT_SQL`, `ExamAttempt::submittedStatusValues()` |
| "Class 10 · Physics" লেবেল | `ClassSubject::label()` |
| Student কার referral-এ, কত দিন (শুরু/বদল/লক) | `App\Services\StudentReferralService`, `Models\StudentReferral` |
| Teacher/Student-এর কমিশন তোলা (reserve, request, paid/reject) | `App\Services\WithdrawalService`; পেজ `Support\Pages\ReferralsAndCommissionPage` (দুই প্যানেলে), Admin `WithdrawalResource` |
| Referrer-কে student-এর ঢাকা email দেখানো | `User::maskedEmail()` |
| Group plan-এর সদস্য | `App\Services\SubscriptionMemberService`, `Models\SubscriptionMember` |
| ডিভাইস সীমা ও প্রোফাইলের ডিভাইস-তালিকা | `App\Services\DeviceSessionService` |
| পরীক্ষা পূর্ণ কি না | `Exam::isFull()`; ভুল উত্তরে কত কাটা যায় `Exam::marksLostPerWrongAnswer()` |
| Staff-এর প্রশ্ন breakdown পেজ | `Support\Pages\QuestionStatusBreakdownPage` |
| approve/reject-এর status বদল | `Models\Concerns\HasApprovalStatus` |
| Admin approve করে এমন একাউন্ট (Staff, School): অপেক্ষার পেজ, middleware, approve/suspend বাটন | `Support\Pages\PendingApprovalPage`, `EnsureAccountIsApproved`, `User::isAwaitingApproval()`, `StaffLikeUserResourceSchema::statusActions()` |
| School-এর তথ্যের ফর্ম; সংযোগ কোড/secret; সই করা অনুরোধ | `Support\SchoolDetailsForm`; `Models\SchoolProfile` (`for()`, `markConnected()`, `regenerateSecret()`); `App\Support\SchoolConnectionSignature`, `AuthenticateSchoolInstallation` |
| School-এর নিজের প্রশ্ন: ছাত্ররা কবে দেখে, তাদের পেজ, এডিটে নতুন সংস্করণ কখন | `Question::scopeReleasedToSchoolStudents()`, `App\Services\SchoolQuestionLibrary`, `SchoolQuestionsController` (লিংক `SchoolConnectionSignature::verifyLink()`); `Question::editsMakeNewVersion()` |
| App: manifest, `/app`, `<head>`-এর অংশ, প্যানেল বাছাইয়ের কার্ড | `PwaController`, `config/pwa.php`, `<x-pwa-head>`, `<x-panel-choices>` |
| অফার: চালু কি না, কার ব্যানারে, কাদের কাছে; পাঠানো; কার্ড ও ব্যানার; push চালু | `Models\Offer`, `App\Services\OfferNotifier` (+ `OfferPushNotification`), `<x-offer-card>`, `filament.support.offer-banner`, `<x-push-prompt>` |
| গাইড: কে দেখে, ভাষা, ভিডিও; "সাহায্য" পেজ; পেজের "কীভাবে করবেন?"; "শুরু করুন" | `Models\Guide`, `Enums\GuidePage`, `Support\Pages\HelpCenter`, `<x-guides.content>`, `<x-guides.page-help>`, `App\Services\OnboardingChecklist` |
| Admin-এর প্রশ্ন রিভিউ: সারির, একসাথে অনেক (ডুপ্লিকেট বাদ), "দেখুন" জানালা থেকে ও পরের pending প্রশ্ন | `Support\QuestionReviewActions` |
| শেয়ার-লিংকের exam খোঁজা, লিংক সচল কি না | `Exam::findPublishedByShareToken()`, `isAcceptingAttempts()` |
| একটা শ্রেণির subject-এর approved প্রশ্ন | `Question::scopeOfClassSubject()`, `approvedOptionsForClassSubject()` |
| পড়ার জন্য প্রশ্ন দেখানো (অপশন/CQ অংশ) | `filament.support.questions.question-body`, `Support\QuestionDisplay` |
| প্রশ্ন/উদ্দীপক/অপশন/CQ অংশের ছবি (সব জায়গায়, সীমিত আকারে) | `<x-question-image :path>` (`option` দিলে ছোট) |
| পড়ার জন্য প্রশ্নের তালিকা (MCQ উদ্দীপকসহ, তারপর CQ, উত্তর ও ব্যাখ্যাসহ) — প্রশ্নপত্র পড়া, Teacher-এর পরীক্ষা দেখা | `filament.support.questions.question-list`; Teacher-এর বাটন `ExamsTable::viewQuestionsAction()` |
| MCQ-র উত্তর কী হিসেবে জমা হয় ও কোনটা সঠিক (শুধু-ছবির অপশনও) — খাতা, গ্রেডিং, ফলাফল | `Models\Concerns\HasMcqOptions` (`answerValueOf()`, `correctAnswerValue()`) |
| পরীক্ষার খাতা (শিরোনাম + নাম + ঘড়ি + প্রশ্ন + সতর্কবার্তা) — Guest ও লগইন করা student হুবহু একই দেখে | `<x-exam-paper>`, `<x-exam-heading>`, `<x-exam-participant>`, `<x-exam-timer>`, `<x-exam-submit-warning>`, `resources/js/exam-timer.js` |
| ফলাফল | `<x-exam-result>`, `<x-exam-result-question>` |
| পরীক্ষার ফলাফল-তালিকা ও পজিশন (Teacher) | `App\Services\ExamResultSheet` (র‍্যাংকিং-এর একমাত্র জায়গা), `teacher.partials.result-sheet-table` |
| Guest-এর নাম + ফোন/ইমেইল ফিল্ড | `<x-guest-identity-fields>` |
| টেবিলে তারিখ/সময় (অঙ্কসহ পুরোটা দর্শকের ভাষায় — বাংলায় "অক্টো ২০, ২০২৬") — `date()`/`dateTime()` না | `TextColumn`-এ `->localDate()` / `->localDateTime()` (`AppServiceProvider`); অঙ্ক বদলানো `Locale::current()->writeDigits()` |
| ভাষা বদলানোর বাটন (সব জায়গায়) | `<x-language-switcher variant="light|dark|panel">` |
| গণিতের সূত্র রেন্ডার (দুই JS bundle-এই) | `resources/js/katex-embeds.js` |

### অন্যান্য

- **Filament v5 কনভেনশন মেনে চলুন** — v3-এর পুরনো Form/Table syntax v4/v5-এ কাজ নাও করতে পারে; কোড লেখার আগে official v5 docs চেক করুন।
- প্রতিটা নতুন Resource/Page লেখার আগে কোন role/panel-এর জন্য তা ঠিক করে সেই অনুযায়ী middleware/policy লাগান।
- **DEV-LOGIN-BYPASS (শুধু `APP_ENV=local`, অস্থায়ী):** ব্রাউজারে পরিবর্তন যাচাইয়ের জন্য `/dev-login/{user}` Google ছাড়া লগইন করায় — নিয়ম ১-এর একমাত্র ব্যতিক্রম, লাইভে রুটটার অস্তিত্বই নেই। এর সব অংশের তালিকা ও মুছে ফেলার ধাপ এক ফাইলে: `DEV-LOGIN-BYPASS.md` (রিপোজিটরির মূলে) — user "বাইপাস মুছে দাও" বললে সেই তালিকা ধরে সব মুছতে হবে। বাইপাসে নতুন কিছু যোগ হলে ওই তালিকাতেও যোগ করতে হবে। লাইভের `.env`-এ `APP_ENV` কখনো `local` রাখা যাবে না।
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
9. **Subscription limit:** plan-এর দিনের/মাসের সীমা শেষ হলে নতুন exam/practice-exam তৈরি ব্লক হয়; পরের দিন/মাসে আবার রিসেট হয় (কোনো cron/reset job ছাড়াই)। Plan-এর বাইরের শ্রেণি/বিষয়/difficulty/সুবিধা ব্লক হয়; plan না থাকলে সব ব্লক।
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
