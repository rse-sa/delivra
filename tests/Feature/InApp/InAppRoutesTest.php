<?php

namespace RSE\Delivra\Tests\Feature\InApp;

use RSE\Delivra\Facades\InApp;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;

class InAppRoutesTest extends InAppTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('delivra.in_app.routes.enabled', true);
        $app['config']->set('delivra.in_app.routes.middleware', []);
    }

    protected function send(InAppUser $user, string $title, ?string $category = null): InAppNotification
    {
        return InApp::send($user, InAppMessage::make('notifications.welcome.title', ['name' => $title])->category($category));
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('in-app-notifications')->assertUnauthorized();
    }

    public function test_it_lists_the_users_rows_rendered_newest_first(): void
    {
        $user = $this->user();
        $this->send($user, 'one');
        $this->travel(1)->minutes();
        $this->send($user, 'two');
        $this->send($this->user('Omar'), 'not mine');

        $this->actingAs($user)
            ->getJson('in-app-notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Welcome two')
            ->assertJsonPath('data.1.title', 'Welcome one')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_list_renders_in_the_request_locale(): void
    {
        $user = $this->user();
        $this->send($user, 'Sara');
        app()->setLocale('ar');

        $this->actingAs($user)->getJson('in-app-notifications')->assertJsonPath('data.0.title', 'مرحبًا Sara');
    }

    public function test_it_filters_by_status_category_and_archive(): void
    {
        $user = $this->user();
        $this->send($user, 'read', 'tasks')->markAsRead();
        $this->send($user, 'unread', 'tasks');
        $this->send($user, 'comment', 'comments');
        $this->send($user, 'archived', 'tasks')->archive();

        $this->actingAs($user);

        $this->getJson('in-app-notifications?status=unread')->assertJsonCount(2, 'data');
        $this->getJson('in-app-notifications?status=read')->assertJsonPath('data.0.title', 'Welcome read');
        $this->getJson('in-app-notifications?category=comments')->assertJsonCount(1, 'data');
        $this->getJson('in-app-notifications?category[]=comments&category[]=tasks')->assertJsonCount(3, 'data');
        $this->getJson('in-app-notifications?archived=1')->assertJsonPath('data.0.title', 'Welcome archived');
        $this->getJson('in-app-notifications?per_page=500')->assertUnprocessable();
    }

    public function test_unread_count_has_a_category_breakdown(): void
    {
        $user = $this->user();
        $this->send($user, 'a', 'tasks');
        $this->send($user, 'b', 'tasks');
        $this->send($user, 'c', 'comments');
        $this->send($user, 'd');
        $this->send($user, 'e', 'tasks')->markAsRead();

        $this->actingAs($user)
            ->getJson('in-app-notifications/unread-count')
            ->assertExactJson(['count' => 4, 'categories' => ['tasks' => 2, 'comments' => 1]]);
    }

    public function test_row_actions(): void
    {
        $user = $this->user();
        $row  = $this->send($user, 'x');

        $this->actingAs($user);

        $this->postJson("in-app-notifications/{$row->id}/read")->assertOk()->assertJsonPath('data.id', $row->id);
        $this->assertTrue($row->fresh()->isRead());

        $this->postJson("in-app-notifications/{$row->id}/unread")->assertJsonPath('data.read_at', null);
        $this->postJson("in-app-notifications/{$row->id}/archive")->assertOk();
        $this->assertTrue($row->fresh()->isArchived());

        $this->postJson("in-app-notifications/{$row->id}/unarchive")->assertJsonPath('data.archived_at', null);

        $this->deleteJson("in-app-notifications/{$row->id}")->assertNoContent();
        $this->assertNull($row->fresh());
    }

    public function test_empty_data_is_an_array_in_php_and_an_object_in_json(): void
    {
        $user = $this->user();
        $row  = InApp::send($user, InAppMessage::make('x')->button('y'));

        $this->assertSame([], $row->render()['data']);
        $this->assertSame([], $row->render()['buttons'][0]['params']);

        $json = $this->actingAs($user)->postJson("in-app-notifications/{$row->id}/read")->getContent();

        $this->assertStringContainsString('"data":{}', $json);
        $this->assertStringContainsString('"params":{}', $json);
    }

    public function test_read_all_with_and_without_category(): void
    {
        $user = $this->user();
        $this->send($user, 'a', 'tasks');
        $this->send($user, 'b', 'comments');
        $other = $this->send($this->user('Omar'), 'c', 'tasks');

        $this->actingAs($user);

        $this->postJson('in-app-notifications/read-all', ['category' => 'tasks'])->assertNoContent();
        $this->assertSame(1, $user->unreadInAppNotificationsCount());

        $this->postJson('in-app-notifications/read-all')->assertNoContent();
        $this->assertSame(0, $user->unreadInAppNotificationsCount());
        $this->assertFalse($other->fresh()->isRead());
    }

    public function test_another_users_row_is_404(): void
    {
        $row = $this->send($this->user('Omar'), 'x');

        $this->actingAs($this->user());

        $this->postJson("in-app-notifications/{$row->id}/read")->assertNotFound();
        $this->deleteJson("in-app-notifications/{$row->id}")->assertNotFound();
        $this->assertNotNull($row->fresh());
    }

    public function test_routes_follow_the_tenant_scope(): void
    {
        $acme  = Company::query()->create(['name' => 'Acme']);
        $other = Company::query()->create(['name' => 'Other']);
        $user  = $this->user();

        InApp::send($user, InAppMessage::make('notifications.welcome.title', ['name' => 'acme'])->scope($acme));
        $hidden = InApp::send($user, InAppMessage::make('notifications.welcome.title', ['name' => 'other'])->scope($other));
        InApp::send($user, InAppMessage::make('notifications.welcome.title', ['name' => 'global']));

        InApp::scopeUsing(fn () => $acme);

        $this->actingAs($user);

        $this->getJson('in-app-notifications')->assertJsonCount(2, 'data');
        $this->getJson('in-app-notifications/unread-count')->assertJsonPath('count', 2);
        $this->postJson("in-app-notifications/{$hidden->id}/read")->assertNotFound();
    }

    public function test_apps_can_register_their_own_route_group(): void
    {
        InApp::routes(['prefix' => 'mobile/notifications', 'middleware' => [], 'name' => 'mobile.notifications.']);

        $user = $this->user();
        $this->send($user, 'x');

        $this->actingAs($user)->getJson(route('mobile.notifications.index'))->assertJsonCount(1, 'data');
    }
}
