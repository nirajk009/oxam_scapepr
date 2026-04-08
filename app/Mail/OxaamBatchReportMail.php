<?php

namespace App\Mail;

use App\Models\OxaamBatchReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OxaamBatchReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public OxaamBatchReport $report,
        public array $summary,
        public string $subjectLine,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.oxaam-batch-report',
        );
    }

    public function attachments(): array
    {
        if (! $this->report->csv_path || ! is_file($this->report->csv_path)) {
            return [];
        }

        return [
            Attachment::fromPath($this->report->csv_path)
                ->as(basename($this->report->csv_path))
                ->withMime('text/csv'),
        ];
    }
}
