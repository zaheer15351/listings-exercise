<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure formatting with no database or framework, so a plain unit test. The
 * matching rules live in SQL and are covered by the feature tests.
 */
class SavedSearchSummaryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('summaries')]
    public function test_the_summary_reads_as_the_search(array $criteria, string $summary): void
    {
        $this->assertSame($summary, (new SavedSearch($criteria))->summary());
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
            'type only' => [['property_type' => PropertyType::SemiDetached], 'Semi-detached'],
            'bedrooms only' => [['min_bedrooms' => 3], 'Any property, 3+ beds'],
            'min price only' => [['min_price' => 200_000], 'Any property, from £200,000'],
            'max price only' => [['max_price' => 300_000], 'Any property, up to £300,000'],
        ];
    }

    public function test_filters_keep_only_the_set_criteria_as_query_parameters(): void
    {
        $search = new SavedSearch(['property_type' => PropertyType::Flat, 'max_price' => 300_000]);

        $this->assertSame(['property_type' => 'flat', 'max_price' => 300_000], $search->filters());
    }
}
