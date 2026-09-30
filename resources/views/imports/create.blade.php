@extends('layouts.app')

@section('title', 'Завантажити PDF')

@section('content')
    <div class="grid overflow-hidden border border-black/10 bg-base-100 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="px-6 py-9 sm:px-10 sm:py-12 lg:px-14">
            <a class="text-sm font-medium underline decoration-1 underline-offset-4 hover:no-underline" href="{{ route('imports.index') }}">← До імпортів</a>
            <p class="mt-10 text-sm font-bold uppercase tracking-[0.12em] text-base-content/55">Новий імпорт</p>
            <h1 class="mt-3 text-4xl font-medium leading-[1.08] tracking-tight sm:text-5xl">Завантажити<br>документ</h1>
            <p class="mt-4 max-w-lg leading-6 text-base-content/65">Додайте PDF протоколу, щоб підготувати його до перевірки.</p>

            <form class="mt-8" method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="mb-7 block" for="document">
                    <span class="mb-3 flex items-center justify-between gap-3">
                        <span class="font-semibold">PDF-файл</span>
                        <span class="text-sm text-base-content/55">До {{ number_format($maxKilobytes / 1024, 0) }} MiB</span>
                    </span>
                    <span class="block border border-dashed border-black/50 px-4 py-7 text-center sm:px-8">
                        <input class="file-input file-input-ghost w-full max-w-full" id="document" type="file" name="document" accept="application/pdf,.pdf" aria-describedby="document-help" required>
                        <span class="mt-3 block text-sm leading-6 text-base-content/65" id="document-help">Виберіть PDF-файл із пристрою. Оригінал буде збережений у приватному сховищі.</span>
                    </span>
                </label>
                <label class="mb-7 block" for="session_number">
                    <span class="mb-1 block text-sm text-base-content/60">Номер сесії</span>
                    <input class="input input-bordered h-11 w-full rounded-none border-x-0 border-t-0 border-b-2 bg-transparent px-0 focus:border-black" id="session_number" type="text" name="session_number" value="{{ old('session_number') }}" maxlength="64" placeholder="Наприклад, 99" autocomplete="off" required>
                    <span class="mt-2 block text-sm text-base-content/60">Сесія ради, до якої належить документ.</span>
                </label>
                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <a class="btn btn-outline h-12 min-w-36 rounded-full border-black text-black hover:bg-black hover:text-white" href="{{ route('imports.index') }}">Скасувати</a>
                    <button class="btn btn-primary h-12 min-w-36 rounded-full" type="submit">Завантажити</button>
                </div>
            </form>
        </section>
        <aside class="flex items-center bg-base-200 px-6 py-9 sm:px-10 lg:px-12">
            <div class="mx-auto w-full max-w-lg">
                <div class="flex items-center gap-4">
                    <span class="grid size-10 shrink-0 place-items-center bg-secondary text-2xl" aria-hidden="true">!</span>
                    <h2 class="text-2xl font-medium tracking-tight">Зверніть увагу</h2>
                </div>
                <div class="mt-7 space-y-5 text-base leading-6">
                    <p>Файл зберігається приватно та обробляється для створення чернеток записів.</p>
                    <p>Перед підтвердженням звірте розпізнані результати з оригіналом протоколу.</p>
                    <p>Чернетки не публікуються та не потрапляють до експорту голосувань.</p>
                </div>
            </div>
        </aside>
    </div>
@endsection
