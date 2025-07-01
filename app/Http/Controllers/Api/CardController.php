<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;

class CardController extends Controller
{
    // ყველა ბარათის წამოღება
    public function index()
    {
        return Card::with('group')->get();
    }

    // ერთი ბარათის დეტალები
    public function show($id)
    {
        return Card::with('group')->findOrFail($id);
    }

    // ბარათის შექმნა
    public function store(Request $request)
    {
        $validated = $request->validate([
            'child_first_name' => 'required|string|max:255',
            'child_last_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'parent_first_name' => 'required|string|max:255',
            'parent_last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'status' => 'required|string|in:pending,active,inactive',
            'group_id' => 'required|exists:garden_groups,id',
            'parent_code' => 'nullable|string|max:255',
        ]);

        $card = Card::create($validated);

        return response()->json($card, 201);
    }

    // განახლება
    public function update(Request $request, $id)
    {
        $card = Card::findOrFail($id);

        $validated = $request->validate([
            'child_first_name' => 'sometimes|required|string|max:255',
            'child_last_name' => 'sometimes|required|string|max:255',
            'father_name' => 'sometimes|required|string|max:255',
            'parent_first_name' => 'sometimes|required|string|max:255',
            'parent_last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'status' => 'sometimes|required|string|in:pending,active,inactive',
            'group_id' => 'sometimes|required|exists:garden_groups,id',
            'parent_code' => 'nullable|string|max:255',
        ]);

        $card->update($validated);

        return response()->json($card);
    }

    // წაშლა
    public function destroy($id)
    {
        $card = Card::findOrFail($id);
        $card->delete();

        return response()->json(['message' => 'Card deleted']);
    }
}
