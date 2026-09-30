@extends('layouts.app')

@section('title', 'Панель')

@section('content')
    <h1>Панель RADA</h1>
    <p>Стартова основа модуля поіменних голосувань. Завантаження PDF наразі не запускає розпізнавання.</p>
    <section class="card">
        <p>Імпортів: <strong>{{ $importCount }}</strong></p>
        <p>Неперевірених staging-записів: <strong>{{ $pendingCount }}</strong></p>
        <p>Підтверджених голосів: <strong>{{ $confirmedCount }}</strong></p>
        <a class="button" href="{{ route('imports.index') }}">Перейти до імпортів</a>
        <a class="button" href="{{ route('exports.votes') }}">Завантажити CSV голосувань</a>
    </section>
@endsection
