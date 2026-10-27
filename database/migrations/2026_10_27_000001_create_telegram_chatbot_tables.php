<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. telegram_users
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('language_code', 12)->default('en')->index();
            $table->timestamp('accepted_terms_at')->nullable();
            $table->string('status', 20)->default('active')->index(); // active, muted, suspended, banned
            $table->timestamp('muted_until')->nullable();
            $table->unsignedInteger('paid_credits_balance')->default(0);
            $table->unsignedBigInteger('pending_report_conversation_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // 2. daily_usages
        Schema::create('daily_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->date('usage_date')->index();
            $table->unsignedInteger('free_matches_used')->default(0);
            $table->unsignedInteger('paid_credits_balance')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'usage_date']);
        });

        // 3. match_queue
        Schema::create('match_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('telegram_users')->cascadeOnDelete();
            $table->string('language_code', 12);
            $table->timestamp('queued_at');
            $table->string('status', 20)->default('waiting'); // waiting, matched, cancelled, expired
            $table->timestamps();
            $table->index(['language_code', 'status', 'queued_at'], 'idx_match_queue_lookup');
        });

        // 4. conversations
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('active')->index(); // active, ended
            $table->string('language_code', 12)->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('telegram_users')->nullOnDelete();
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });

        // 5. conversation_participants
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        // 6. chat_messages (metadata only by default - no message body stored)
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->string('message_type', 20)->default('text');
            $table->unsignedInteger('char_length')->default(0);
            $table->string('moderation_state', 20)->default('allowed')->index(); // allowed, blocked, rate_limited
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        // 7. payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->string('invoice_payload', 128)->unique();
            $table->string('currency', 3)->default('XTR');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('credits_granted')->default(50);
            $table->string('telegram_payment_charge_id', 191)->nullable()->unique();
            $table->string('provider_payment_charge_id', 191)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, paid, refunded, failed
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('raw_update_hash', 64)->nullable();
            $table->timestamps();
        });

        // 8. credit_ledger (immutable audit trail for all paid credit grants/debits/refunds/adjustments)
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->string('direction', 10); // credit, debit
            $table->integer('amount'); // positive integer
            $table->unsignedInteger('balance_after');
            $table->string('reason', 64)->index(); // purchase_grant, match_spend, refund_reversal, admin_adjustment
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // 9. reports
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('reason_code', 40)->index();
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open')->index(); // open, resolved, dismissed
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['reported_user_id', 'status']);
        });

        // 10. blocked_users
        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_user_id', 'blocked_user_id']);
            $table->index(['blocked_user_id', 'blocker_user_id']);
        });

        // 11. bans
        Schema::create('bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->string('action', 20)->index(); // warn, mute, suspend, ban
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable()->index();
            $table->text('reason');
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
        });

        // 12. admin_users
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('telegram_users')->nullOnDelete();
            $table->foreignId('web_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('telegram_id')->nullable()->unique();
            $table->string('role', 30)->default('moderator'); // super_admin, admin, moderator
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
        });

        // 13. audit_logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_admin_id')->nullable()->index();
            $table->unsignedBigInteger('actor_telegram_id')->nullable()->index();
            $table->string('action', 64)->index();
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 14. processed_updates
        Schema::create('processed_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('update_type', 40)->nullable();
            $table->timestamp('processed_at')->useCurrent()->index();
            $table->timestamps();
        });

        // 15. bot_settings
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, int, bool, json
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
        Schema::dropIfExists('processed_updates');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('bans');
        Schema::dropIfExists('blocked_users');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('match_queue');
        Schema::dropIfExists('daily_usages');
        Schema::dropIfExists('telegram_users');
    }
};
