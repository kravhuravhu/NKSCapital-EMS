<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class EmployeeNumberService
{
    /**
     * Generate a unique employee number in the format EN{yy}{4char}.
     * The two digit year keeps numbers short and recognisable, and the four
     * characters give enough entropy that fifty retries is more than enough
     * for hundreds of simultaneous hires.
     */
    public function generate(): string
    {
        $prefix = 'EN' . now()->format('y');

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = $prefix . strtoupper(Str::random(4));

            if (!User::where('employee_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a unique employee number after 50 attempts.');
    }
}