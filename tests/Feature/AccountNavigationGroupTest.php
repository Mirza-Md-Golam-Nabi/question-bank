<?php

use App\Filament\Staff\Pages\MyProfile as StaffMyProfile;
use App\Filament\Student\Pages\MyProfile as StudentMyProfile;
use App\Filament\Student\Pages\MySubscription as StudentMySubscription;
use App\Filament\Student\Pages\ReferralsAndCommission as StudentReferralsAndCommission;
use App\Filament\Support\NavigationGroup;
use App\Filament\Teacher\Pages\MyProfile as TeacherMyProfile;
use App\Filament\Teacher\Pages\MySubscription as TeacherMySubscription;
use App\Filament\Teacher\Pages\ReferralsAndCommission as TeacherReferralsAndCommission;

it('groups the profile, subscription and referral pages under "Account" in that order', function (string $profile, string $subscription, string $referrals) {
    expect([$profile::getNavigationGroup(), $subscription::getNavigationGroup(), $referrals::getNavigationGroup()])
        ->each->toBe(NavigationGroup::Account);
    expect([$profile::getNavigationSort(), $subscription::getNavigationSort(), $referrals::getNavigationSort()])
        ->toBe([1, 2, 3]);
})->with([
    'teacher' => [TeacherMyProfile::class, TeacherMySubscription::class, TeacherReferralsAndCommission::class],
    'student' => [StudentMyProfile::class, StudentMySubscription::class, StudentReferralsAndCommission::class],
]);

it('leaves the staff profile page outside any group', function () {
    expect(StaffMyProfile::getNavigationGroup())->toBeNull();
});
