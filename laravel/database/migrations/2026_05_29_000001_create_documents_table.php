<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('source_path', 1000)->nullable();
            $table->string('data_type', 20)->default('public');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->mediumText('raw_text');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('chunks_indexed')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
