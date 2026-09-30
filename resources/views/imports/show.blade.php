@extends('layouts.app')

@section('title', 'Імпорт '.$import->id)

@section('content')
    <h1>Імпорт #{{ $import->id }}</h1>
    <div class="card">
        <p><strong>Файл:</strong> {{ $import->sourceDocument->original_name }}</p>
        <p><strong>Статус імпорту:</strong> {{ $import->status->value }}</p>
        <p><strong>Статус документа:</strong> {{ $import->sourceDocument->status->value }}</p>
        @if ($import->notes)
            <p><strong>Примітки:</strong> {{ $import->notes }}</p>
        @endif
        <p><strong>SHA-256:</strong> <code>{{ $import->sourceDocument->sha256 }}</code></p>
        <p><strong>Розмір:</strong> {{ number_format($import->sourceDocument->size / 1024, 1) }} KiB</p>
        <p><strong>Сесія:</strong> {{ $import->session?->title ?? 'Сесія №'.$import->session_number }}</p>
    </div>
    <div class="card">
        <h2>Обробка документа</h2>
        <p>Розпізнані записи збережено у staging та потребують ручної перевірки перед підтвердженням.</p>
        <p><strong>Записів staging:</strong> {{ $import->stagedRecords->count() }}</p>
        <p><strong>Валідних:</strong> {{ $import->stagedRecords->where('status', \App\Enums\StagedRecordStatus::Pending)->count() }}</p>
        <p><strong>Помилкових:</strong> {{ $import->stagedRecords->where('status', \App\Enums\StagedRecordStatus::Rejected)->count() }}</p>
        <p>Записи staging не є офіційними результатами. Офіційними для системи можуть бути лише перевірені й підтверджені голоси.</p>
        @if ($import->stagedRecords->isNotEmpty())
            <table>
                <thead><tr><th>Питання</th><th>Депутат</th><th>Результат</th><th>Стан</th><th>Помилка</th></tr></thead>
                <tbody>
                @foreach ($import->stagedRecords as $record)
                    <tr>
                        <td>{{ $record->question_number ?? '—' }} {{ $record->question_title }}</td>
                        <td>{{ $record->deputy_name ?? '—' }}</td>
                        <td>{{ $record->raw_result ?? '—' }}</td>
                        <td>{{ $record->status->value }}</td>
                        <td>{{ $record->validation_error ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if ($import->status === \App\Enums\ImportStatus::NeedsReview && $import->stagedRecords->where('status', \App\Enums\StagedRecordStatus::Rejected)->isEmpty())
            <form method="post" action="{{ route('imports.confirm', $import) }}">
                @csrf
                <button type="submit">Підтвердити імпорт</button>
            </form>
        @endif
        @if ($import->status !== \App\Enums\ImportStatus::Confirmed)
            <form method="post" action="{{ route('imports.destroy', $import) }}">
                @csrf
                @method('delete')
                <button type="submit">Скасувати та видалити чернетку</button>
            </form>
        @endif
    </div>
@endsection
