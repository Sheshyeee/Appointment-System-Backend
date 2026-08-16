<!DOCTYPE html>
<html>

<body style="font-family: Arial, sans-serif; color: #333;">
    <h2>Hi {{ $appointment->full_name }},</h2>

    <p>This is a friendly reminder about your upcoming appointment.</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 400px; margin-top: 16px;">
        <tr>
            <td style="padding: 6px 0; color: #666;">Service</td>
            <td style="padding: 6px 0; font-weight: bold;">{{ $appointment->service->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #666;">Dentist</td>
            <td style="padding: 6px 0; font-weight: bold;">{{ $appointment->dentist->full_name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #666;">Date</td>
            <td style="padding: 6px 0; font-weight: bold;">{{ $appointment->date->format('D, M j, Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #666;">Time</td>
            <td style="padding: 6px 0; font-weight: bold;">{{ $appointment->time }}</td>
        </tr>
    </table>

    <p style="margin-top: 20px;">If you need to reschedule or cancel, please contact the clinic directly.</p>

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>

</html>
