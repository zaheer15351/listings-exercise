<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchPageTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->demo = $this->demoUser();
        Branch::factory()->create(['region' => 'Leeds']);
    }

    public function test_index_lists_only_the_users_own_searches(): void
    {
        SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);
        SavedSearch::factory()->for(User::factory())->create(['region' => 'Bristol']);

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 1)
                ->where('savedSearches.0.summary', 'Any property in Leeds')
                ->where('savedSearches.0.filters', ['region' => 'Leeds'])
            );
    }

    public function test_a_search_is_saved_for_the_signed_in_user(): void
    {
        $this->post('/saved-searches', [
            'property_type' => 'flat',
            'region' => 'Leeds',
            'min_bedrooms' => 2,
            'min_price' => 200000,
            'max_price' => 300000,
            'frequency' => 'instant',
        ])->assertRedirect('/saved-searches');

        $search = $this->demo->savedSearches()->sole();
        $this->assertSame(PropertyType::Flat, $search->property_type);
        $this->assertSame(200_000, $search->min_price);
        $this->assertSame(AlertFrequency::Instant, $search->frequency);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('validSearches')]
    public function test_either_end_of_the_price_range_is_optional(array $input): void
    {
        $this->post('/saved-searches', [...$input, 'frequency' => 'daily'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->demo->savedSearches()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validSearches(): array
    {
        return [
            'min only' => [['min_price' => 200000]],
            'max only' => [['max_price' => 300000]],
            'equal ends' => [['min_price' => 300000, 'max_price' => 300000]],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidSearches')]
    public function test_invalid_searches_are_rejected(array $input, string $field): void
    {
        $this->post('/saved-searches', [...['frequency' => 'daily'], ...$input])
            ->assertSessionHasErrors($field);

        $this->assertSame(0, SavedSearch::query()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidSearches(): array
    {
        return [
            'no criteria would match everything' => [[], 'criteria'],
            'blank criteria count as none' => [['region' => '', 'max_price' => ''], 'criteria'],
            'zero bedrooms would match everything' => [['min_bedrooms' => 0], 'min_bedrooms'],
            'zero price would match everything' => [['min_price' => 0], 'min_price'],
            'range the wrong way round' => [['min_price' => 300000, 'max_price' => 200000], 'max_price'],
            'a region no branch covers' => [['region' => 'Atlantis'], 'region'],
            'unknown property type' => [['property_type' => 'castle'], 'property_type'],
            'missing frequency' => [['region' => 'Leeds', 'frequency' => ''], 'frequency'],
            'unknown frequency' => [['region' => 'Leeds', 'frequency' => 'hourly'], 'frequency'],
        ];
    }

    public function test_a_user_can_delete_their_own_search(): void
    {
        $search = SavedSearch::factory()->for($this->demo)->create(['region' => 'Leeds']);

        $this->delete("/saved-searches/{$search->id}")->assertRedirect('/saved-searches');

        $this->assertModelMissing($search);
    }

    public function test_someone_elses_search_is_not_found(): void
    {
        $theirs = SavedSearch::factory()->for(User::factory())->create(['region' => 'Leeds']);

        $this->delete("/saved-searches/{$theirs->id}")->assertNotFound();

        $this->assertModelExists($theirs);
    }

    public function test_the_listings_page_offers_the_alert_frequencies(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alertFrequencies', count(AlertFrequency::cases()))
            );
    }
}
