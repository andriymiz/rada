<?php

test('the public landing page is available without signing in', function () {
    config([
        'services.open_data.base_url' => 'https://portal.example.test/',
        'services.open_data.organization_slug' => 'sample-council',
    ]);

    $response = $this->get('/');

    $response
        ->assertSee('громада')
        ->assertSee('Увійти до системи')
        ->assertSee('fi-simple-layout')
        ->assertDontSee('fi-topbar')
        ->assertSee('href="'.route('filament.rada.auth.login').'"', false)
        ->assertSee('href="https://portal.example.test/organization/sample-council"', false);
});

test('the admin panel still requires sign-in', function () {
    $this->get('/panel')->assertRedirect('/panel/login');
});
