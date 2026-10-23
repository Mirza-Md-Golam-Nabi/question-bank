<?php

use App\Enums\UserRole;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Models\AcademicClass;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\OfferPushNotification;
use App\Services\OfferNotifier;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->classSix = AcademicClass::factory()->create(['name' => 'Class Six']);
    $this->classSeven = AcademicClass::factory()->create(['name' => 'Class Seven']);
    $this->student = User::factory()->student()->create(['academic_class_id' => $this->classSix->id]);
    $this->teacher = User::factory()->teacher()->create();

    $this->subscribe = fn (User $user, string $endpoint) => $user->updatePushSubscription($endpoint, 'public-key', 'auth-token', 'aes128gcm');
});

it('serves one app manifest that opens on /app', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson([
            'name' => 'Next Fusion',
            'short_name' => 'Next Fusion',
            'start_url' => '/app',
            'scope' => '/',
            'display' => 'standalone',
            'icons' => [['src' => config('pwa.icons.192'), 'sizes' => '192x192']],
        ]);

    $this->get('/')->assertOk()->assertSee('/manifest.webmanifest', false);
});

it('opens the app on the signed-in user\'s own dashboard, or on the sign-in choices', function () {
    $this->get('/app')->assertOk()->assertSee(route('filament.student.auth.login'))->assertSee(route('filament.school.auth.login'));

    $this->actingAs($this->student)->get('/app')->assertRedirect(route('filament.student.pages.dashboard'));
    $this->actingAs($this->teacher)->get('/app')->assertRedirect(route('filament.teacher.pages.dashboard'));
});

it('shows an offer on the home page only while it is on', function () {
    Offer::factory()->create(['title' => 'Eid offer', 'ends_at' => now()->addDay()]);
    Offer::factory()->create(['title' => 'Old offer', 'ends_at' => now()->subMinute()]);
    Offer::factory()->create(['title' => 'Coming offer', 'starts_at' => now()->addDay()]);
    Offer::factory()->create(['title' => 'Switched off', 'is_active' => false]);

    $this->get('/')->assertOk()->assertSee('Eid offer')->assertDontSee('Old offer')->assertDontSee('Coming offer')->assertDontSee('Switched off');
    $this->get('/app')->assertSee('Eid offer')->assertDontSee('Old offer');
});

it('puts an offer in the banner of its roles — a student\'s only for the classes it names', function () {
    $forAll = Offer::factory()->forRoles([UserRole::Student, UserRole::Teacher])->create();
    $forSix = Offer::factory()->forRoles(UserRole::Student)->create(['academic_class_ids' => [(string) $this->classSix->id]]);
    $forSeven = Offer::factory()->forRoles(UserRole::Student)->create(['academic_class_ids' => [$this->classSeven->id]]);
    $forSchools = Offer::factory()->forRoles(UserRole::School)->create();

    expect($forSix->fresh()->academic_class_ids)->toBe([$this->classSix->id])
        ->and(Offer::inBannerOf($this->student)->pluck('id')->all())->toEqualCanonicalizing([$forAll->id, $forSix->id])
        ->and(Offer::inBannerOf($this->teacher)->pluck('id')->all())->toBe([$forAll->id])
        ->and($forSix->audience()->pluck('id')->all())->toBe([$this->student->id])
        ->and($forSeven->audience()->count())->toBe(0)
        ->and($forSchools->audienceLabels())->toBe([UserRole::School->getLabel()]);
});

it('shows the banner on the dashboard until the user closes it', function () {
    Filament::setCurrentPanel('student');
    $offer = Offer::factory()->forRoles(UserRole::Student)->create(['title' => 'Practice pack']);

    $this->actingAs($this->student)->get('/student')->assertOk()->assertSee('Practice pack');

    $this->postJson(route('offers.dismiss', $offer))->assertOk();

    expect(Offer::inBannerOf($this->student)->count())->toBe(0);
    $this->get('/student')->assertOk()->assertDontSee('Practice pack');
});

it('lets an Admin write an offer for some classes of students', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin = User::factory()->admin()->create());

    livewire(ManageOffers::class)
        ->callAction('create', [
            'title' => 'Class six pack',
            'body' => 'Half price this week.',
            'target_roles' => [UserRole::Student->value],
            'academic_class_ids' => [(string) $this->classSix->id],
            'button_url' => '/student/my-subscription',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $offer = Offer::query()->sole();
    expect($offer->academic_class_ids)->toBe([$this->classSix->id])
        ->and($offer->created_by)->toBe($admin->id);
});

it('sends an offer to the bell of everyone it is for, once', function () {
    $this->withoutDefer();
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->forRoles(UserRole::Student)->create(['title' => 'Bell offer']);

    app(OfferNotifier::class)->send($offer, $admin, toBell: true, toPush: false);

    expect($this->student->notifications()->count())->toBe(1)
        ->and($this->student->notifications()->first()->data['title'])->toBe('Bell offer')
        ->and($this->teacher->notifications()->count())->toBe(0)
        ->and($offer->fresh()->notified_at)->not->toBeNull()
        ->and(fn () => app(OfferNotifier::class)->send($offer->fresh(), $admin, true, false))->toThrow(ValidationException::class);
});

it('sends an offer as a push notification only to the devices that switched it on', function () {
    $this->withoutDefer();
    Notification::fake();
    $otherStudent = User::factory()->student()->create();
    ($this->subscribe)($this->student, 'https://push.example/one');

    $offer = Offer::factory()->forRoles(UserRole::Student)->create();
    expect(app(OfferNotifier::class)->reach($offer))->toBe(['users' => 2, 'push' => 1]);

    app(OfferNotifier::class)->send($offer, User::factory()->admin()->create(), toBell: false, toPush: true);

    Notification::assertSentTo($this->student, OfferPushNotification::class);
    Notification::assertNotSentTo($otherStudent, OfferPushNotification::class);
});

it('sends nothing for an offer that is not on, or with neither way chosen', function () {
    $admin = User::factory()->admin()->create();
    $off = Offer::factory()->create(['is_active' => false]);

    expect(fn () => app(OfferNotifier::class)->send($off, $admin, true, true))->toThrow(ValidationException::class)
        ->and(fn () => app(OfferNotifier::class)->send(Offer::factory()->create(), $admin, false, false))->toThrow(ValidationException::class);
});

it('sends from the Admin\'s offers list', function () {
    $this->withoutDefer();
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());
    $offer = Offer::factory()->forRoles(UserRole::Teacher)->create();

    livewire(ManageOffers::class)
        ->callAction(TestAction::make('send')->table($offer), ['to_bell' => true, 'to_push' => false])
        ->assertNotified();

    expect($this->teacher->notifications()->count())->toBe(1);
    livewire(ManageOffers::class)->assertActionHidden(TestAction::make('send')->table($offer->fresh()));
});

it('keeps a signed-in user\'s devices for push notifications', function () {
    $subscription = ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret']];

    $this->postJson(route('push-subscriptions.store'), $subscription)->assertUnauthorized();

    $this->actingAs($this->student)->postJson(route('push-subscriptions.store'), $subscription)->assertOk();
    expect($this->student->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example/abc']);

    $this->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://push.example/abc'])->assertOk();
    expect($this->student->pushSubscriptions()->count())->toBe(0);
});
