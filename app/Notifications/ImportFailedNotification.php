<?php

namespace App\Notifications;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Upload $upload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("CSV import finished with errors: {$this->upload->original_filename}")
            ->error()
            ->line("The import of \"{$this->upload->original_filename}\" finished with errors.")
            ->line("Successful: {$this->upload->successful_rows} — Failed: {$this->upload->failed_rows} of {$this->upload->total_rows} rows.")
            ->action('View Import Details', route('dashboard.show', $this->upload))
            ->line('Check the import logs for the full error details.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'upload_id' => $this->upload->id,
            'failed_rows' => $this->upload->failed_rows,
        ];
    }
}
