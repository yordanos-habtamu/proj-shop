<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : 'there';
        $project = $this->order->project;

        return (new MailMessage)
            ->subject(__('Order #:id has been refunded', ['id' => $this->order->id]))
            ->greeting(__('Hi :name,', ['name' => $name]))
            ->line(__('Your order for ":title" has been refunded.', ['title' => $project->title]))
            ->line(__('Download access for this project archive has been revoked.'))
            ->action(__('View receipt'), route('orders.show', $this->order));
    }
}
