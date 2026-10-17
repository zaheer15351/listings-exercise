<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // One nullable column per criterion; null means "any". The same
            // criteria as the listings filter, so a saved search is exactly the
            // search it was saved from.
            $table->string('property_type')->nullable();
            $table->string('region')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->unsignedInteger('min_price')->nullable();
            $table->unsignedInteger('max_price')->nullable();

            $table->string('frequency')->default('daily');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
