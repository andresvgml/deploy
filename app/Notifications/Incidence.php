<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class Incidence extends Notification
{
    use Queueable;

    protected $incidence;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($incidence)
    {
        $this->incidence = $incidence;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->error()
                    ->line('An incident has occurred.')
                    ->line(substr($this->incidence->content->message ?? "", 0, 300))
                    ->action('Go to Monitor', $this->getUrl());
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return SlackMessage
     */
    public function toSlack($notifiable)
    {
        $url = $this->getUrl();
		$title = $this->incidence->type . ((isset($this->incidence->content) && isset($this->incidence->content["level"])) ? " - " . $this->incidence->content["level"] : "");

        return (new SlackMessage)
                    ->error()
                    ->content(substr($this->incidence->content["message"] ?? "Incidence detected", 0, 300))
                    ->attachment(function ($attachment) use ($url, $title) {
                        $attachment->title($title, $url)
                            ->fields([
                                'id'          => $this->incidence->id,
                                'date'        => $this->incidence->created_at ?? date('Y-m-d H:i:s'),
								'occurrences' => $this->incidence->content["occurrences"] ?? 1,
                                'tags'        => implode(', ', $this->incidence->_tags ?? []),
                            ]);
                    });

    }

    /**
     * Get the Monitor url.
     *
     * @return string
     */
    private function getUrl()
    {
        return url("/home/{$this->incidence->type}s/{$this->incidence->id}");
    }
}
