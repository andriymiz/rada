<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('rada.pdf_max_kilobytes'),
            ],
            'session_id' => ['nullable', 'integer', 'exists:council_sessions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Оберіть PDF-файл.',
            'document.mimes' => 'Дозволено завантажувати лише PDF-файли.',
            'document.mimetypes' => 'Тип завантаженого файлу не визначено як PDF.',
            'document.max' => 'PDF перевищує налаштований максимальний розмір.',
        ];
    }
}
