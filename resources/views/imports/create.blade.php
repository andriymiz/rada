@extends('layouts.app')

@section('title', 'Завантажити PDF')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a class="link link-primary text-sm font-bold no-underline hover:underline" href="{{ route('imports.index') }}">← До імпортів</a>
        <div class="mb-7 mt-5">
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-primary">Новий імпорт</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight sm:text-4xl">Завантажити документ</h1>
            <p class="mt-2 text-base leading-7 text-base-content/65">Додайте PDF протоколу, щоб підготувати його до перевірки.</p>
        </div>

        <form class="card rounded-3xl border border-base-300/70 bg-base-100 shadow-sm" method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card-body gap-7 p-5 sm:p-8">
                <div>
                    <label class="label mb-2" for="document">
                        <span class="label-text font-bold">PDF-файл</span>
                        <span class="label-text-alt text-base-content/55">До {{ number_format($maxKilobytes / 1024, 0) }} MiB</span>
                    </label>
                    <div class="rounded-2xl border-2 border-dashed border-primary/25 bg-primary/[0.03] p-4 sm:p-6">
                        <input class="file-input file-input-bordered w-full rounded-xl" id="document" type="file" name="document" accept="application/pdf,.pdf" aria-describedby="document-help" required>
                        <p class="mt-3 text-sm leading-6 text-base-content/60" id="document-help">Виберіть PDF-файл із пристрою. Оригінал буде збережений у приватному сховищі.</p>
                    </div>
                </div>
                <div>
                    <label class="label mb-2" for="session_number"><span class="label-text font-bold">Номер сесії</span></label>
                    <input class="input input-bordered w-full rounded-xl" id="session_number" type="text" name="session_number" value="{{ old('session_number') }}" maxlength="64" placeholder="Наприклад, 99" autocomplete="off" required>
                    <p class="mt-2 text-sm text-base-content/60">Вкажіть номер сесії ради, до якої належить документ.</p>
                </div>
                <div class="alert alert-info rounded-2xl text-sm leading-6">
                    <span>Розпізнані результати залишаються чернетками, доки ви не перевірите та не підтвердите їх.</span>
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a class="btn btn-ghost rounded-full" href="{{ route('imports.index') }}">Скасувати</a>
                    <button class="btn btn-primary rounded-full px-7" type="submit">Завантажити PDF <span aria-hidden="true">→</span></button>
                </div>
            </div>
        </form>
    </div>
@endsection
