<?php

  namespace App\Mail;

  use Illuminate\Bus\Queueable;
  use Illuminate\Mail\Mailable;
  use Illuminate\Queue\SerializesModels;

  class OrderMail extends Mailable
  {

    use Queueable, SerializesModels;

    public array $attachmentsData;
    public string $email_title;
    public string $fromDate;
    public string $duetoDate;

    public function __construct(array $attachmentsData, string $subjDate, string $fromDate, string $duetoDate)
    {
        $this->attachmentsData = $attachmentsData;
        $this->email_title = "Bestellung (RW Coswig) - {$subjDate}";
        $this->fromDate = $fromDate;
        $this->duetoDate = $duetoDate;
    }

    public function build()
    {
        $mail = $this->subject($this->email_title)
          ->view('mail.order');

        foreach ($this->attachmentsData as $a) {
            $mail->attachData($a['data'], $a['filename'], ['mime' => $a['mime']]);
        }

        return $mail;
    }

  }