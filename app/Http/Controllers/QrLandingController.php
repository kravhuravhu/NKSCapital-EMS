<?php

namespace App\Http\Controllers;

use App\Models\QrLoginChallenge;
use App\Support\Api;
use Illuminate\Support\Facades\Auth;

class QrLandingController extends Controller
{
    /**
     * Phone side of the QR sign in. Guests are sent to the login page and
     * returned here afterwards. Signed in users only see the approve prompt
     * when the challenge was created for their own account.
     */
    public function __invoke(string $challenge)
    {
        $path = '/auth/qr/' . $challenge;

        if (!Auth::check()) {
            return redirect()->route('login', ['redirect' => $path]);
        }

        if (!Api::token()) {
            Auth::guard('web')->logout();

            return redirect()->route('login', ['redirect' => $path]);
        }

        $qr = QrLoginChallenge::with('user')->where('challenge', $challenge)->first();

        $state = 'ready';
        $name = '';

        if (!$qr || $qr->user_id === null || !$qr->user) {
            $state = 'invalid';
        } elseif ((int) Auth::id() !== (int) $qr->user_id) {
            $state = 'mismatch';
        } elseif (!$qr->isPending() || $qr->isExpired()) {
            $state = 'invalid';
        } else {
            $name = (string) ($qr->user->full_name ?: $qr->user->email);
        }

        return view('auth.qr-approve', [
            'challenge' => $challenge,
            'state' => $state,
            'targetName' => $name,
        ]);
    }
}