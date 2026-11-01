<?php

namespace App\Actions;

use App\Contracts\ContactPointable;
use App\Enums\ContactPointType;
use App\Models\ContactPoint;
use Illuminate\Database\Eloquent\Model;

class StoreImportedContactPoints
{
    /**
     * @param  array<int, mixed>  $emails
     * @param  array<int, mixed>  $telephones
     * @param  array<int, mixed>  $socialAccounts
     */
    public function handle(
        Model&ContactPointable $contactable,
        array $emails = [],
        array $telephones = [],
        array $socialAccounts = [],
    ): void {
        foreach ($emails as $email) {
            $this->store($contactable, ContactPointType::Email, $email);
        }

        foreach ($telephones as $telephone) {
            if (! is_string($telephone) || blank($telephone)) {
                continue;
            }

            $this->store($contactable, ContactPointType::Phone, $this->normalizePhone($telephone));
        }

        foreach ($socialAccounts as $account) {
            if (! is_string($account) || blank($account)) {
                continue;
            }

            $this->store($contactable, ContactPointType::fromUrl($account), $account);
        }
    }

    private function store(Model&ContactPointable $contactable, ContactPointType $type, mixed $value): void
    {
        if (! is_string($value) || blank($value)) {
            return;
        }

        $contactPoint = ContactPoint::query()->where([
            'contactable_type' => $contactable->getMorphClass(),
            'contactable_id' => $contactable->getKey(),
            'type' => $type,
            'value' => $value,
        ])->first();

        if ($contactPoint instanceof ContactPoint) {
            return;
        }

        $contactPoint = new ContactPoint;
        $contactPoint->contactable_type = $contactable->getMorphClass();
        $contactPoint->contactable_id = $contactable->getKey();
        $contactPoint->type = $type->value;
        $contactPoint->value = $value;
        $contactPoint->is_public = true;
        $contactPoint->save();
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = ltrim($phone, "'+‘’ \t\n\r\0\x0B");

        return str_starts_with($phone, '+')
            ? $phone
            : (str_starts_with($cleaned, '380') ? '+'.$cleaned : $phone);
    }
}
