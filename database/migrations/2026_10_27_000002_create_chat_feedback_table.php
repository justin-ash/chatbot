<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->string('feedback_type', 30)->index(); // thumbs_up, thumbs_down, spam, vulgar, underage, other_report
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, reviewed, actioned, dismissed
            $table->timestamps();

            $table->unique(['match_id', 'reporter_user_id', 'feedback_type'], 'uq_chat_feedback_match_reporter_type');
            $table->index(['target_user_id', 'feedback_type']);
        });

        Schema::table('telegram_users', function (Blueprint $table) {
            $table->unsignedBigInteger('pending_feedback_id')->nullable()->after('pending_report_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('pending_feedback_id');
        });

        Schema::dropIfExists('chat_feedback');
    }
};
