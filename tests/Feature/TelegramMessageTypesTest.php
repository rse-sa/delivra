<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\Telegram\BotBlockedException;
use RSE\Delivra\Tests\TestCase;

/**
 * Every message type sends through the manager: same events, failures, uploads and default chat.
 */
class TelegramMessageTypesTest extends TestCase
{
    protected string $file;

    protected mixed $answer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'tg') . '.jpg';
        file_put_contents($this->file, 'image-bytes');

        // a closure, so a test can swap the answer (Http::fake stubs added later never win)
        Http::fake(fn () => $this->answer ?? Http::response(['ok' => true, 'result' => ['message_id' => 8]]));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function test_local_photo_is_uploaded_with_its_fields(): void
    {
        Event::fake([DelivraMessageSent::class]);

        $response = telegram()->photo($this->file)->caption('Look')->addUrlButton('Open', 'https://x.test')->to('77')->send();

        $this->assertSame(8, $response->getMessageId());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendPhoto')
            && $request->isMultipart()
            && $request->hasFile('photo', 'image-bytes', basename($this->file))
            && collect($request->data())->firstWhere('name', 'caption')['contents'] === 'Look'
            && collect($request->data())->firstWhere('name', 'chat_id')['contents'] === '77'
            && str_contains(collect($request->data())->firstWhere('name', 'reply_markup')['contents'], 'x.test'));
        Event::assertDispatched(DelivraMessageSent::class, fn ($event) => $event->body === 'Look');
    }

    public function test_photo_url_or_file_id_goes_as_a_field(): void
    {
        telegram()->photo('https://cdn.test/a.jpg')->to('77')->send();
        telegram()->photo('AgACAgQAAxkBAAI')->to('77')->send();

        Http::assertSent(fn ($request) => ! $request->isMultipart() && $request['photo'] === 'https://cdn.test/a.jpg');
        Http::assertSent(fn ($request) => $request['photo'] === 'AgACAgQAAxkBAAI');
    }

    public function test_video_and_document_upload_their_thumbnail_too(): void
    {
        telegram()->video($this->file)->thumbnail($this->file)->duration(30)->to('77')->send();
        telegram()->document('https://cdn.test/a.pdf')->thumbnail($this->file)->to('77')->send();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendVideo')
            && $request->hasFile('video') && $request->hasFile('thumbnail')
            && collect($request->data())->firstWhere('name', 'duration')['contents'] === '30');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendDocument')
            && $request->hasFile('thumbnail')
            && collect($request->data())->firstWhere('name', 'document')['contents'] === 'https://cdn.test/a.pdf');
    }

    public function test_poll_keeps_false_and_zero_values(): void
    {
        telegram()->poll('Pick one', ['A', 'B'])->type('quiz')->correctOption(0)->to('77')->send();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendPoll')
            && $request['is_anonymous'] === false
            && $request['correct_option_id'] === 0
            && $request['options'] === '["A","B"]');
    }

    public function test_location_and_contact_use_the_default_chat_and_token(): void
    {
        telegram()->location(24.7, 46.6)->send();
        telegram()->contact('966500000000', 'Sara')->send();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bottest-token/sendLocation' && $request['chat_id'] === 'test-chat-id');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendContact') && $request['first_name'] === 'Sara');
    }

    public function test_failures_are_typed_for_every_type(): void
    {
        $this->answer = Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403);

        $this->expectException(BotBlockedException::class);

        telegram()->photo('https://cdn.test/a.jpg')->to('77')->send();
    }

    public function test_a_sending_listener_can_cancel_a_direct_send(): void
    {
        Event::listen(DelivraMessageSending::class, fn () => false);

        $this->assertNull(telegram()->photo('https://cdn.test/a.jpg')->to('77')->send());

        Http::assertNothingSent();
    }

    public function test_notification_channel_uploads_files_and_passes_the_notifiable(): void
    {
        Event::fake([DelivraMessageSent::class]);
        $file = $this->file;

        NotificationFacade::route('telegram', '77')->notify(new class($file) extends Notification
        {
            public function __construct(public string $file) {}

            public function via($notifiable): array
            {
                return ['telegram'];
            }

            public function toTelegram($notifiable)
            {
                return telegram()->document($this->file)->caption('Invoice');
            }
        });

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendDocument') && $request->hasFile('document'));
        Event::assertDispatched(DelivraMessageSent::class, fn ($event) => $event->notifiable !== null && $event->notification !== null);
    }
}
