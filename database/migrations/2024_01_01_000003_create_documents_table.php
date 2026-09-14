<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->longText('body');
            $table->unsignedInteger('order')->default(0);
            $table->string('section')->nullable();
            $table->string('source_path');
            $table->string('blob_sha');
            $table->boolean('searchable')->default(true);
            $table->json('seo')->nullable();
            $table->timestamps();

            $table->unique(['version_id', 'slug']);
            $table->unique(['version_id', 'source_path']);
            $table->index('section');
        });

        // SQLite has no full-text index support, so this is skipped there —
        // fine for tests, which search via the "collection" Scout driver.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            Schema::table('documents', function (Blueprint $table) {
                $table->fullText(['title', 'body']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
