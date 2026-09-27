<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attachments used to live base64-encoded in a JSON column on emails,
 * so every inbox query dragged every file along. Move them to their own
 * table (raw bytes, deleted with the email via cascade) and index the
 * inbox sort column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('content_type');
            $table->unsignedBigInteger('size');
            $table->string('content_id')->nullable();
            $table->boolean('inline')->default(false);
            $table->binary('content');
            $table->timestamps();

            $table->index(['email_id', 'content_id']);
        });

        if (Schema::hasColumn('emails', 'attachments')) {
            DB::table('emails')
                ->whereNotNull('attachments')
                ->select(['id', 'attachments'])
                ->orderBy('id')
                ->chunkById(50, function ($emails) {
                    foreach ($emails as $email) {
                        $rows = array_map(fn (array $att) => [
                            'email_id' => $email->id,
                            'name' => $att['name'] ?? 'unnamed',
                            'content_type' => $att['content_type'] ?? 'application/octet-stream',
                            'size' => $att['size'] ?? 0,
                            'content_id' => $att['content_id'] ?? null,
                            'inline' => (bool) ($att['inline'] ?? false),
                            'content' => base64_decode($att['content'] ?? ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], json_decode($email->attachments, true) ?: []);

                        if ($rows) {
                            DB::table('email_attachments')->insert($rows);
                        }
                    }
                });

            Schema::table('emails', function (Blueprint $table) {
                $table->dropColumn('attachments');
            });
        }

        Schema::table('emails', function (Blueprint $table) {
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['received_at']);
            $table->json('attachments')->nullable();
        });

        DB::table('email_attachments')->orderBy('id')->get()->groupBy('email_id')
            ->each(fn ($atts, $emailId) => DB::table('emails')->where('id', $emailId)->update([
                'attachments' => json_encode($atts->map(fn ($att) => [
                    'name' => $att->name,
                    'content_type' => $att->content_type,
                    'size' => $att->size,
                    'content_id' => $att->content_id,
                    'inline' => (bool) $att->inline,
                    'content' => base64_encode($att->content),
                ])->values()),
            ]));

        Schema::dropIfExists('email_attachments');
    }
};
