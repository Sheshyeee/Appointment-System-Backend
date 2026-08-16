<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // GET /services
    public function index()
    {
        // withCount('appointments') adds an `appointments_count` field to
        // each service in a single query (no N+1). Requires Service to
        // have an `appointments()` hasMany relation defined on the model —
        // add it if it isn't there yet:
        //
        //   public function appointments()
        //   {
        //       return $this->hasMany(Appointment::class);
        //   }
        $services = Service::withCount('appointments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'services' => $services,
        ]);
    }

    // POST /services
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:0',
            'icon'        => 'required|string|max:50',
        ]);

        $service = Service::create($validated);

        return response()->json([
            'message' => 'Service created successfully.',
            'service' => $service,
        ], 201);
    }

    // GET /services/{id}
    public function show($id)
    {
        $service = Service::withCount('appointments')->findOrFail($id);

        return response()->json([
            'service' => $service,
        ]);
    }

    // PUT /services/{id}/update
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|required|numeric|min:0',
            'duration'    => 'sometimes|required|integer|min:0',
            'icon'        => 'sometimes|required|string|max:50',
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully.',
            'service' => $service,
        ]);
    }

    // DELETE /services/{id}
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
        ]);
    }
}
