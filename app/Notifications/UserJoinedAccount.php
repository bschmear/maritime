<?php

namespace App\Notifications;

use App\Models\Account;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserJoinedAccount extends Notification implements ShouldQueue
{
    use Queueable;

    public User $newUser;
    public Account $account;
    public string $role;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $newUser, Account $account, string $role)
    {
        $this->newUser = $newUser;
        $this->account = $account;
        $this->role = $role;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->newUser->name} joined {$this->account->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$this->newUser->name} ({$this->newUser->email}) has accepted your invitation and joined {$this->account->name} as a {$this->role}.")
            ->line("They now have access to your account and can help manage your marine sales operations.")
            ->action('Manage Account', route('accounts.show', $this->account->id))
            ->line('If you need to adjust billing or permissions, you can do so in the account management section.')
            ->salutation('Best regards, Maritime CRM Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'new_user_id' => $this->newUser->id,
            'new_user_name' => $this->newUser->name,
            'new_user_email' => $this->newUser->email,
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
            'role' => $this->role,
        ];
    }
}