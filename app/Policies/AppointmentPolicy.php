<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
  /**
   * Staff/admin can view any appointment.
   * A patient may only view an appointment that belongs to them.
   */
  public function view(User $user, Appointment $appointment): bool
  {
    if (in_array($user->role, ['admin', 'staff'], true)) {
      return true;
    }

    return $appointment->user_id === $user->id;
  }

  /**
   * Staff/admin can update any appointment (status, notes, etc).
   * A patient may only update their own appointment — enforce which
   * fields they're allowed to change (e.g. not `status`) inside the
   * controller/form request, this policy only checks ownership.
   */
  public function update(User $user, Appointment $appointment): bool
  {
    if (in_array($user->role, ['admin', 'staff'], true)) {
      return true;
    }

    return $appointment->user_id === $user->id;
  }

  /**
   * Staff/admin can cancel any appointment.
   * A patient may only cancel their own.
   */
  public function delete(User $user, Appointment $appointment): bool
  {
    if (in_array($user->role, ['admin', 'staff'], true)) {
      return true;
    }

    return $appointment->user_id === $user->id;
  }
}
