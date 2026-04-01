<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('domain');
            $table->string('requester_ip', 45)->nullable();
            $table->boolean('consent_to_email')->default(false);
            $table->string('status', 30)->default('processing'); // processing | complete | failed
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->unsignedSmallInteger('breach_count')->default(0);
            $table->string('sales_rep_email')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('domain');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
