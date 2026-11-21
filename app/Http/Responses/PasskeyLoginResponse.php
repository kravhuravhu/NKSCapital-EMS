<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Session;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request)
    {
        $requested = Session::pull('intended_url');

        $fallback = (is_string($requested) && str_starts_with($requested, '/') && ! str_starts_with($requested, '//'))
            ? $requested
            : route('portal.dashboard');

        $target = redirect()->intended($fallback)->getTargetUrl();

        if ($request->wantsJson()) {
            return response()->json(['redirect' => $target], 200);
        }

        return redirect($target);
    }
}
