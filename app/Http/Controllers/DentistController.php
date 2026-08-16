<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DentistController extends Controller
{
    // GET /api/dentists
    public function index()
    {
        return response()->json(Dentist::all());
    }

    public function staff()
    {
        $staffs = User::where('role', 'staff')->latest()->get();

        return response()->json(['staffs' => $staffs]);
    }

    public function destroy($id)
    {
        $dentist = Dentist::findOrFail($id);
        $dentist->delete();

        return response()->json([
            'message' => 'Dentist deleted successfully.',
        ]);
    }

    public function updateStaff(Request $request, $id)
    {
        $staff = User::findOrFail($id);

        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
        ]);

        if (isset($validated['name'])) $staff->name = $validated['name'];
        if (isset($validated['email'])) $staff->email = $validated['email'];

        $staff->save();

        return response()->json(['message' => 'Staff updated successfully.']);
    }

    public function destroyStaff($id)
    {
        $staff = User::findOrFail($id);
        $staff->delete();

        return response()->json(['message' => 'Staff deleted successfully.']);
    }

    // POST /api/dentists
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'rating' => 'nullable|numeric|min:0|max:5',
            'years_experience' => 'nullable|integer|min:0',
            'education' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'photo_url' => 'nullable|string|max:255',
        ]);

        $dentist = Dentist::create($validated);

        return response()->json($dentist, 201);
    }

    public function updateDentist(Request $request, $id)
    {
        $dentist = Dentist::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'rating' => 'nullable|numeric|min:0|max:5',
            'years_experience' => 'nullable|integer|min:0',
            'education' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'photo_url' => 'nullable|string|max:255',
        ]);

        $dentist->update($validated);

        return response()->json($dentist, 200);
    }

    public function registerUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        return response()->json([
            'user' => $user
        ], 201);
    }
}
