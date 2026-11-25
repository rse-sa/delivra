<?php

namespace RSE\Delivra\InApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use RSE\Delivra\InApp\InAppManager;
use RSE\Delivra\InApp\InAppRenderer;

/**
 * @mixin \RSE\Delivra\InApp\Models\InAppNotification
 */
class InAppNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return InAppRenderer::forJson($this->resource->render(null, InAppManager::resolveTimezone($request->user())));
    }

    /**
     * The rendered row has its own "data" key, which makes Laravel skip the wrapper.
     * Always wrap, so one row looks like a row of the list.
     */
    public function toResponse($request)
    {
        return (new class($this) extends ResourceResponse
        {
            protected function haveDefaultWrapperAndDataIsUnwrapped($data)
            {
                return $this->wrapper() !== null;
            }
        })->toResponse($request);
    }
}
