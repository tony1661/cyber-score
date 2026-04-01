<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('breach_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('source_name', 100);
            $table->string('breach_name', 100);
            $table->date('breach_date')->nullable();
            $table->json('exposed_attributes_json')->nullable();
            $table->unsignedTinyInteger('severity_score')->default(0);
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breach_events');
    }
};
