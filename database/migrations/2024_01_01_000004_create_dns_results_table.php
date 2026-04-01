<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dns_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('spf_result', 30)->nullable();
            $table->text('spf_raw')->nullable();
            $table->string('dkim_result', 30)->nullable();
            $table->text('dkim_raw')->nullable();
            $table->string('dmarc_result', 30)->nullable();
            $table->text('dmarc_raw')->nullable();
            $table->text('alignment_notes')->nullable();
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_results');
    }
};
