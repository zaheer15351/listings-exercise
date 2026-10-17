<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary' => $this->summary(),
            'filters' => $this->filters(),
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
