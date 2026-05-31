<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->morphs('notifiable');
                $table->string('type');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('notifications', 'resolved_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->timestamp('resolved_at')->nullable()->index()->after('read_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notifications', 'resolved_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex(['resolved_at']);
                $table->dropColumn('resolved_at');
            });
        }
    }
};
