<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewClassworkPosted extends Mailable
{
    use Queueable, SerializesModels;

    public $studentName;
    public $teacherName;
    public $subjectName;
    public $classworkType;
    public $classworkTitle;
    public $deadline;
    public $classworkLink;

    /**
     * Create a new message instance.
     */
    public function __construct($studentName, $teacherName, $subjectName, $classworkType, $classworkTitle, $deadline, $classworkLink)
    {
        $this->studentName = $studentName;
        $this->teacherName = $teacherName;
        $this->subjectName = $subjectName;
        $this->classworkType = ucfirst($classworkType);
        $this->classworkTitle = $classworkTitle;
        $this->deadline = $deadline;
        $this->classworkLink = $classworkLink;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New ' . $this->classworkType . ' in ' . $this->subjectName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new_classwork',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
