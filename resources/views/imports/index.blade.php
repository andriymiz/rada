@extends('layouts.app')

@section('title', 'Імпорти')

@section('content')
    <div class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <a class="link link-primary text-sm font-bold no-underline hover:underline" href="{{ route('dashboard') }}">← На головну</a>
            <p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-primary">Документи та голосування</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight sm:text-4xl">Імпорти</h1>
            <p class="mt-2 max-w-2xl text-base leading-7 text-base-content/65">Документи зберігаються у приватному сховищі. Розпізнані записи потрібно перевірити перед підтвердженням.</p>
        </div>
        <a class="btn btn-primary rounded-full px-6 shadow-lg shadow-primary/20" href="{{ route('imports.create') }}">
            <span aria-hidden="true">＋</span>
            Завантажити PDF
        </a>
    </div>

    @if ($imports->isEmpty())
        <section class="card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
            <div class="card-body items-center px-6 py-14 text-center">
                <span class="grid size-16 place-items-center rounded-3xl bg-primary/10 text-3xl text-primary" aria-hidden="true">▤</span>
                <h2 class="mt-2 text-xl font-extrabold">Тут поки порожньо</h2>
                <p class="max-w-md text-base-content/65">Завантажте PDF протоколу засідання, щоб створити перший імпорт.</p>
                <a class="btn btn-primary mt-3 rounded-full" href="{{ route('imports.create') }}">Завантажити документ</a>
            </div>
        </section>
    @else
        <section class="overflow-hidden rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300/70 px-5 py-4 sm:px-6">
                <h2 class="font-extrabold">Усі документи</h2>
                <span class="badge badge-ghost rounded-full">{{ $imports->total() }} записів</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-base-content/55">
                            <th>Документ</th>
                            <th>Сесія</th>
                            <th>Статус</th>
                            <th>Завантажив</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($imports as $import)
                        @php
                            $statusLabel = match ($import->status) {
                                \App\Enums\ImportStatus::Pending => 'Очікує обробки',
                                \App\Enums\ImportStatus::Processing => 'Обробляється',
                                \App\Enums\ImportStatus::NeedsReview => 'Потрібна перевірка',
                                \App\Enums\ImportStatus::Confirmed => 'Підтверджено',
                                \App\Enums\ImportStatus::Failed => 'Помилка',
                            };
                            $statusStyle = match ($import->status) {
                                \App\Enums\ImportStatus::NeedsReview => 'badge-warning',
                                \App\Enums\ImportStatus::Confirmed => 'badge-success',
                                \App\Enums\ImportStatus::Failed => 'badge-error',
                                default => 'badge-info',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a class="font-bold text-primary hover:underline" href="{{ route('imports.show', $import) }}">{{ $import->sourceDocument->original_name }}</a>
                                <span class="mt-1 block text-xs text-base-content/50">Імпорт #{{ $import->id }}</span>
                            </td>
                            <td>{{ $import->session?->title ?? 'Сесія №'.$import->session_number }}</td>
                            <td><span class="badge {{ $statusStyle }} badge-soft rounded-full">{{ $statusLabel }}</span></td>
                            <td>{{ $import->uploader?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $import->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($imports->hasPages())
                <div class="border-t border-base-300/70 px-5 py-4 sm:px-6">
                    {{ $imports->links() }}
                </div>
            @endif
        </section>
    @endif
@endsection
