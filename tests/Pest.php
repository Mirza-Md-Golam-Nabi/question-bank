<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ImportedImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Gives a teacher or student an active plan with no conditions at all —
 * every class, subject, difficulty and feature, no limits — for tests about
 * something other than plans. Without a plan a user may not build or
 * practise any exam (CLAUDE.md rule 7).
 */
function subscribeToEverything(User $user): Subscription
{
    $plan = SubscriptionPlan::factory()->pro()->create([
        'target_role' => $user->role->subscriptionTargetRole(),
    ]);

    return Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
}

/**
 * The images a JSON import names in "image", as uploads: a small PNG for
 * each file name, kept on a fake public disk.
 */
function imagesNamedIn(string $json): ImportedImages
{
    Storage::fake(ImportedImages::DISK);

    preg_match_all('/"image":\s*"([^"]+)"/', $json, $names);

    return ImportedImages::fromUploads(array_map(
        fn (string $name): UploadedFile => UploadedFile::fake()->image($name, 40, 40),
        array_unique($names[1]),
    ));
}
