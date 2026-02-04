<?php

namespace RSE\Delivra\Tests\Unit\Telegram;

use RSE\Delivra\Telegram\Messages\PhotoMessage;
use RSE\Delivra\Tests\TestCase;

class PhotoMessageTest extends TestCase
{
    public function test_sets_photo_path(): void
    {
        $message = PhotoMessage::make()->photo('/path/to/photo.jpg');

        $this->assertEquals('/path/to/photo.jpg', $message->toArray()['photo']);
    }

    public function test_sets_caption(): void
    {
        $message = PhotoMessage::make()->caption('Check this out');

        $this->assertEquals('Check this out', $message->toArray()['caption']);
    }

    public function test_to_array(): void
    {
        $message = PhotoMessage::make()
            ->photo('/path/to/photo.jpg')
            ->caption('Test');

        $array = $message->toArray();

        $this->assertEquals('/path/to/photo.jpg', $array['photo']);
        $this->assertEquals('Test', $array['caption']);
    }
}
