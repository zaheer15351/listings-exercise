<?php

namespace App\Http\Requests;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same criteria as ListingIndexRequest, with two differences that
     * matter only for alerts: a zero would match everything, so numbers start
     * at 1, and the region must be one a branch covers, or the search could
     * never match.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'region' => ['nullable', 'string', 'max:100', Rule::exists('branches', 'region')],
            'min_bedrooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'min_price' => ['nullable', 'integer', 'min:1'],
            'max_price' => ['nullable', 'integer', 'min:1', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'frequency' => ['required', new Enum(AlertFrequency::class)],
        ];
    }

    /**
     * A search with no criteria matches every listing, which is the spam the
     * feature exists to avoid.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! collect(SavedSearch::CRITERIA)->contains(fn (string $field) => $this->filled($field))) {
                    $validator->errors()->add('criteria', 'Choose at least one criterion to save a search.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_price.gte' => 'The maximum price must be at least the minimum price.',
            'region.exists' => 'None of our branches cover that area.',
        ];
    }
}
