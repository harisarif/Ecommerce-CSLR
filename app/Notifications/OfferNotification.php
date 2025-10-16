<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;


class OfferNotification extends Notification
{
    use Queueable;

    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Notification channels
     */
    public function via($notifiable)
    {
        // store in DB and broadcast in real time
        return ['database', 'broadcast'];
    }

    /**
     * Store in database
     */
    public function toDatabase($notifiable)
    {
        return $this->payload;
    }

    /**
     * Broadcast over websockets/log driver
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->payload);
    }

    /**
     * (Optional) Define broadcast channel
     * Will broadcast on private channel "private-App.Models.User.{id}"
     */
}
