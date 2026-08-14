<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * LAN mail transport: stores outgoing messages in the `outbox_mails`
 * table instead of sending over SMTP (no mail server on the LAN host).
 * Admins read the mailbox page; password reset links work end-to-end.
 */
final class OutboxTransport implements TransportInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): SentMessage
    {
        if ($message instanceof Email) {
            $to = '';
            foreach ($message->getTo() as $address) {
                $to = $address->getAddress();
                break;
            }

            DB::table('outbox_mails')->insert([
                'to_email' => $to,
                'subject' => (string) $message->getSubject(),
                'body' => $message->getHtmlBody() ?? $message->getTextBody(),
                'created_at' => now(),
            ]);
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'outbox';
    }
}
