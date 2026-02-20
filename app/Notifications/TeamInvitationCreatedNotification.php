<?php declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $rawToken,
        private readonly Team $team,
        private readonly User $inviter,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = rtrim(config('app.url'), '/') . '/invitations/accept/' . urlencode($this->rawToken)
            . '?team=' . urlencode((string) $this->team->id);

        $inviterName = trim((string) ($this->inviter->name ?? ''));
        if ($inviterName === '') {
            $inviterName = (string) $this->inviter->email;
        }

        return (new MailMessage())
            ->subject('You are invited to join team ' . $this->team->name)
            ->greeting('Hello,')
            ->line('You have been invited to join the team "' . $this->team->name . '".')
            ->line('Invited by: ' . $inviterName)
            ->line('Role: ' . (string) $this->invitation->role)
            ->line('This invitation expires at: ' . optional($this->invitation->expires_at)?->toDateTimeString())
            ->action('Accept Invitation', $acceptUrl)
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
