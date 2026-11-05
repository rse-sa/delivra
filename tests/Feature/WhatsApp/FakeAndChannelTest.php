<?php

namespace RSE\Delivra\Tests\Feature\WhatsApp;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RSE\Delivra\Facades\WhatsApp;
use RSE\Delivra\Tests\TestCase;
use RSE\Delivra\WhatsApp\Messages\TextMessage;
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Template;
use RSE\Delivra\WhatsApp\Testing\SentMessage;

class FakeAndChannelTest extends TestCase
{
    public function test_fake_records_class_templates(): void
    {
        Http::fake();
        $fake = WhatsApp::fake();

        whatsapp()->account('sales')->to('0500000001')->send(new PaymentReminder(250));

        $fake->assertTemplateSent(PaymentReminder::class, fn (PaymentReminder $template) => $template->amount === 250)
            ->assertTemplateSent('payment_reminder')
            ->assertSent(PaymentReminder::class, fn ($template, SentMessage $sent) => $sent->account === 'sales' && $sent->phoneNumberId === '2001')
            ->assertSentTo('966500000001')
            ->assertSentCount(1);

        Http::assertNothingSent();
    }

    public function test_fake_assert_nothing_sent(): void
    {
        WhatsApp::fake()->assertNothingSent()->assertNotSent(TextMessage::class);
    }

    public function test_notification_channel_uses_route_and_skips_blocked(): void
    {
        $fake = WhatsApp::fake();
        whatsapp()->canSendUsing(fn (string $recipient) => $recipient !== '966500000002');

        NotificationFacade::route('whatsapp', ['966500000001', '966500000002'])->notify(new PaymentDue);

        $fake->assertSentCount(1)->assertSentTo('966500000001', PaymentReminder::class);
    }

    public function test_notification_channel_skips_when_no_route(): void
    {
        $fake = WhatsApp::fake();

        (new AnonymousNotifiable)->notify(new PaymentDue);

        $fake->assertNothingSent();
    }
}

class PaymentReminder extends Template
{
    protected ?string $language = 'ar';

    public function __construct(public int $amount) {}

    public function body(): array
    {
        return ['amount' => $this->amount];
    }

    public function buttons(): array
    {
        return [Button::quickReply('wa_unsubscribe')];
    }
}

class PaymentDue extends Notification
{
    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): PaymentReminder
    {
        return new PaymentReminder(100);
    }
}
