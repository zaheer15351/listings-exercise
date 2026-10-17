<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One fixed listing, one criterion varied per case, so a failure names the
 * rule that broke. Factory values are random, so nothing is left to them.
 */
class SavedSearchMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing = Listing::factory()
            ->for(Branch::factory()->create(['region' => 'Leeds']))
            ->live()
            ->create([
                'property_type' => PropertyType::Flat,
                'bedrooms' => 3,
                'price' => 250_000,
            ]);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('criteria')]
    public function test_a_listing_matches_when_every_set_criterion_holds(array $criteria, bool $matches): void
    {
        SavedSearch::factory()->create($criteria);

        $this->assertSame($matches, SavedSearch::query()->matching($this->listing)->exists());
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function criteria(): array
    {
        return [
            'no criteria matches anything' => [[], true],
            'same type' => [['property_type' => PropertyType::Flat], true],
            'other type' => [['property_type' => PropertyType::Terraced], false],
            'same region' => [['region' => 'Leeds'], true],
            'other region' => [['region' => 'Manchester'], false],
            'min bedrooms is inclusive' => [['min_bedrooms' => 3], true],
            'too few bedrooms' => [['min_bedrooms' => 4], false],
            'min price is inclusive' => [['min_price' => 250_000], true],
            'below min price' => [['min_price' => 250_001], false],
            'max price is inclusive' => [['max_price' => 250_000], true],
            'above max price' => [['max_price' => 249_999], false],
            'inside a price range' => [['min_price' => 200_000, 'max_price' => 300_000], true],
            'every criterion holds' => [[
                'property_type' => PropertyType::Flat, 'region' => 'Leeds',
                'min_bedrooms' => 2, 'min_price' => 200_000, 'max_price' => 300_000,
            ], true],
            'one criterion fails' => [[
                'property_type' => PropertyType::Flat, 'region' => 'Manchester',
                'min_bedrooms' => 2, 'min_price' => 200_000, 'max_price' => 300_000,
            ], false],
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('summaries')]
    public function test_the_summary_reads_as_the_search(array $criteria, string $summary): void
    {
        $this->assertSame($summary, SavedSearch::factory()->make($criteria)->summary());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function summaries(): array
    {
        return [
            'everything' => [[
                'property_type' => PropertyType::Flat, 'region' => 'Leeds',
                'min_bedrooms' => 2, 'min_price' => 200_000, 'max_price' => 300_000,
            ], 'Flat in Leeds, 2+ beds, £200,000 to £300,000'],
            'region only' => [['region' => 'Leeds'], 'Any property in Leeds'],
            'min price only' => [['min_price' => 200_000], 'Any property, from £200,000'],
            'max price only' => [['max_price' => 300_000], 'Any property, up to £300,000'],
        ];
    }
}
