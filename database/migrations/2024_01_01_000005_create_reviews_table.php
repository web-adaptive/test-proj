<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('yandex_settings_id')->constrained('yandex_settings')->onDelete('cascade');
            $table->dateTime('date');
            $table->string('branch')->nullable();
            $table->string('reviewer_name');
            $table->string('reviewer_phone')->nullable();
            $table->integer('rating');
            $table->text('text');
            $table->string('external_id')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
