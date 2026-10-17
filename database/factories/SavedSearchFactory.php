<?php

namespace Database\Factories;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare factory search has no criteria, so it matches every live listing.
 * Tests state the criteria they care about.
 *
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'property_type' => null,
            'region' => null,
            'min_bedrooms' => null,
            'min_price' => null,
            'max_price' => null,
            'frequency' => AlertFrequency::Daily,
        ];
    }

    public function instant(): static
    {
        return $this->state(fn () => ['frequency' => AlertFrequency::Instant]);
    }

    public function daily(): static
    {
        return $this->state(fn () => ['frequency' => AlertFrequency::Daily]);
    }
}
