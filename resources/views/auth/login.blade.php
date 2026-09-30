@extends('layouts.app')

@section('title', 'Вхід')

@section('content')
    <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-primary p-7 text-primary-content shadow-xl shadow-primary/15 sm:p-10 lg:min-h-[32rem] lg:p-12">
            <div aria-hidden="true" class="absolute -right-20 -top-16 -z-10 size-72 rounded-full bg-white/10"></div>
            <div aria-hidden="true" class="absolute -bottom-28 -left-12 -z-10 size-64 rounded-full bg-secondary/30 blur-2xl"></div>
            <span class="badge badge-secondary border-0 px-4 py-3 font-bold text-secondary-content">RADA · ЦИФРОВІ СЕРВІСИ</span>
            <h1 class="mt-10 max-w-xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">Сервіси ради —<br>просто й прозоро</h1>
            <p class="mt-5 max-w-lg text-lg leading-8 text-primary-content/80">Робочий простір для документів засідань, перевірки поіменних голосувань та підготовки відкритих даних.</p>
            <div class="mt-10 flex flex-wrap gap-3 text-sm font-semibold">
                <span class="rounded-full bg-white/10 px-4 py-2">Документи</span>
                <span class="rounded-full bg-white/10 px-4 py-2">Перевірка голосувань</span>
                <span class="rounded-full bg-white/10 px-4 py-2">Відкриті дані</span>
            </div>
        </section>

        <section class="mx-auto w-full max-w-md">
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-primary">Вхід працівника</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight">Вітаємо у RADA</h2>
            <p class="mt-2 leading-7 text-base-content/65">Увійдіть, щоб продовжити роботу.</p>
            <form class="card mt-7 rounded-3xl border border-base-300/70 bg-base-100 shadow-sm" method="post" action="{{ url('/login') }}">
                @csrf
                <div class="card-body gap-4 p-6 sm:p-8">
                    <div>
                        <label class="label mb-2" for="email"><span class="label-text font-bold">Електронна пошта</span></label>
                        <input class="input input-bordered w-full rounded-xl" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="name@example.gov.ua" required autofocus>
                    </div>
                    <div>
                        <label class="label mb-2" for="password"><span class="label-text font-bold">Пароль</span></label>
                        <input class="input input-bordered w-full rounded-xl" id="password" type="password" name="password" autocomplete="current-password" required>
                    </div>
                    <label class="label cursor-pointer justify-start gap-3 py-1">
                        <input class="checkbox checkbox-primary" type="checkbox" name="remember" value="1">
                        <span class="label-text">Запам’ятати мене</span>
                    </label>
                    <button class="btn btn-primary mt-2 w-full rounded-full" type="submit">Увійти <span aria-hidden="true">→</span></button>
                </div>
            </form>
            <p class="mt-5 text-sm leading-6 text-base-content/60">Облікові записи створює адміністратор. Самостійна реєстрація недоступна.</p>
        </section>
    </div>
@endsection
