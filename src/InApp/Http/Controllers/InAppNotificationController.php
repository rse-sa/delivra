<?php

namespace RSE\Delivra\InApp\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use RSE\Delivra\InApp\InAppManager;
use RSE\Delivra\InApp\Http\Resources\InAppNotificationResource;
use RSE\Delivra\InApp\Models\InAppNotification;

/**
 * Every query starts from the signed-in user, so another user's row is a 404.
 */
class InAppNotificationController extends Controller
{
    public function index(Request $request)
    {
        // ?category=tasks and ?category[]=tasks&category[]=comments both work
        if (is_string($request->query('category'))) {
            $request->merge(['category' => [$request->query('category')]]);
        }

        $validated = $request->validate([
            'status'     => ['sometimes', 'in:unread,read,all'],
            'category'   => ['sometimes', 'array'],
            'category.*' => ['string'],
            'archived'   => ['sometimes', 'boolean'],
            'per_page'   => ['sometimes', 'integer', 'min:1', 'max:' . (int) config('delivra.in_app.routes.max_per_page', 50)],
        ]);

        $notifications = $this->query($request)
            ->when(($validated['status'] ?? 'all') === 'unread', fn (Builder $query) => $query->unread())
            ->when(($validated['status'] ?? 'all') === 'read', fn (Builder $query) => $query->read())
            ->when($validated['category'] ?? null, fn (Builder $query, array $categories) => $query->category($categories))
            ->when(
                $request->boolean('archived'),
                fn (Builder $query) => $query->archived(),
                fn (Builder $query) => $query->withoutArchived(),
            )
            ->latest()
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return $this->resource()::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $query = $this->query($request)->unread()->withoutArchived();

        $categories = (clone $query)
            ->whereNotNull('category')
            ->groupBy('category')
            ->selectRaw('category, count(*) as aggregate')
            ->pluck('aggregate', 'category')
            ->map(fn ($count) => (int) $count);

        return response()->json([
            'count'      => $query->count(),
            'categories' => (object) $categories->all(),
        ]);
    }

    public function readAll(Request $request): Response
    {
        $validated = $request->validate(['category' => ['sometimes', 'nullable', 'string']]);

        $this->query($request)
            ->unread()
            ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query->category($category))
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function read(Request $request, string $notification): JsonResource
    {
        return $this->respond($this->find($request, $notification)->markAsRead());
    }

    public function unread(Request $request, string $notification): JsonResource
    {
        return $this->respond($this->find($request, $notification)->markAsUnread());
    }

    public function archive(Request $request, string $notification): JsonResource
    {
        return $this->respond($this->find($request, $notification)->archive());
    }

    public function unarchive(Request $request, string $notification): JsonResource
    {
        return $this->respond($this->find($request, $notification)->unarchive());
    }

    public function destroy(Request $request, string $notification): Response
    {
        $this->find($request, $notification)->delete();

        return response()->noContent();
    }

    /**
     * The user's rows in the current tenant scope, plus system-wide rows.
     */
    protected function query(Request $request): Builder
    {
        $user  = $request->user();
        $model = config('delivra.in_app.model', InAppNotification::class);

        abort_if($user === null, 401);

        return $model::query()
            ->whereMorphedTo('notifiable', $user)
            ->visibleInScope(InAppManager::resolveScope($user));
    }

    protected function find(Request $request, string $id): InAppNotification
    {
        return $this->query($request)->findOrFail($id);
    }

    protected function respond(InAppNotification $notification): JsonResource
    {
        return $this->resource()::make($notification);
    }

    /**
     * @return class-string<JsonResource>
     */
    protected function resource(): string
    {
        return config('delivra.in_app.resource', InAppNotificationResource::class);
    }
}
