<?php

it('shows a login link for the teacher, staff and student panels on the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('filament.teacher.auth.login'), false)
        ->assertSee(route('filament.staff.auth.login'), false)
        ->assertSee(route('filament.student.auth.login'), false);
});

it('does not link to the admin panel from the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee(route('filament.admin.auth.login'), false)
        ->assertDontSee('/admin/register', false);
});
