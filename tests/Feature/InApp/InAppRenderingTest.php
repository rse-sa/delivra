<?php

namespace RSE\Delivra\Tests\Feature\InApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RSE\Delivra\Exceptions\InApp\InvalidPayloadException;
use RSE\Delivra\Facades\InApp;
use RSE\Delivra\InApp\Messages\InAppButton;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;

class InAppRenderingTest extends InAppTestCase
{
    protected function taskNotification(): InAppNotification
    {
        $user = $this->user();
        $task = Task::query()->create(['title' => 'Roof']);

        return InApp::send($user, InAppMessage::make('notifications.task.title', ['task' => $task->title])
            ->body('notifications.task.body', [
                'due'    => InApp::date('2026-11-30'),
                'status' => InApp::trans('tasks.status.open'),
                'files'  => InApp::choice('notifications.files', 3),
            ])
            ->icon('fa-list')
            ->color('warning')
            ->category('tasks')
            ->subject($task)
            ->route('tasks.show', ['task' => $task])
            ->button('notifications.actions.open', fn (InAppButton $button) => $button->route('tasks.show', $task)->color('primary')));
    }

    public function test_it_renders_in_the_current_locale_each_time_it_is_read(): void
    {
        $notification = $this->taskNotification();

        $en = $notification->render('en');
        $ar = $notification->render('ar');

        $this->assertSame('Task Roof assigned', $en['title']);
        $this->assertSame('Due 30 November 2026, status open, 3 files', $en['body']);
        $this->assertSame('Open', $en['buttons'][0]['label']);
        $this->assertSame('تم إسناد المهمة Roof', $ar['title']);
        $this->assertStringContainsString('مفتوحة', $ar['body']);
        $this->assertStringContainsString('نوفمبر', $ar['body']);
        $this->assertSame('فتح', $ar['buttons'][0]['label']);

        app()->setLocale('ar');
        $this->assertSame('تم إسناد المهمة Roof', $notification->fresh()->render()['title']);
    }

    public function test_rendering_does_not_change_the_app_locale(): void
    {
        $this->taskNotification()->render('ar');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_it_renders_links_icons_and_morphs(): void
    {
        $rendered = $this->taskNotification()->render();

        $this->assertSame(url('tasks/1'), $rendered['url']);
        $this->assertSame(url('tasks/1'), $rendered['buttons'][0]['url']);
        $this->assertSame('primary', $rendered['buttons'][0]['color']);
        $this->assertSame('fa-list', $rendered['icon']);
        $this->assertSame('warning', $rendered['color']);
        $this->assertSame('tasks', $rendered['category']);
        $this->assertSame(['type' => Task::class, 'id' => '1'], $rendered['subject']);
        $this->assertNull($rendered['causer']);
        $this->assertNull($rendered['read_at']);
    }

    public function test_a_removed_route_or_a_missing_param_renders_no_url(): void
    {
        $user = $this->user();

        $gone    = InApp::send($user, InAppMessage::make('x')->route('gone.route'));
        $noParam = InApp::send($user, InAppMessage::make('x')->route('tasks.show'));

        $this->assertNull($gone->render()['url']);
        $this->assertNull($noParam->render()['url']);
    }

    public function test_datetimes_render_in_the_reader_timezone(): void
    {
        $user = $this->user();
        $user->forceFill(['timezone' => 'Asia/Riyadh'])->save();

        $notification = InApp::send($user, InAppMessage::make('notifications.welcome.title', [
            'name' => InApp::datetime(Carbon::parse('2026-11-30 07:00', 'UTC'), 'H:i'),
        ]));

        $this->assertSame('Welcome 07:00', $notification->render()['title']);

        InApp::timezoneUsing(fn ($notifiable) => $notifiable->timezone);

        $this->assertSame('Welcome 10:00', $notification->fresh()->render()['title']);
        $this->assertSame('Welcome 11:00', $notification->render(null, 'Asia/Dubai')['title']);
    }

    public function test_a_date_does_not_shift_between_timezones(): void
    {
        $notification = InApp::send($this->user(), InAppMessage::make('notifications.welcome.title', ['name' => InApp::date('2026-11-30')]));

        $this->assertSame('Welcome 30 November 2026', $notification->render(null, 'Pacific/Honolulu')['title']);
        $this->assertSame('Welcome 30 November 2026', $notification->render(null, 'Asia/Tokyo')['title']);
    }

    public function test_a_null_date_renders_empty(): void
    {
        $notification = InApp::send($this->user(), InAppMessage::make('notifications.welcome.title', ['name' => InApp::date(null)]));

        $this->assertSame('Welcome ', $notification->render()['title']);
    }

    public function test_literal_text_ignores_the_locale(): void
    {
        $notification = InApp::send($this->user(), InAppMessage::make(InApp::text('Imported row')));

        $this->assertSame('Imported row', $notification->render('ar')['title']);
    }

    public function test_translations_render_the_reader_locale_then_the_fallback(): void
    {
        config(['app.fallback_locale' => 'en']);

        $notification = InApp::send($this->user(), InAppMessage::make('notifications.welcome.title', [
            'name' => InApp::translations(['ar' => 'اعتماد العقود', 'en' => 'Contract approval']),
        ]));

        $this->assertSame('Welcome Contract approval', $notification->render('en')['title']);
        $this->assertSame('Welcome Contract approval', $notification->render('fr')['title']);
        $this->assertSame('مرحبًا اعتماد العقود', $notification->fresh()->render('ar')['title']);
    }

    public function test_translations_fall_back_to_the_first_text_and_work_as_a_title(): void
    {
        $user = $this->user();

        $only  = InApp::send($user, InAppMessage::make(InApp::translations(['ar' => 'إعلان'])));
        $empty = InApp::send($user, InAppMessage::make(InApp::translations([])));

        $this->assertSame('إعلان', $only->render('en')['title']);
        $this->assertSame('', $empty->render('en')['title']);
        $this->assertSame([], (array) $empty->payload['title']['v']);
    }

    public function test_translated_reads_every_translation_of_a_model_attribute(): void
    {
        $workflow = new class extends Model
        {
            protected $attributes = ['name' => '{"ar":"اعتماد العقود","en":"Contract approval"}'];

            public function getTranslations(string $attribute): array
            {
                return json_decode($this->attributes[$attribute], true);
            }
        };

        $notification = InApp::send($this->user(), InAppMessage::make(InApp::translated($workflow, 'name')));

        $this->assertSame('Contract approval', $notification->render('en')['title']);
        $this->assertSame('اعتماد العقود', $notification->render('ar')['title']);
    }

    public function test_translated_keeps_a_plain_attribute_as_literal_text(): void
    {
        $task = Task::query()->create(['title' => 'Roof']);

        $notification = InApp::send($this->user(), InAppMessage::make(InApp::translated($task, 'title')));

        $this->assertSame('Roof', $notification->render('ar')['title']);
        $this->assertSame('text', $notification->payload['title']['t']);
    }

    public function test_render_hooks_run_for_the_type_then_the_category(): void
    {
        $this->user()->notify(new MessageNotification(InAppMessage::make('x')->category('tasks')));
        $notification = InAppNotification::query()->sole();

        InApp::renderUsing('tasks', fn ($model, array $rendered) => ['title' => $rendered['title'] . ' + category'] + $rendered);
        InApp::renderUsing(MessageNotification::class, fn ($model, array $rendered, string $locale) => ['title' => "type({$locale})"] + $rendered);

        $this->assertSame('type(en) + category', $notification->render()['title']);
    }

    public function test_registering_a_hook_again_replaces_it(): void
    {
        $notification = InApp::send($this->user(), InAppMessage::make(InApp::text('Hi'))->category('tasks'));

        // a provider booted once per test registers the same hook again
        InApp::renderUsing('tasks', fn ($model, array $rendered) => ['title' => $rendered['title'] . '!'] + $rendered);
        InApp::renderUsing('tasks', fn ($model, array $rendered) => ['title' => $rendered['title'] . '!'] + $rendered);

        $this->assertSame('Hi!', $notification->render()['title']);

        InApp::renderUsing('tasks', null);

        $this->assertSame('Hi', $notification->render()['title']);
    }

    public function test_an_unknown_payload_version_throws(): void
    {
        $notification = InApp::send($this->user(), InAppMessage::make('x'));
        $notification->forceFill(['payload' => ['v' => 99]])->save();

        $this->expectException(InvalidPayloadException::class);

        $notification->render();
    }
}
