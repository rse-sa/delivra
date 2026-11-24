<?php

namespace RSE\Delivra\Tests\Unit\Telegram;

use RSE\Delivra\Telegram\Updates\Update;
use RSE\Delivra\Tests\TestCase;

class UpdateTest extends TestCase
{
    public function test_parses_a_start_command_in_a_private_chat(): void
    {
        $update = Update::fromArray([
            'update_id' => 10,
            'message'   => [
                'message_id' => 5,
                'from'       => ['id' => 77, 'username' => 'ahmad', 'first_name' => 'Ahmad', 'language_code' => 'ar'],
                'chat'       => ['id' => 77, 'type' => 'private'],
                'text'       => '/start abc_DEF-1',
            ],
        ]);

        $this->assertSame(10, $update->id);
        $this->assertSame(Update::MESSAGE, $update->type);
        $this->assertSame('77', $update->chatId);
        $this->assertTrue($update->isPrivate());
        $this->assertSame('ahmad', $update->username);
        $this->assertSame('ar', $update->languageCode);
        $this->assertSame(5, $update->messageId);
        $this->assertSame('start', $update->command());
        $this->assertSame('abc_DEF-1', $update->commandArgument());
    }

    public function test_plain_text_is_not_a_command(): void
    {
        $update = Update::fromArray(['update_id' => 1, 'message' => ['chat' => ['id' => 1, 'type' => 'private'], 'text' => 'K7M2XQ9P']]);

        $this->assertNull($update->command());
        $this->assertNull($update->commandArgument());
    }

    public function test_command_with_bot_mention_and_no_argument(): void
    {
        $update = Update::fromArray(['update_id' => 1, 'message' => ['chat' => ['id' => 1, 'type' => 'group'], 'text' => '/stop@MyBot']]);

        $this->assertSame('stop', $update->command());
        $this->assertNull($update->commandArgument());
        $this->assertFalse($update->isPrivate());
    }

    public function test_parses_a_callback_query(): void
    {
        $update = Update::fromArray([
            'update_id'      => 11,
            'callback_query' => [
                'id'      => 'cb-1',
                'from'    => ['id' => 77, 'first_name' => 'Ahmad'],
                'message' => ['message_id' => 9, 'chat' => ['id' => 77, 'type' => 'private']],
                'data'    => 'c:nonce',
            ],
        ]);

        $this->assertSame(Update::CALLBACK_QUERY, $update->type);
        $this->assertSame('cb-1', $update->callbackId);
        $this->assertSame('c:nonce', $update->callbackData);
        $this->assertSame('77', $update->chatId);
        $this->assertSame(9, $update->messageId);
        $this->assertNull($update->text);
    }

    public function test_detects_a_blocked_bot(): void
    {
        $update = Update::fromArray([
            'update_id'      => 12,
            'my_chat_member' => [
                'chat'            => ['id' => 77, 'type' => 'private'],
                'from'            => ['id' => 77],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ]);

        $this->assertSame(Update::MY_CHAT_MEMBER, $update->type);
        $this->assertSame('kicked', $update->memberStatus);
        $this->assertTrue($update->botWasBlocked());
    }

    public function test_unknown_update_types_are_other(): void
    {
        $update = Update::fromArray(['update_id' => 13, 'edited_message' => ['chat' => ['id' => 1]]]);

        $this->assertSame(Update::OTHER, $update->type);
        $this->assertNull($update->chatId);
    }
}
