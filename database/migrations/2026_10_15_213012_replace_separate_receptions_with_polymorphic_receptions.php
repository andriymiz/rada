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
        Schema::dropIfExists('official_receptions');
        Schema::dropIfExists('deputy_receptions');

        Schema::create('receptions', function (Blueprint $table): void {
            $table->id();
            $table->string('receptionable_type');
            $table->unsignedBigInteger('receptionable_id');
            $table->unique(
                ['receptionable_type', 'receptionable_id'],
                'receptions_receptionable_unique',
            );
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receptions');

        Schema::create('deputy_receptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deputy_id')->unique()->constrained('deputies')->restrictOnDelete();
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
};
