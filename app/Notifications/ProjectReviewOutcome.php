<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectReviewOutcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public bool $approved,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verdict = $this->approved ? 'approved' : 'rejected';
        $subject = "Your project \"{$this->project->title}\" was {$verdict}";
        $recipient = $notifiable instanceof User ? $notifiable->name : 'there';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hi '.$recipient.',')
            ->line("Your project \"{$this->project->title}\" was {$verdict} by our review team.");

        if ($this->notes !== null && $this->notes !== '') {
            $message->line('Reviewer notes: '.$this->notes);
        }

        if ($this->approved) {
            return $message->line('The project is now live on the marketplace.');
        }

        return $message->line('You can edit the project and submit it again.');
    }
}
