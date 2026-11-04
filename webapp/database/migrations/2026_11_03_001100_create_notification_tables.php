<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notification delivery log (§2.6, Q8, Tiket 16)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20); // email, telegram, badge
            $table->string('recipient'); // email address atau chat_id
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('related_type', 30)->nullable(); // demurrage_alarm, milestone_verified, dll
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('container_number', 20)->nullable();
            $table->boolean('sent_successfully')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['company_id', 'channel', 'sent_at']);
        });

        // Telegram bot config per company (§2.6)
        Schema::create('telegram_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('bot_token');
            $table->string('chat_id'); // target chat/group
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_configs');
        Schema::dropIfExists('notification_logs');
    }
};
