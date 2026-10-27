<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('method_type', 30)->index(); // gpay_upi, bank_account
            $table->string('account_label', 100);
            $table->string('account_holder_name', 120);
            $table->string('gpay_upi_id', 120)->nullable();
            $table->string('gpay_phone', 30)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->string('ifsc_swift_code', 40)->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payout_account_id')->nullable()->constrained('admin_payout_accounts')->nullOnDelete();
            $table->string('reference_id', 64)->unique();
            $table->string('payout_method', 30)->index(); // gpay_upi, bank_account
            $table->unsignedBigInteger('stars_deducted');
            $table->decimal('usd_amount', 10, 2);
            $table->decimal('inr_amount', 12, 2);
            $table->string('payout_currency', 10)->default('INR');
            $table->decimal('payout_amount', 12, 2);
            $table->string('destination_summary', 255);
            $table->string('provider', 40)->default('direct_payout'); // razorpayx, direct_payout, fragment_ton
            $table->string('provider_payout_id', 120)->nullable(); // UTR / bank reference
            $table->string('status', 20)->default('pending')->index(); // pending, processing, completed, rejected
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('admin_payout_accounts');
    }
};
