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
        Schema::table('datasets', function (Blueprint $table): void {
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('dataset_groups')
                ->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->index(['group_id', 'sort'], 'datasets_group_id_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table): void {
            $table->dropIndex('datasets_group_id_sort_index');
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn('sort');
        });
    }
};
