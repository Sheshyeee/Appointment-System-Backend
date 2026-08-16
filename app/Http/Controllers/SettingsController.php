<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    private function defaultWorkingHours(): array
    {
        $weekday = ['enabled' => true, 'open' => '8:00 AM', 'close' => '6:00 PM'];
        $saturday = ['enabled' => true, 'open' => '9:00 AM', 'close' => '3:00 PM'];
        $sunday = ['enabled' => false, 'open' => null, 'close' => null];

        return [
            'monday'    => $weekday,
            'tuesday'   => $weekday,
            'wednesday' => $weekday,
            'thursday'  => $weekday,
            'friday'    => $weekday,
            'saturday'  => $saturday,
            'sunday'    => $sunday,
        ];
    }

    /**
     * There's only ever one settings row. Fetch it, or create it with
     * sensible defaults the first time this is called.
     */
    private function getOrCreateSettings(): ClinicSetting
    {
        $settings = ClinicSetting::first();

        if (!$settings) {
            $settings = ClinicSetting::create([
                'name'          => 'My Dental Clinic',
                'address'       => null,
                'phone'         => null,
                'email'         => null,
                'working_hours' => $this->defaultWorkingHours(),
            ]);
        }

        return $settings;
    }

    // GET /settings
    public function show()
    {
        return response()->json([
            'settings' => $this->getOrCreateSettings(),
        ]);
    }

    // PUT /settings
    public function updateInfo(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:255',
        ]);

        $settings = $this->getOrCreateSettings();
        $settings->update($validated);

        return response()->json([
            'message'  => 'Clinic information updated successfully.',
            'settings' => $settings,
        ]);
    }

    // PUT /settings/hours
    public function updateHours(Request $request)
    {
        $validated = $request->validate([
            'working_hours' => 'required|array',
        ]);

        $hours = $validated['working_hours'];

        // Normalize/validate per-day shape ourselves rather than trying to
        // express this nesting in Laravel's dot-notation array rules.
        $clean = [];
        foreach (self::DAYS as $day) {
            $entry = $hours[$day] ?? ['enabled' => false, 'open' => null, 'close' => null];
            $enabled = (bool) ($entry['enabled'] ?? false);

            $clean[$day] = [
                'enabled' => $enabled,
                'open'    => $enabled ? ($entry['open'] ?? null) : null,
                'close'   => $enabled ? ($entry['close'] ?? null) : null,
            ];
        }

        $settings = $this->getOrCreateSettings();
        $settings->update(['working_hours' => $clean]);

        return response()->json([
            'message'  => 'Working hours updated successfully.',
            'settings' => $settings,
        ]);
    }
}
