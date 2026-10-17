<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // The search that decides how the alert is delivered. Deleting it
            // is how a buyer stops alerts, so every alert it raised goes with
            // it, including ones already emailed.
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();

            // When the alert's email was handed to the mailer; null while it
            // waits for the daily digest.
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // A buyer hears about a listing once, however many of their
            // searches it matches and however many times it goes live.
            $table->unique(['user_id', 'listing_id']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
