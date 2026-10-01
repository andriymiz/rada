@extends('layouts.app')

@section('title', 'Імпорт '.$import->id)

@section('content')
    @php
        $importStatusLabel = match ($import->status) {
            \App\Enums\ImportStatus::Pending => 'Очікує обробки',
            \App\Enums\ImportStatus::Processing => 'Обробляється',
            \App\Enums\ImportStatus::NeedsReview => 'Потрібна перевірка',
            \App\Enums\ImportStatus::Confirmed => 'Підтверджено',
            \App\Enums\ImportStatus::Failed => 'Помилка',
        };
        $documentStatusLabel = match ($import->sourceDocument->status) {
            \App\Enums\SourceDocumentStatus::Uploaded => 'Завантажено',
            \App\Enums\SourceDocumentStatus::Processing => 'Обробляється',
            \App\Enums\SourceDocumentStatus::Processed => 'Опрацьовано',
            \App\Enums\SourceDocumentStatus::Rejected => 'Відхилено',
        };
    @endphp

    <div class="mb-7">
        <a class="link link-primary text-sm font-bold no-underline hover:underline" href="{{ route('imports.index') }}">← До імпортів</a>
        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-primary">Документ · імпорт #{{ $import->id }}</p>
                <h1 class="mt-1 break-words text-3xl font-black tracking-tight sm:text-4xl">{{ $import->sourceDocument->original_name }}</h1>
            </div>
            <span class="badge badge-lg {{ $import->status === \App\Enums\ImportStatus::Confirmed ? 'badge-success' : ($import->status === \App\Enums\ImportStatus::Failed ? 'badge-error' : 'badge-warning') }} badge-soft rounded-full px-4">{{ $importStatusLabel }}</span>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[0.85fr_1.15fr]">
        <section aria-labelledby="document-details" class="card h-fit rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
            <div class="card-body p-5 sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-[0.12em] text-primary">Відомості</p>
                        <h2 id="document-details" class="mt-1 text-xl font-extrabold">Про документ</h2>
                    </div>
                    <span class="badge badge-ghost rounded-full">{{ $documentStatusLabel }}</span>
                </div>
                <dl class="mt-3 divide-y divide-base-300/70">
                    <div class="py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Сесія</dt>
                        <dd class="mt-1 font-semibold">{{ $import->session?->title ?? 'Сесія №'.$import->session_number }}</dd>
                    </div>
                    <div class="py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Завантажив</dt>
                        <dd class="mt-1 font-semibold">{{ $import->uploader?->name ?? '—' }}</dd>
                    </div>
                    <div class="py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Дата завантаження</dt>
                        <dd class="mt-1 font-semibold">{{ $import->created_at->format('d.m.Y о H:i') }}</dd>
                    </div>
                    <div class="py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Розмір файлу</dt>
                        <dd class="mt-1 font-semibold">{{ number_format($import->sourceDocument->size / 1024, 1) }} KiB</dd>
                    </div>
                    <div class="py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Контрольна сума SHA-256</dt>
                        <dd class="mt-1 break-all font-mono text-xs leading-5 text-base-content/70">{{ $import->sourceDocument->sha256 }}</dd>
                    </div>
                    @if ($import->notes)
                        <div class="py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Примітки</dt>
                            <dd class="mt-1 leading-6">{{ $import->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        <section aria-labelledby="review-heading" class="card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
            <div class="card-body p-5 sm:p-7">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.12em] text-primary">Перевірка</p>
                    <h2 id="review-heading" class="mt-1 text-xl font-extrabold">Розпізнані записи</h2>
                    <p class="mt-2 leading-6 text-base-content/65">Звірте записи з оригіналом. До підтвердження вони не є офіційними результатами.</p>
                </div>
                <div class="stats stats-vertical mt-2 w-full rounded-2xl bg-base-200 shadow-none sm:stats-horizontal">
                    <div class="stat px-4 py-3">
                        <div class="stat-title text-xs">Усього записів</div>
                        <div class="stat-value text-2xl">{{ $import->stagedRecords->count() }}</div>
                    </div>
                    <div class="stat px-4 py-3">
                        <div class="stat-title text-xs">Потребують перевірки</div>
                        <div class="stat-value text-2xl text-warning">{{ $import->stagedRecords->where('status', \App\Enums\StagedRecordStatus::Pending)->count() }}</div>
                    </div>
                    <div class="stat px-4 py-3">
                        <div class="stat-title text-xs">Помилки</div>
                        <div class="stat-value text-2xl text-error">{{ $import->stagedRecords->where('status', \App\Enums\StagedRecordStatus::Rejected)->count() }}</div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @if ($import->stagedRecords->isNotEmpty())
        <section aria-labelledby="records-heading" class="mt-5 overflow-hidden rounded-3xl border border-base-300/70 bg-base-100 shadow-sm">
            <div class="border-b border-base-300/70 px-5 py-4 sm:px-6">
                <h2 id="records-heading" class="font-extrabold">Записи для звірки</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-base-content/55">
                            <th>Питання</th>
                            <th>Депутат</th>
                            <th>Результат</th>
                            <th>Стан</th>
                            <th>Примітка</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($import->stagedRecords as $record)
                        @php
                            $recordStatusLabel = match ($record->status) {
                                \App\Enums\StagedRecordStatus::Pending => 'На перевірці',
                                \App\Enums\StagedRecordStatus::Confirmed => 'Підтверджено',
                                \App\Enums\StagedRecordStatus::Rejected => 'Помилка',
                            };
                        @endphp
                        <tr>
                            <td class="min-w-56 whitespace-normal">
                                @if ($record->question_number)
                                    <span class="mr-1 inline-grid size-7 place-items-center rounded-lg bg-primary/10 text-xs font-bold text-primary">{{ $record->question_number }}</span>
                                @endif
                                <span class="font-semibold">{{ $record->question_title ?: '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap">{{ $record->deputy_name ?? '—' }}</td>
                            <td class="whitespace-nowrap font-semibold">{{ $record->raw_result ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $record->status === \App\Enums\StagedRecordStatus::Rejected ? 'badge-error' : ($record->status === \App\Enums\StagedRecordStatus::Confirmed ? 'badge-success' : 'badge-warning') }} badge-soft rounded-full">{{ $recordStatusLabel }}</span>
                            </td>
                            <td class="min-w-48 whitespace-normal text-sm text-base-content/65">{{ $record->validation_error ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="alert alert-info mt-5 rounded-2xl">
            <span>Для цього документа ще немає розпізнаних записів.</span>
        </div>
    @endif

    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if ($import->status !== \App\Enums\ImportStatus::Confirmed)
            <form method="post" action="{{ route('imports.destroy', $import) }}">
                @csrf
                @method('delete')
                <button class="btn btn-ghost rounded-full text-error hover:bg-error/10" type="submit">Скасувати та видалити чернетку</button>
            </form>
        @else
            <span class="text-sm font-semibold text-success">Імпорт підтверджено</span>
        @endif
        @if ($import->status === \App\Enums\ImportStatus::NeedsReview && $import->stagedRecords->where('status', \App\Enums\StagedRecordStatus::Rejected)->isEmpty())
            <form method="post" action="{{ route('imports.confirm', $import) }}">
                @csrf
                <button class="btn btn-primary rounded-full px-6" type="submit">Підтвердити перевірені записи <span aria-hidden="true">→</span></button>
            </form>
        @endif
    </div>
@endsection
