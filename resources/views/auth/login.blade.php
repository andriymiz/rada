@extends('layouts.app')

@section('title', 'Вхід')

@section('content')
    <div class="grid min-h-[70vh] bg-base-100 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="flex items-center px-6 py-12 sm:px-12 lg:px-16 xl:px-24">
            <div class="mx-auto w-full max-w-lg">
                <p class="text-sm font-bold uppercase tracking-[0.12em] text-base-content/55">RADA · Цифрові сервіси ради</p>
                <h1 class="mt-8 max-w-lg text-4xl font-medium leading-[1.08] tracking-tight sm:text-6xl">Увійти до<br>робочого простору</h1>
                <p class="mt-5 max-w-md text-lg leading-7 text-base-content/65">Документи засідань, перевірка голосувань і відкриті дані — в одному місці.</p>
                <form class="mt-8" method="post" action="{{ url('/login') }}">
                    @csrf
                    <label class="mb-5 block" for="email">
                        <span class="mb-2 block text-sm font-semibold">Електронна пошта</span>
                        <input class="input input-bordered h-14 w-full rounded-none border-x-0 border-t-0 border-b-2 bg-transparent px-0 text-base focus:border-black" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="name@example.gov.ua" required autofocus>
                    </label>
                    <label class="mb-5 block" for="password">
                        <span class="mb-2 block text-sm font-semibold">Пароль</span>
                        <input class="input input-bordered h-14 w-full rounded-none border-x-0 border-t-0 border-b-2 bg-transparent px-0 text-base focus:border-black" id="password" type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 py-2">
                        <input class="checkbox checkbox-primary" type="checkbox" name="remember" value="1">
                        <span class="text-sm">Запам’ятати мене</span>
                    </label>
                    <button class="btn btn-primary mt-5 h-14 w-full rounded-full text-base" type="submit">Увійти</button>
                </form>
                <p class="mt-6 text-sm leading-6 text-base-content/60">Облікові записи створює адміністратор. Самостійна реєстрація недоступна.</p>
            </div>
        </section>
        <aside class="flex items-center bg-base-200 px-6 py-12 sm:px-12 lg:px-16">
            <div class="mx-auto w-full max-w-lg">
                <div class="flex items-center gap-4">
                    <span class="grid size-10 shrink-0 place-items-center bg-secondary text-2xl" aria-hidden="true">!</span>
                    <h2 class="text-2xl font-medium tracking-tight">Зверніть увагу</h2>
                </div>
                <div class="mt-7 space-y-5 text-base leading-6">
                    <p>Це захищений робочий простір для працівників ради. Доступ надає адміністратор.</p>
                    <p>Розпізнані з PDF записи потребують перевірки та не стають офіційними результатами автоматично.</p>
                    <p>Підтверджені голоси можна експортувати у відкриті дані після звірки.</p>
                </div>
            </div>
        </aside>
    </div>
@endsection
