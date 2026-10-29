<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use App\Services\InvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationMail extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $invitationId, public string $token)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $invitation = OrganizationInvitation::find($this->invitationId);

        return $invitation && hash_equals($invitation->token_hash, hash('sha256', $this->token))
            && app(InvitationService::class)->available($invitation);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your workspace invitation')
            ->line('You have been invited to join a workspace. This link expires in seven days.')
            ->action('Review invitation', route('invitations.show', $this->token))
            ->line('If you were not expecting this invitation, you can ignore it.');
    }
}
