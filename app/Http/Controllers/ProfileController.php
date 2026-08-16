<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // GET /profile
    public function show(Request $request)
    {
        return response()->json([
            'user' => Auth::user(),
        ]);
    }
    // PUT /profile
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'date_of_birth' => 'nullable|date',
            'phone'         => 'nullable|string|max:30',
            'address'       => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
