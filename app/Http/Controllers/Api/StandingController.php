<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StandingResource;
use App\Models\Standing;

class StandingController extends Controller
{
    public function index()
    {
        return StandingResource::collection(
            Standing::query()
                ->public()
                ->with(['discipline', 'division'])
                ->get()
        );
    }

    public function show(string $public_id)
    {
        // Only the released years, and no standing at all where none of them is.
        $standing = Standing::where('public_id', $public_id)
            ->public()
            ->with(['discipline', 'division', 'seasons' => fn ($query) => $query->public()])
            ->first();

        if (!$standing) {
            return response()->json([
                'error' => 'Standing not found.',
            ], 404);
        }

        return new StandingResource($standing);
    }
}
