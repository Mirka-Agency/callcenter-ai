<?php

namespace App\Notifications\Admin;

use App\Filament\Pages\Analytics\BusinessAnalytics;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSummaryReportNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{period: string, title: string, range: string, sections: list<array{heading: string, rows: array<string, string>}>}  $report
     */
    public function __construct(public readonly array $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->report['title'].' — '.$this->report['range'])
            ->greeting($this->report['title'])
            ->line($this->report['range']);

        foreach ($this->report['sections'] as $section) {
            $message->line('**'.$section['heading'].'**');

            foreach ($section['rows'] as $label => $value) {
                $message->line($label.': '.$value);
            }
        }

        return $message->action(__('admin.reports.open_dashboard'), BusinessAnalytics::getUrl(panel: 'admin'));
    }
}
