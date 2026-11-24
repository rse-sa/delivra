<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\BotBlockedException;
use RSE\Delivra\Exceptions\Telegram\RateLimitedException;
use RSE\Delivra\Exceptions\Telegram\TelegramMessageFailedException;
use RSE\Delivra\Telegram\Events\TelegramUpdateReceived;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Tests\TestCase;

class TelegramBotApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('delivra.telegram.webhook.enabled', true);
        $app['config']->set('delivra.telegram.webhook.secret_token', 'hook-secret');
        $app['config']->set('delivra.http.retry_delay', 0);
    }

    public function test_buttons_are_sent_as_rows(): void
    {
        $message = TextMessage::make()
            ->addUrlButton('Open', 'https://example.com')
            ->addCallbackButton('Yes', 'c:1')
            ->newRow()
            ->addUrlButton('Help', 'https://example.com/help');

        $this->assertSame([
            'inline_keyboard' => [
                [['text' => 'Open', 'url' => 'https://example.com'], ['text' => 'Yes', 'callback_data' => 'c:1']],
                [['text' => 'Help', 'url' => 'https://example.com/help']],
            ],
        ], json_decode($message->toArray()['reply_markup'], true));
    }

    public function test_new_row_without_buttons_adds_no_empty_row(): void
    {
        $message = TextMessage::make()->newRow()->addUrlButton('Open', 'https://example.com')->newRow();

        $this->assertSame([[['text' => 'Open', 'url' => 'https://example.com']]], $message->getButtons());
    }

    public function test_forbidden_answer_throws_bot_blocked_without_retrying(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        try {
            telegram()->send(telegram()->text('Hi')->to('77'));
            $this->fail('No exception thrown');
        } catch (BotBlockedException $e) {
            $this->assertInstanceOf(TelegramMessageFailedException::class, $e);
            $this->assertSame('77', $e->getChatId());
        }

        Http::assertSentCount(1);
    }

    public function test_rate_limited_answer_carries_retry_after(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 7]], 429)]);

        try {
            telegram()->send(telegram()->text('Hi')->to('77'));
            $this->fail('No exception thrown');
        } catch (RateLimitedException $e) {
            $this->assertSame(7, $e->retryAfter);
        }
    }

    public function test_server_errors_are_retried(): void
    {
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502)
            ->push(['ok' => true, 'result' => ['message_id' => 3]])]);

        $response = telegram()->send(telegram()->text('Hi')->to('77'));

        $this->assertSame(3, $response->getMessageId());
        Http::assertSentCount(2);
    }

    public function test_answer_callback_query_and_edit_message(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

        telegram()->answerCallbackQuery('cb-1', 'Done', 'other-token');
        telegram()->editMessageText('77', 9, 'Linked');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/botother-token/answerCallbackQuery'
            && $request['callback_query_id'] === 'cb-1');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bottest-token/editMessageText'
            && $request['message_id'] === 9
            && $request['reply_markup'] === '{"inline_keyboard":[]}');
    }

    public function test_get_updates_returns_update_objects(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            ['update_id' => 4, 'message' => ['chat' => ['id' => 1, 'type' => 'private'], 'text' => '/start']],
        ]])]);

        $updates = telegram()->getUpdates(offset: 4, timeout: 0);

        $this->assertCount(1, $updates);
        $this->assertSame('start', $updates[0]->command());
    }

    public function test_webhook_rejects_a_wrong_secret(): void
    {
        $this->postJson('delivra/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'nope'])
            ->assertForbidden();
    }

    public function test_webhook_fires_the_event_once_per_update(): void
    {
        Event::fake([TelegramUpdateReceived::class]);

        $payload = ['update_id' => 50, 'message' => ['chat' => ['id' => 77, 'type' => 'private'], 'text' => '/start abc']];

        $this->postJson('delivra/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();
        $this->postJson('delivra/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();

        Event::assertDispatchedTimes(TelegramUpdateReceived::class, 1);
        Event::assertDispatched(TelegramUpdateReceived::class, fn ($event) => $event->update->commandArgument() === 'abc'
            && $event->token === 'test-token');
    }
}
