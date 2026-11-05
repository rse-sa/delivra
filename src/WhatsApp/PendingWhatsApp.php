<?php

namespace RSE\Delivra\WhatsApp;

use RSE\Delivra\Exceptions\WhatsApp\InvalidMessageException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppBatchException;
use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Messages\TextMessage;
use RSE\Delivra\WhatsApp\Templates\Template;
use Throwable;

class PendingWhatsApp
{
    /**
     * @param  Account|null  $account  null lets each message pick its account
     */
    public function __construct(
        protected WhatsAppManager $manager,
        protected ?Account $account = null,
        protected array $recipients = [],
    ) {}

    public function to(string|array $recipients): static
    {
        $this->recipients = (array) $recipients;

        return $this;
    }

    public function text(string $body, bool $previewUrl = false): WhatsAppResponse|WhatsAppBatchResponse|null
    {
        return $this->send(TextMessage::make($body)->previewUrl($previewUrl));
    }

    /**
     * @param  array<int|string, mixed>  $body
     */
    public function template(string $name, array $body = [], ?string $language = null): WhatsAppResponse|WhatsAppBatchResponse|null
    {
        return $this->send(TemplateMessage::make($name, $language)->body($body));
    }

    /**
     * One recipient returns a response (or throws). Many recipients return a batch,
     * and throw WhatsAppBatchException when any of them failed.
     */
    public function send(Message|Template $message): WhatsAppResponse|WhatsAppBatchResponse|null
    {
        $message    = $this->manager->prepare($message);
        $account    = $this->manager->accountFor($message, $this->account);
        $recipients = $this->recipients ?: $message->getRecipients();

        if ($recipients === []) {
            throw new InvalidMessageException('No WhatsApp recipients specified.');
        }

        if (count($recipients) === 1) {
            return $this->manager->sendTo($account, reset($recipients), $message);
        }

        $batch = new WhatsAppBatchResponse;

        foreach ($recipients as $recipient) {
            try {
                if ($response = $this->manager->sendTo($account, $recipient, $message)) {
                    $batch->addSuccess($recipient, $response);
                }
            } catch (Throwable $e) {
                $batch->addFailure($recipient, $e);
            }
        }

        if ($batch->hasFailures()) {
            throw new WhatsAppBatchException($batch);
        }

        return $batch;
    }
}
