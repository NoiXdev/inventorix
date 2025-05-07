<?php

namespace App\Listeners;

use App\Events\UserCreatingEvent;

class addNameByCreatingToUser
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserCreatingEvent $event): void
    {
        $event->user->name = $event->user->firstname . ' ' . $event->user->lastname;
    }
}
