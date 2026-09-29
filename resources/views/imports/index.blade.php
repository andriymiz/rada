@extends('layouts.app')

@section('title', 'Імпорти')

@section('content')
    <h1>Імпорти</h1>
    <p>Документи зберігаються приватно. PDF parser/OCR буде доданий після аналізу дозволеного зразка.</p>
    <p><a class="button" href="{{ route('imports.create') }}">Завантажити PDF</a></p>
    @if ($imports->isEmpty())
        <div class="card">Імпортів поки немає.</div>
    @else
        <table>
            <thead><tr><th>Файл</th><th>Сесія</th><th>Статус</th><th>Завантажив</th><th>Дата</th></tr></thead>
            <tbody>
            @foreach ($imports as $import)
                <tr>
                    <td><a href="{{ route('imports.show', $import) }}">{{ $import->sourceDocument->original_name }}</a></td>
                    <td>{{ $import->session?->title ?? 'Не вказано' }}</td>
                    <td>{{ $import->status->value }}</td>
                    <td>{{ $import->uploader?->name ?? '—' }}</td>
                    <td>{{ $import->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $imports->links() }}
    @endif
@endsection
