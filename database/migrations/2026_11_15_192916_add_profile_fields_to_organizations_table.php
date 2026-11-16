<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->text('constituent_doc')->nullable();
            $table->text('homepage')->nullable();
            $table->json('social_accounts')->nullable();
            $table->string('contact_point')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('opening_hours')->nullable();
            $table->text('availability_restriction')->nullable();
            $table->text('logo')->nullable();
            $table->foreignId('parent_org_id')->nullable()->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_org_id');
            $table->dropColumn([
                'description',
                'constituent_doc',
                'homepage',
                'social_accounts',
                'contact_point',
                'email',
                'phone',
                'opening_hours',
                'availability_restriction',
                'logo',
            ]);
        });
    }
};
