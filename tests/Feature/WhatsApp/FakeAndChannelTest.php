<?php

namespace RSE\Delivra\Tests\Feature\WhatsApp;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RSE\Delivra\Facades\WhatsApp;
use RSE\Delivra\Tests\TestCase;
use RSE\Delivra\WhatsApp\Messages\ButtonsMessage;
use RSE\Delivra\WhatsApp\Messages\ListMessage;
use RSE\Delivra\WhatsApp\Messages\LocationMessage;
use RSE\Delivra\WhatsApp\Messages\MediaMessage;
use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\Messages\ReactionMessage;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
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

    public function test_notification_builds_messages_inline_with_helper(): void
    {
        $fake = WhatsApp::fake();

        NotificationFacade::route('whatsapp', '0500000001')->notify(new InlineOrderReady);
        NotificationFacade::route('whatsapp', '0500000001')->notify(new InlineOrderReady(withInvoice: true));

        $fake->assertSentCount(2)
            ->assertTemplateSent('order_ready', fn (TemplateMessage $message) => $message->getLanguage() === 'ar')
            ->assertSent(MediaMessage::class, fn (MediaMessage $message) => $message->type() === 'document')
            ->assertSentTo('966500000001');
    }

    public function test_manager_builders_return_messages(): void
    {
        $this->assertInstanceOf(MediaMessage::class, whatsapp()->image('https://x.test/a.png', 'Hi'));
        $this->assertSame('video', whatsapp()->video('https://x.test/a.mp4')->type());
        $this->assertSame('audio', whatsapp()->audio('media-id')->type());
        $this->assertSame('sticker', whatsapp()->sticker('media-id')->type());
        $this->assertInstanceOf(ButtonsMessage::class, whatsapp()->buttons('Pick one'));
        $this->assertInstanceOf(ListMessage::class, whatsapp()->list('Pick one', 'Menu'));
        $this->assertInstanceOf(LocationMessage::class, whatsapp()->location(24.7, 46.6, 'Office'));
        $this->assertInstanceOf(ReactionMessage::class, whatsapp()->reaction('wamid.1', '👍'));
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

class InlineOrderReady extends Notification
{
    public function __construct(public bool $withInvoice = false) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): Message
    {
        if ($this->withInvoice) {
            return whatsapp()->document('https://x.test/invoice.pdf', 'invoice.pdf', 'Your invoice');
        }

        return whatsapp()->template('order_ready', 'ar')
            ->body(['name' => 'Ali'])
            ->urlButton('A-15');
    }
}
