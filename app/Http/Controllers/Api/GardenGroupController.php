<?php

namespace App\Http\Controllers\Api;

use App\Models\GardenGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GardenGroupController extends Controller
{
    public function index()
    {
        return GardenGroup::with('garden')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'garden_id' => 'required|exists:gardens,id',
        ]);

        return GardenGroup::create($validated);
    }

    public function show($id)
    {
        return GardenGroup::with('garden')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $group = GardenGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'garden_id' => 'sometimes|required|exists:gardens,id',
        ]);

        $group->update($validated);

        return $group;
    }

    public function destroy($id)
    {
        $group = GardenGroup::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
