<?php

namespace App\Rules;

use App\Enums\ContactPointType;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

class ContactPointValue implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $mountedActionTypes = data_get($this->data, 'mountedActions.*.data.type');
        $contactType = is_array($mountedActionTypes) && $mountedActionTypes !== []
            ? end($mountedActionTypes)
            : data_get($this->data, 'type');

        $rules = match ($contactType) {
            ContactPointType::Email->value => ['email'],
            ContactPointType::Website->value,
            ContactPointType::Facebook->value,
            ContactPointType::Instagram->value,
            ContactPointType::Telegram->value,
            ContactPointType::YouTube->value,
            ContactPointType::TikTok->value,
            ContactPointType::X->value,
            ContactPointType::LinkedIn->value,
            ContactPointType::WhatsApp->value => ['url:http,https'],
            default => [],
        };

        if ($rules === []) {
            return;
        }

        $validator = Validator::make(['value' => $value], ['value' => $rules]);

        if ($validator->fails()) {
            $fail($validator->errors()->first('value'));
        }
    }
}
