<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: the columns may already exist in dev databases
        if (Schema::hasColumn('emails', 'cc')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            $table->text('cc')->nullable()->after('to');
            $table->text('bcc')->nullable()->after('cc');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn(['cc', 'bcc']);
        });
    }
};
