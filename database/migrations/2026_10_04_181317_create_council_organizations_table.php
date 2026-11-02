<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('edrpou', 8)->unique();
            $table->string('katoottg', 19)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_organizations');
    }
};
