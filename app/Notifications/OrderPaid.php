<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaid extends Notification implements ShouldQueue
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
            ->subject(__('Your purchase of :title is confirmed', ['title' => $project->title]))
            ->greeting(__('Hi :name,', ['name' => $name]))
            ->line(__('Payment for ":title" was confirmed.', ['title' => $project->title]))
            ->line(__('Order :id — your receipt and download link are ready.', ['id' => $this->order->id]))
            ->action(__('View receipt'), route('orders.show', $this->order))
            ->line(__('Download links are signed and expire, so request a fresh one from your receipt whenever you need it.'));
    }
}
