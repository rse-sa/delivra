<?php

namespace RSE\Delivra\Tests\Feature\InApp;

use Illuminate\Support\Carbon;
use RSE\Delivra\Exceptions\InApp\InvalidMessageException;
use RSE\Delivra\Facades\InApp;
use RSE\Delivra\InApp\Messages\InAppButton;
use RSE\Delivra\InApp\Messages\InAppMessage;

class InAppMessageTest extends InAppTestCase
{
    public function test_it_builds_the_versioned_payload(): void
    {
        $task = Task::query()->create(['title' => 'Roof']);

        $payload = json_decode(json_encode(InAppMessage::make('notifications.task.title', ['task' => 'Roof'])
            ->body('notifications.task.body', [
                'due'    => InApp::date('2026-11-30'),
                'status' => InApp::trans('tasks.status.open'),
                'files'  => InApp::choice('notifications.files', 2),
            ])
            ->icon('fa-solid fa-list-check')
            ->color('warning')
            ->route('tasks.show', ['task' => $task])
            ->button('notifications.actions.open', fn (InAppButton $button) => $button->url('/tasks/1')->color('primary')->icon('fa-eye'))
            ->button('notifications.actions.open', fn (InAppButton $button) => $button->post()->params(['step' => 2])->markAsRead()->key('approve'))
            ->data(['priority' => 'high'])
            ->data(['rank' => 1])), true);

        $this->assertSame(1, $payload['v']);
        $this->assertSame(['t' => 'trans', 'k' => 'notifications.task.title', 'p' => ['task' => 'Roof']], $payload['title']);
        $this->assertSame(['t' => 'date', 'v' => '2026-11-30'], $payload['body']['p']['due']);
        $this->assertSame(['t' => 'choice', 'k' => 'notifications.files', 'c' => 2, 'p' => []], $payload['body']['p']['files']);
        $this->assertSame(['route' => 'tasks.show', 'params' => ['task' => $task->id]], $payload['link']);
        $this->assertSame('0', $payload['buttons'][0]['key']);
        $this->assertSame(['url' => '/tasks/1'], $payload['buttons'][0]['link']);
        $this->assertSame('approve', $payload['buttons'][1]['key']);
        $this->assertSame('POST', $payload['buttons'][1]['method']);
        $this->assertSame(['step' => 2], $payload['buttons'][1]['params']);
        $this->assertTrue($payload['buttons'][1]['mark_read']);
        $this->assertSame(['priority' => 'high', 'rank' => 1], $payload['data']);
    }

    public function test_a_datetime_is_stored_in_utc(): void
    {
        $value = InApp::datetime(Carbon::parse('2026-11-30 10:00', 'Asia/Riyadh'))->toArray();

        $this->assertSame('2026-11-30T07:00:00+00:00', $value['v']);
        $this->assertTrue($value['time']);
    }

    public function test_empty_params_and_data_encode_as_objects(): void
    {
        $json = json_encode(InAppMessage::make('notifications.welcome.title'));

        $this->assertStringContainsString('"p":{}', $json);
        $this->assertStringContainsString('"data":{}', $json);
    }

    public function test_a_title_is_required(): void
    {
        $this->expectException(InvalidMessageException::class);

        InAppMessage::make()->body('x')->toArray();
    }

    public function test_a_button_method_must_be_known(): void
    {
        $this->expectException(InvalidMessageException::class);

        InAppButton::make('x')->method('OPTIONS');
    }

    public function test_a_link_is_a_url_or_a_route_not_both(): void
    {
        $this->expectException(InvalidMessageException::class);

        InAppMessage::make('x')->url('/a')->route('tasks.show', 1);
    }

    public function test_button_keys_are_unique(): void
    {
        $this->expectException(InvalidMessageException::class);

        InAppMessage::make('x')
            ->button('a', fn (InAppButton $button) => $button->key('same'))
            ->button('b', fn (InAppButton $button) => $button->key('same'))
            ->toArray();
    }

    public function test_description_is_the_title_key_or_the_literal_title(): void
    {
        $this->assertSame('notifications.task.title', InAppMessage::make('notifications.task.title')->description());
        $this->assertSame('Imported', InAppMessage::make(InApp::text('Imported'))->description());
    }
}
