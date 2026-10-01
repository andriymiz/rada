@extends('layouts.app')

@section('title', 'Панель')

@section('content')
    <section class="grid overflow-hidden border border-black/10 bg-base-100 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="px-6 py-10 sm:px-10 sm:py-14 lg:px-14">
            <p class="text-sm font-bold uppercase tracking-[0.12em] text-base-content/55">Цифрові сервіси ради</p>
            <h1 class="mt-8 max-w-2xl text-4xl font-medium leading-[1.08] tracking-tight sm:text-6xl">Робочий простір<br>для важливих справ</h1>
            <p class="mt-5 max-w-xl text-lg leading-7 text-base-content/65">Документи засідань, перевірка поіменних голосувань і відкриті дані — в одному місці.</p>
            <a class="btn btn-primary mt-7 h-12 rounded-full px-7" href="{{ route('imports.create') }}">
                Завантажити документ <span aria-hidden="true">→</span>
            </a>
        </div>
        <aside class="flex items-center bg-base-200 px-6 py-9 sm:px-10 lg:px-12">
            <div>
                <div class="flex items-center gap-4">
                    <span class="grid size-10 shrink-0 place-items-center bg-secondary text-2xl" aria-hidden="true">!</span>
                    <h2 class="text-xl font-medium">Перевіряйте перед підтвердженням</h2>
                </div>
                <p class="mt-5 leading-6">Розпізнані записи — це чернетки. Звірте їх із документом, перш ніж переносити дані до підтверджених голосів.</p>
            </div>
        </aside>
    </section>

    <section aria-labelledby="overview-heading" class="mt-10">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.12em] text-base-content/55">Огляд системи</p>
                <h2 id="overview-heading" class="mt-1 text-2xl font-medium tracking-tight sm:text-3xl">Стан роботи</h2>
            </div>
            <a class="link link-primary font-bold" href="{{ route('imports.index') }}">Усі імпорти <span aria-hidden="true">→</span></a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article class="card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
                <div class="card-body gap-4 p-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-base-content/65">Завантажені документи</span>
                        <span class="grid size-11 place-items-center rounded-2xl bg-primary/10 text-xl text-primary" aria-hidden="true">▤</span>
                    </div>
                    <p class="text-4xl font-black tracking-tight">{{ number_format($importCount) }}</p>
                    <p class="text-sm text-base-content/60">Усі зареєстровані імпорти</p>
                </div>
            </article>
            <article class="card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
                <div class="card-body gap-4 p-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-base-content/65">Очікують перевірки</span>
                        <span class="grid size-11 place-items-center rounded-2xl bg-warning/20 text-xl text-warning-content" aria-hidden="true">◷</span>
                    </div>
                    <p class="text-4xl font-black tracking-tight">{{ number_format($pendingCount) }}</p>
                    <p class="text-sm text-base-content/60">Розпізнані записи staging</p>
                </div>
            </article>
            <article class="card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm sm:col-span-2 xl:col-span-1">
                <div class="card-body gap-4 p-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-base-content/65">Підтверджені голоси</span>
                        <span class="grid size-11 place-items-center rounded-2xl bg-success/15 text-xl text-success" aria-hidden="true">✓</span>
                    </div>
                    <p class="text-4xl font-black tracking-tight">{{ number_format($confirmedCount) }}</p>
                    <p class="text-sm text-base-content/60">Готові до експорту</p>
                </div>
            </article>
        </div>
    </section>

    <section aria-labelledby="services-heading" class="mt-10">
        <div class="mb-5">
            <p class="text-sm font-bold uppercase tracking-[0.12em] text-base-content/55">Сервіси</p>
            <h2 id="services-heading" class="mt-1 text-2xl font-medium tracking-tight sm:text-3xl">Що потрібно зробити?</h2>
        </div>
        <div class="grid gap-4 lg:grid-cols-3">
            <a class="group card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm transition hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg" href="{{ route('imports.create') }}">
                <div class="card-body p-6">
                    <span class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-2xl text-primary" aria-hidden="true">↑</span>
                    <h3 class="mt-2 text-lg font-extrabold">Додати документ</h3>
                    <p class="text-sm leading-6 text-base-content/65">Завантажте PDF протоколу, щоб підготувати записи до перевірки.</p>
                    <span class="mt-2 font-bold text-primary">Завантажити <span aria-hidden="true">→</span></span>
                </div>
            </a>
            <a class="group card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm transition hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg" href="{{ route('imports.index') }}">
                <div class="card-body p-6">
                    <span class="grid size-12 place-items-center rounded-2xl bg-accent/15 text-2xl text-accent" aria-hidden="true">✓</span>
                    <h3 class="mt-2 text-lg font-extrabold">Перевірити імпорти</h3>
                    <p class="text-sm leading-6 text-base-content/65">Перегляньте статуси документів та звірте розпізнані записи.</p>
                    <span class="mt-2 font-bold text-primary">Відкрити імпорти <span aria-hidden="true">→</span></span>
                </div>
            </a>
            <a class="group card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm transition hover:-translate-y-1 hover:border-primary/40 hover:shadow-lg" href="{{ route('exports.motions') }}">
                <div class="card-body p-6">
                    <span class="grid size-12 place-items-center rounded-2xl bg-secondary/35 text-2xl text-secondary-content" aria-hidden="true">⇩</span>
                    <h3 class="mt-2 text-lg font-extrabold">Підготувати відкриті дані</h3>
                    <p class="text-sm leading-6 text-base-content/65">Завантажте CSV порядку денного та підтверджених поіменних голосів.</p>
                    <span class="mt-2 font-bold text-primary">Перейти до експорту <span aria-hidden="true">→</span></span>
                </div>
            </a>
        </div>
    </section>
@endsection
