<?php

namespace App\Mail;

use App\Models\SubmittedQuestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewSubmissionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubmittedQuestion $submittedQuestion,
        public ?User $admin = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Question Submission Awaiting Review (#{$this->submittedQuestion->id})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-new-submission',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
