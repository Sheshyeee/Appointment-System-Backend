<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    // GET /patients
    public function index(Request $request)
    {
        $query = User::where('role', 'patient')
            ->withCount(['appointments' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }])
            ->withMax(['appointments' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'date');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('name')->get()->map(function ($patient) {
            return [
                'id'              => $patient->id,
                'name'            => $patient->name,
                'email'           => $patient->email,
                'phone'           => $patient->phone,
                'date_of_birth'   => $patient->date_of_birth,
                'address'         => $patient->address,
                'medical_notes'   => $patient->medical_notes,
                'appointments_count' => $patient->appointments_count,
                'last_visit'      => $patient->appointments_max_date,
            ];
        });

        return response()->json([
            'patients' => $patients,
        ]);
    }

    // POST /patients
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'phone'         => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string',
            'password'      => 'nullable|string|min:8',
        ]);

        $patient = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'phone'         => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'address'       => $validated['address'] ?? null,
            'medical_notes' => $validated['medical_notes'] ?? null,
            // If no password is set, generate a random one — the patient
            // can reset it via a "forgot password" flow if one exists.
            'password'      => $validated['password'] ?? Str::random(16),
            'role'          => 'patient',
        ]);

        return response()->json([
            'message' => 'Patient created successfully.',
            'patient' => $patient,
        ], 201);
    }

    // PUT /patients/{id}/update
    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Only an admin can edit patients.');
        }
        $patient = User::where('role', 'patient')->findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($patient->id)],
            'phone'         => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string',
        ]);

        $patient->update($validated);

        return response()->json([
            'message' => 'Patient updated successfully.',
            'patient' => $patient,
        ]);
    }

    // DELETE /patients/{id}
    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Only an admin can edit patients.');
        }
        $patient = User::where('role', 'patient')->findOrFail($id);
        $patient->delete();

        return response()->json([
            'message' => 'Patient deleted successfully.',
        ]);
    }

    public function show($id)
    {
        $patient = User::where('role', 'patient')->findOrFail($id);

        $appointments = Appointment::with(['service', 'dentist'])
            ->where('user_id', $patient->id)
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id'            => $appointment->id,
                    'service_name'  => $appointment->service->name,
                    'dentist_name'  => $appointment->dentist->full_name,
                    'date'          => $appointment->date->format('D, M j, Y'),
                    'date_raw'      => $appointment->date->format('Y-m-d'),
                    'time'          => $appointment->time,
                    'status'        => $appointment->status,
                ];
            });

        return response()->json([
            'patient' => [
                'id'            => $patient->id,
                'name'          => $patient->name,
                'email'         => $patient->email,
                'phone'         => $patient->phone,
                'date_of_birth' => $patient->date_of_birth,
                'address'       => $patient->address,
                'medical_notes' => $patient->medical_notes,
            ],
            'appointments' => $appointments,
        ]);
    }

    // POST /patients/{id}/notes
    public function addNote(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $patient = User::where('role', 'patient')->findOrFail($id);

        $entry = '[' . now()->format('M j, Y g:i A') . '] ' . trim($validated['note']);
        $patient->medical_notes = $patient->medical_notes
            ? $patient->medical_notes . "\n\n" . $entry
            : $entry;
        $patient->save();

        return response()->json([
            'message'       => 'Note added successfully.',
            'medical_notes' => $patient->medical_notes,
        ]);
    }
}
