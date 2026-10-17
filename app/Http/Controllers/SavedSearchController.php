<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every query starts from the signed-in user, so a buyer can only ever see or
 * delete their own searches; someone else's id is simply not found.
 */
class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        $savedSearches = $request->user()->savedSearches()->orderByDesc('id')->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
        ]);
    }

    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $request->user()->savedSearches()->create($request->validated());

        return to_route('saved-searches.index');
    }

    public function destroy(Request $request, int $savedSearch): RedirectResponse
    {
        $request->user()->savedSearches()->findOrFail($savedSearch)->delete();

        return to_route('saved-searches.index');
    }
}
