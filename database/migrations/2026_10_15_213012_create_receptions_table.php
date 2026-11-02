<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receptions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('receptionable');
            $table->string('katoottg', 19)->nullable();
            $table->string('address_post_code', 12)->nullable();
            $table->string('address_admin_unit_l1')->nullable();
            $table->string('address_admin_unit_l2')->nullable();
            $table->string('address_admin_unit_l3')->nullable();
            $table->string('address_admin_unit_l4')->nullable();
            $table->string('address_post_name')->nullable();
            $table->string('address_thoroughfare')->nullable();
            $table->string('address_locator_designator')->nullable();
            $table->string('address_locator_building')->nullable();
            $table->string('address_locator_name')->nullable();
            $table->text('opening_hours')->nullable();
            $table->text('availability_restriction')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receptions');
    }
};
