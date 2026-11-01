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
        Schema::create('official_receptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_id')->unique()->constrained('officials')->restrictOnDelete();
            $table->string('katoottg', 19);
            $table->string('address_post_code', 12);
            $table->string('address_admin_unit_l1');
            $table->string('address_admin_unit_l2');
            $table->string('address_admin_unit_l3')->nullable();
            $table->string('address_admin_unit_l4')->nullable();
            $table->string('address_post_name');
            $table->string('address_thoroughfare');
            $table->string('address_locator_designator');
            $table->string('address_locator_building')->nullable();
            $table->string('address_locator_name')->nullable();
            $table->text('opening_hours');
            $table->text('availability_restriction')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('official_receptions');
    }
};
