@extends('layouts.app')

@section('title', 'Завантажити PDF')

@section('content')
    <h1>Завантажити PDF</h1>
    <p>Файл буде збережено у приватному сховищі. Розпізнавання документа поки не виконується.</p>
    <form class="card" method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="document">PDF-файл (до {{ number_format($maxKilobytes / 1024, 0) }} MiB)</label>
        <input id="document" type="file" name="document" accept="application/pdf,.pdf" required>
        <label for="session_id">Сесія (необов’язково)</label>
        <select id="session_id" name="session_id">
            <option value="">Не вказано</option>
            @foreach ($sessions as $session)
                <option value="{{ $session->id }}" @selected(old('session_id') == $session->id)>
                    {{ $session->title }}
                </option>
            @endforeach
        </select>
        <p><button type="submit">Завантажити</button></p>
    </form>
@endsection
