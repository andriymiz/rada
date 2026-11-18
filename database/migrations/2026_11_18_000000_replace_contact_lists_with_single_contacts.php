<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['organizations', 'organization_units', 'officials', 'deputies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->json('additional_contacts')->nullable();
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['emails', 'phones']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['organizations', 'organization_units', 'officials', 'deputies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->json('emails')->nullable();
                $table->json('phones')->nullable();
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['email', 'phone', 'additional_contacts']);
            });
        }
    }
};
