<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ShopFollowNotification extends Notification
{
    use Queueable;

    protected $follower;
    protected $shop;
    protected $action; // 'follow' or 'unfollow'

    public function __construct($follower, $shop, $action)
    {
        $this->follower = $follower;
        $this->shop = $shop;
        $this->action = $action;
    }

    /**
     * Channels to deliver through — save in DB + broadcast (for Pusher)
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Store in database notifications table
     */
    public function toArray($notifiable)
    {
        return [
            'type' => "shop_{$this->action}",
            'shop_id' => $this->shop->id,
            'shop_name' => $this->shop->name,
            'follower_id' => $this->follower->id,
            'follower_username' => $this->follower->username,
            'message' => "{$this->follower->username} {$this->action}ed your shop \"{$this->shop->name}\"",
        ];
    }

    /**
     * Broadcast over Pusher
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
