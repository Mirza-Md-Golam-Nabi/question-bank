<?php

use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\File;

use function Pest\Livewire\livewire;

it('stores the chosen language and returns to the previous page', function () {
    $this->from('/')
        ->get(route('locale.switch', ['locale' => 'bn']))
        ->assertRedirect('/')
        ->assertSessionHas(SetLocale::SESSION_KEY, 'bn');
});

it('rejects a language the platform does not support', function () {
    $this->get('/locale/fr')->assertNotFound();
});

it('shows the home page in english by default and in bangla once switched', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Which panel do you want to sign in to?')
        ->assertSee(route('locale.switch', ['locale' => 'bn']), false);

    $this->withSession([SetLocale::SESSION_KEY => 'bn'])
        ->get('/')
        ->assertOk()
        ->assertSee('আপনি কোন প্যানেলে লগইন করবেন?')
        ->assertDontSee('Which panel do you want to sign in to?');
});

it('ignores an unsupported language left in the session', function () {
    $this->withSession([SetLocale::SESSION_KEY => 'fr'])
        ->get('/')
        ->assertOk()
        ->assertSee('Which panel do you want to sign in to?');
});

it('shows the language switcher on every panel sign-in page', function (string $panel) {
    $this->get("/{$panel}/login")
        ->assertOk()
        ->assertSee(route('locale.switch', ['locale' => 'bn']), false)
        ->assertSee(route('locale.switch', ['locale' => 'en']), false);
})->with(['admin', 'teacher', 'staff', 'student']);

it('renders a panel in bangla once the language is switched', function () {
    $this->seed(RoleSeeder::class);

    $this->actingAs(User::factory()->admin()->create())
        ->withSession([SetLocale::SESSION_KEY => 'bn'])
        ->get(SubjectResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee('বিষয়')
        ->assertSee('নাম (বাংলা)')
        ->assertSee('সংক্ষিপ্ত নাম')
        ->assertSee(route('locale.switch', ['locale' => 'en']), false);
});

it('translates field labels derived from the field name', function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    app()->setLocale('bn');

    livewire(ManageSubjects::class)
        ->assertSee('তৈরির সময়')
        ->assertDontSee('Created at');
});

it('has a bangla translation for every translation key used in the app', function () {
    $translations = json_decode(File::get(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

    $files = collect([app_path(), resource_path('views')])
        ->flatMap(fn (string $path) => File::allFiles($path))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php');

    $usedKeys = $files
        ->flatMap(function (SplFileInfo $file) {
            $source = File::get($file->getPathname());

            preg_match_all('/(?:__|trans_choice)\(\s*\'([^\']+)\'/u', $source, $singleQuoted);
            preg_match_all('/(?:__|trans_choice)\(\s*"([^"]+)"/u', $source, $doubleQuoted);

            return [...$singleQuoted[1], ...$doubleQuoted[1]];
        })
        ->unique()
        ->values();

    expect($usedKeys)->not->toBeEmpty()
        ->and($usedKeys->reject(fn (string $key) => array_key_exists($key, $translations))->values()->all())->toBe([]);
});
