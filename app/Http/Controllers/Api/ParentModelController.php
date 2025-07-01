<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use Illuminate\Http\Request;

class ParentModelController extends Controller
{
    public function index()
    {
        return ParentModel::with(['group', 'card'])->get();
    }

    public function show($id)
    {
        return ParentModel::with(['group', 'card'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive,blocked',
            'phone' => 'required|string|max:20',
            'code' => 'nullable|string|max:255',
            'group_id' => 'required|exists:garden_groups,id',
            'card_id' => 'required|exists:cards,id',
        ]);

        return ParentModel::create($validated);
    }

    public function update(Request $request, $id)
    {
        $parent = ParentModel::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|string|in:active,inactive,blocked',
            'phone' => 'sometimes|required|string|max:20',
            'code' => 'nullable|string|max:255',
            'group_id' => 'sometimes|required|exists:garden_groups,id',
            'card_id' => 'sometimes|required|exists:cards,id',
        ]);

        $parent->update($validated);

        return $parent;
    }

    public function destroy($id)
    {
        $parent = ParentModel::findOrFail($id);
        $parent->delete();

        return response()->json(['message' => 'Parent deleted']);
    }
}
