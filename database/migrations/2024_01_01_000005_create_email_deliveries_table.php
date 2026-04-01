<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('sent_to');
            $table->string('cc_to')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('delivery_status', 30)->default('pending');
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
