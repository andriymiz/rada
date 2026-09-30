@extends('layouts.app')

@section('title', 'Панель')

@section('content')
    <section class="relative isolate overflow-hidden rounded-[2rem] bg-primary px-6 py-8 text-primary-content shadow-xl shadow-primary/15 sm:px-10 sm:py-12">
        <div aria-hidden="true" class="absolute -right-16 -top-24 -z-10 size-72 rounded-full bg-white/10 sm:size-96"></div>
        <div aria-hidden="true" class="absolute -bottom-28 right-1/3 -z-10 size-56 rounded-full bg-secondary/30 blur-2xl"></div>
        <div class="max-w-2xl">
            <span class="badge badge-secondary mb-5 border-0 px-4 py-3 font-bold text-secondary-content">РОБОЧИЙ ПРОСТІР РАДИ</span>
            <h1 class="text-3xl font-black leading-tight tracking-tight sm:text-5xl">Важливі справи —<br class="hidden sm:block"> в одному місці</h1>
            <p class="mt-4 max-w-xl text-base leading-7 text-primary-content/80 sm:text-lg">Завантажуйте документи засідань, перевіряйте розпізнані голоси та готуйте підтверджені дані до експорту.</p>
            <a class="btn btn-secondary mt-7 rounded-full px-6 font-bold shadow-lg shadow-black/10" href="{{ route('imports.create') }}">
                Завантажити документ
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </section>

    <section aria-labelledby="overview-heading" class="mt-10">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-primary">Огляд системи</p>
                <h2 id="overview-heading" class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Стан роботи</h2>
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
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-primary">Сервіси</p>
            <h2 id="services-heading" class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Що потрібно зробити?</h2>
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
