<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'listing_id' => Listing::factory()->live(),
            // The search belongs to the alert's user, as it always does in practice.
            'saved_search_id' => fn (array $attributes) => SavedSearch::factory()->create(['user_id' => $attributes['user_id']])->id,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['sent_at' => now()]);
    }
}
