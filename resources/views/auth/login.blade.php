@extends('layouts.app')

@section('title', 'Вхід')

@section('content')
    <h1>Вхід до RADA</h1>
    <form class="card" method="post" action="{{ url('/login') }}">
        @csrf
        <label for="email">Електронна пошта</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label for="password">Пароль</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <p><label><input type="checkbox" name="remember" value="1"> Запам’ятати мене</label></p>
        <button type="submit">Увійти</button>
    </form>
    <p class="muted">Облікові записи створює адміністратор через Artisan; самостійної реєстрації немає.</p>
@endsection
