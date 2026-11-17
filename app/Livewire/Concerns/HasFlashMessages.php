<?php

namespace App\Livewire\Concerns;

trait HasFlashMessages
{
    /**
     * Clear every flash property this component exposes. Called by the
     * notice stack after the 12-second fade so the component re-renders
     * with no messages and the notice can unmount.
     */
    public function clearFlashMessages(): void
    {
        foreach (['error', 'flash', 'success', 'warning', 'info'] as $prop) {
            if (property_exists($this, $prop)) {
                $this->{$prop} = null;
            }
        }
    }
}