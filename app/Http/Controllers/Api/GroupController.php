<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;

class GroupController extends Controller
{
    public function index()
    {
        return GroupResource::collection(
            Group::public()->with('locations', 'federations')->get()
        );
    }

    /**
     * A club that asked not to be shown is answered the same way an anonymised fencer is: as far
     * as a consumer is concerned the resource does not exist. Its results and the standings they
     * feed are untouched - only the club's own page is gone.
     */
    public function show(string $public_id)
    {
        $club = Group::public()->with('locations', 'federations')->where('public_id', $public_id)->first();

        if (!$club) {
            return response()->json([
                'error' => 'Group not found.',
            ], 404);
        }

        return new GroupResource($club);
    }
}
