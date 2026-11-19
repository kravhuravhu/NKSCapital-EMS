<x-guest-layout>
    @livewire('auth.qr-approve', [
        'challenge' => $challenge,
        'state' => $state,
        'targetName' => $targetName,
    ])
</x-guest-layout>