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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('type')->default('page')->after('id');
            $table->foreignId('project_id')->nullable()->after('type')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->change();

            $table->unique(['project_id', 'source_path']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        DB::table('documents')->where('type', 'file')->delete();

        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'source_path']);
            $table->dropIndex(['type']);
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('type');
            $table->foreignId('version_id')->nullable(false)->change();
        });
    }
};
