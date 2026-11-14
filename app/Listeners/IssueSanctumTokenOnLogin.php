<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Session;

class IssueSanctumTokenOnLogin
{
    public function handle(Login $event): void
    {
        if (!$event->user instanceof User) {
            return;
        }

        // Our Livewire login already stored a token in the session after the
        // OTP step. Avoid issuing a second token in that case.
        if (Session::has('api_token')) {
            return;
        }

        TokenIssuer::issue($event->user);
    }
}