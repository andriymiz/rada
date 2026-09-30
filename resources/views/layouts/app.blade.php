<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'Портал ради') — RADA</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-base-200 text-base-content antialiased">
<header class="border-b-2 border-black bg-base-100">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex min-h-[4.5rem] items-center gap-5 sm:gap-8">
            <a class="flex shrink-0 items-center gap-3" href="{{ auth()->check() ? route('dashboard') : route('login') }}" aria-label="RADA — головна сторінка">
                <span class="grid size-8 place-items-center rounded-lg bg-black text-sm font-black text-white">R</span>
                <span class="hidden text-sm font-bold tracking-tight sm:inline">RADA</span>
            </a>
            @auth
                <nav aria-label="Головна навігація" class="flex min-w-0 flex-1 items-center gap-6 overflow-x-auto whitespace-nowrap text-sm font-medium [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-8">
                    <a class="border-b-2 py-6 {{ request()->routeIs('dashboard') ? 'border-black font-bold' : 'border-transparent hover:border-black' }}" href="{{ route('dashboard') }}">Огляд</a>
                    <a class="border-b-2 py-6 {{ request()->routeIs('imports.*') ? 'border-black font-bold' : 'border-transparent hover:border-black' }}" href="{{ route('imports.index') }}">Документи та імпорти</a>
                    <details class="dropdown">
                        <summary class="cursor-pointer list-none py-6 hover:underline">Відкриті дані <span aria-hidden="true">⌄</span></summary>
                        <ul class="menu dropdown-content z-10 mt-0 w-64 rounded-none border border-black/15 bg-base-100 p-2 shadow-lg">
                            <li><a href="{{ route('exports.motions') }}">Порядок денний <span class="text-xs text-base-content/50">motions.csv</span></a></li>
                            <li><a href="{{ route('exports.votings') }}">Поіменні голоси <span class="text-xs text-base-content/50">votings.csv</span></a></li>
                        </ul>
                    </details>
                </nav>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="hidden max-w-48 truncate text-sm font-medium lg:inline">{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-ghost btn-sm rounded-full font-semibold" type="submit">Вийти</button>
                    </form>
                </div>
            @endauth
        </div>
    </div>
</header>
<main class="mx-auto min-h-[75vh] w-full max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
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
