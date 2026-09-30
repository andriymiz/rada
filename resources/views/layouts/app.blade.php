<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RADA') — RADA</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #17212b; background: #f4f6f8; }
        body { margin: 0; }
        header { background: #143d34; color: white; padding: 1rem max(1rem, calc((100% - 68rem) / 2)); }
        header a { color: white; margin-right: 1rem; }
        header form { display: inline; }
        main { max-width: 68rem; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border: 1px solid #dce2e6; border-radius: .4rem; padding: 1rem; margin: 1rem 0; }
        label { display: block; margin: .8rem 0 .25rem; }
        input, select, button { font: inherit; padding: .55rem; }
        button, .button { background: #176b54; color: white; border: 0; border-radius: .25rem; padding: .6rem .9rem; cursor: pointer; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { text-align: left; padding: .65rem; border-bottom: 1px solid #dce2e6; }
        .error, .notice { padding: .75rem; background: #fff4d6; margin: .75rem 0; }
        .muted { color: #59636e; }
    </style>
</head>
<body>
<header>
    <strong>RADA</strong>
    @auth
        <a href="{{ route('dashboard') }}">Панель</a>
        <a href="{{ route('imports.index') }}">Імпорти</a>
        <a href="{{ route('exports.votes') }}">Експорт голосувань</a>
        <form method="post" action="{{ route('logout') }}">@csrf <button type="submit">Вийти</button></form>
    @endauth
</header>
<main>
    @if (session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
