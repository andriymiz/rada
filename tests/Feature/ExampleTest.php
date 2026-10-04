<?php

test('the public landing page is available without signing in', function () {
    $response = $this->get('/');

    $response
        ->assertSee('Зборівська громада')
        ->assertSee('Офіційний сайт громади')
        ->assertSee('Увійти до системи')
        ->assertSee('fi-simple-layout')
        ->assertDontSee('fi-topbar')
        ->assertSee('href="'.route('filament.rada.auth.login').'"', false);
});

test('the admin panel still requires sign-in', function () {
    $this->get('/panel')->assertRedirect('/panel/login');
});
