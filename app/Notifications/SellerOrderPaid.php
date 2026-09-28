<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerOrderPaid extends Notification implements ShouldQueue
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
        $payoutFormatted = '$'.number_format(($this->order->payout_cents ?? 0) / 100, 2);

        return (new MailMessage)
            ->subject(__('You made a sale for :title!', ['title' => $project->title]))
            ->greeting(__('Hi :name,', ['name' => $name]))
            ->line(__('Good news! Someone just purchased ":title".', ['title' => $project->title]))
            ->line(__('Your payout: :payout', ['payout' => $payoutFormatted]))
            ->action(__('View dashboard'), route('dashboard'))
            ->line(__('Funds are processed and routed to your connected Stripe account.'));
    }
}
