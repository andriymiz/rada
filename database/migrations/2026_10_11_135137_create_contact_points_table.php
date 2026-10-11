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
        Schema::create('contact_points', function (Blueprint $table): void {
            $table->id();
            $table->morphs('contactable');
            $table->string('type');
            $table->text('value');
            $table->boolean('is_public')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('deputies', function (Blueprint $table): void {
            $table->dropColumn(['email', 'telephone', 'account']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Contact point migration is intentionally irreversible because contact values and visibility are now stored as individual records.');
    }
};
