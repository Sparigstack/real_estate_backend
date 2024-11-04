<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class LowQuantityReminderMail extends Mailable
{
    use Queueable, SerializesModels;
    public $data;
    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
      
        return $this->view('emails.lowStockMail')
            ->from('tithishah.sprigstack@gmail.com')
            ->subject('Inventory Low-Quantity Alert')
            ->with(['userName' => $this->data['userName'],'inventoryName' => $this->data['inventoryName'],'currentStock'=>$this->data['currentStock'],'reminderStock'=>$this->data['currentStock']]);
    }
}
