<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
  public function send(string $phone, string $message): bool
  {
    $phone = $this->normalizePhone($phone);

    if (!$phone) {
      Log::warning("SMS not sent — invalid phone number format: {$phone}");
      return false;
    }

    try {
      $response = Http::asForm()->post('https://api.semaphore.co/api/v4/messages', [
        'apikey'     => config('services.semaphore.api_key'),
        'number'     => $phone,
        'message'    => $message,
        'sendername' => config('services.semaphore.sender_name'),
      ]);

      if ($response->failed()) {
        Log::error('SMS send failed: ' . $response->body());
        return false;
      }

      return true;
    } catch (\Exception $e) {
      Log::error('SMS send exception: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Normalize common PH phone formats to what Semaphore expects
   * (09XXXXXXXXX). Accepts 09XXXXXXXXX, +639XXXXXXXXX, or 639XXXXXXXXX.
   */
  private function normalizePhone(string $phone): ?string
  {
    $digits = preg_replace('/\D/', '', $phone);

    if (str_starts_with($digits, '63') && strlen($digits) === 12) {
      return '0' . substr($digits, 2);
    }

    if (str_starts_with($digits, '09') && strlen($digits) === 11) {
      return $digits;
    }

    return null;
  }
}
