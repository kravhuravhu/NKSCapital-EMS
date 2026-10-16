<?php

namespace App\Listeners;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Auth\Events\Login;

class IssueSanctumTokenOnLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            TokenIssuer::issue($event->user);
        }
    }
}