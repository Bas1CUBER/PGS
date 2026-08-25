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
 * table instead of sending over SMTP. Admins can read the mailbox page when
 * the local outbox mailer is selected.
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
                'body' => self::redactSecrets(
                    (string) ($message->getHtmlBody() ?? $message->getTextBody() ?? ''),
                ),
                'created_at' => now(),
            ]);
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    /**
     * The outbox is readable by every admin for 7 days, so live credentials
     * (password-reset codes) must not be persisted verbatim.
     */
    private static function redactSecrets(string $body): string
    {
        return preg_replace_callback(
            '/\b\d{6}\b/',
            static fn (array $m): string => str_repeat('*', strlen($m[0])),
            $body,
        ) ?? $body;
    }

    public function __toString(): string
    {
        return 'outbox';
    }
}
