@extends('layouts.app')

@section('title', 'Завантажити PDF')

@section('content')
    <h1>Завантажити PDF</h1>
    <p>Файл буде збережено у приватному сховищі та розібрано у staging-записи для перевірки.</p>
    <form class="card" method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="document">PDF-файл (до {{ number_format($maxKilobytes / 1024, 0) }} MiB)</label>
        <input id="document" type="file" name="document" accept="application/pdf,.pdf" required>
        <label for="session_number">Номер сесії</label>
        <input id="session_number" type="text" name="session_number" value="{{ old('session_number') }}" maxlength="64" required>
        <p><button type="submit">Завантажити</button></p>
    </form>
@endsection
