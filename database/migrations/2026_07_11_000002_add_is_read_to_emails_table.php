<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: the column may already exist in dev databases
        if (Schema::hasColumn('emails', 'is_read')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            $table->boolean('is_read')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('is_read');
        });
    }
};
