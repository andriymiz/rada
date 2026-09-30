@extends('layouts.app')

@section('title', 'Імпорт '.$import->id)

@section('content')
    <h1>Імпорт #{{ $import->id }}</h1>
    <div class="card">
        <p><strong>Файл:</strong> {{ $import->sourceDocument->original_name }}</p>
        <p><strong>Статус імпорту:</strong> {{ $import->status->value }}</p>
        <p><strong>Статус документа:</strong> {{ $import->sourceDocument->status->value }}</p>
        <p><strong>SHA-256:</strong> <code>{{ $import->sourceDocument->sha256 }}</code></p>
        <p><strong>Розмір:</strong> {{ number_format($import->sourceDocument->size / 1024, 1) }} KiB</p>
        <p><strong>Сесія:</strong> {{ $import->session?->title ?? 'Не вказано' }}</p>
    </div>
    <div class="card">
        <h2>Обробка документа</h2>
        <p>Розпізнані записи збережено у staging та потребують ручної перевірки перед підтвердженням.</p>
        <p><strong>Записів staging:</strong> {{ $import->stagedRecords->count() }}</p>
        <p>Записи staging не є офіційними результатами. Офіційними для системи можуть бути лише перевірені й підтверджені голоси.</p>
    </div>
@endsection
