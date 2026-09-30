<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1769ff">
    <title>@yield('title', 'Портал ради') — RADA</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-base-200 text-base-content antialiased">
<div class="h-1.5 bg-gradient-to-r from-primary via-accent to-secondary"></div>
<header class="border-b border-base-300/70 bg-base-100/95">
    <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-5 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4">
            <a class="group flex items-center gap-3 rounded-2xl" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <span class="grid size-12 place-items-center rounded-2xl bg-primary text-xl font-black tracking-tight text-primary-content shadow-lg shadow-primary/20">R</span>
                <span>
                    <span class="block text-lg font-black tracking-tight">RADA</span>
                    <span class="block text-xs font-medium text-base-content/60">Цифрові сервіси ради</span>
                </span>
            </a>
            @auth
                <div class="hidden items-center gap-3 sm:flex">
                    <span class="max-w-48 truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-ghost btn-sm rounded-full" type="submit">Вийти</button>
                    </form>
                </div>
            @endauth
        </div>
        @auth
            <nav aria-label="Головна навігація" class="flex flex-wrap items-center gap-2">
                <a class="btn btn-sm rounded-full {{ request()->routeIs('dashboard') ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('dashboard') }}">Огляд</a>
                <a class="btn btn-sm rounded-full {{ request()->routeIs('imports.*') ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('imports.index') }}">Документи та імпорти</a>
                <div class="hidden flex-1 sm:block"></div>
                <details class="dropdown dropdown-end">
                    <summary class="btn btn-sm btn-outline rounded-full">Відкриті дані <span aria-hidden="true">⌄</span></summary>
                    <ul class="menu dropdown-content z-10 mt-2 w-64 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl">
                        <li><a href="{{ route('exports.motions') }}">Порядок денний <span class="text-xs text-base-content/50">motions.csv</span></a></li>
                        <li><a href="{{ route('exports.votings') }}">Поіменні голоси <span class="text-xs text-base-content/50">votings.csv</span></a></li>
                    </ul>
                </details>
                <div class="flex w-full items-center justify-between border-t border-base-300 pt-3 sm:hidden">
                    <span class="max-w-48 truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-ghost btn-sm rounded-full" type="submit">Вийти</button>
                    </form>
                </div>
            </nav>
        @endauth
    </div>
</header>
<main class="mx-auto min-h-[70vh] w-full max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
    <div class="mx-auto max-w-6xl">
        @if (session('status'))
            <div class="alert alert-success mb-6 rounded-2xl shadow-sm" role="status">
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-error mb-6 rounded-2xl shadow-sm" role="alert">
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error mb-6 rounded-2xl shadow-sm" role="alert">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </div>
</main>
<footer class="border-t border-base-300/70 bg-base-100">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-base-content/60 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <span class="font-bold text-base-content/80">RADA <span class="font-normal">· Робочий простір ради</span></span>
        <span>Дані проходять перевірку перед підтвердженням та експортом.</span>
    </div>
</footer>
</body>
</html>
