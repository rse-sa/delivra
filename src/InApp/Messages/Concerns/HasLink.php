<?php

namespace RSE\Delivra\InApp\Messages\Concerns;

use BackedEnum;
use Illuminate\Contracts\Routing\UrlRoutable;
use RSE\Delivra\Exceptions\InApp\InvalidMessageException;

/**
 * A link is a URL, or a route name with params that becomes a URL when read.
 */
trait HasLink
{
    protected ?string $url = null;

    protected ?string $routeName = null;

    protected array $routeParams = [];

    public function url(?string $url): static
    {
        if ($url !== null && $this->routeName !== null) {
            throw new InvalidMessageException('An in-app link has a route already. Use url() or route(), not both.');
        }

        $this->url = $url;

        return $this;
    }

    /**
     * Stored as name and params, so a new domain does not break old notifications.
     */
    public function route(string $name, mixed $params = []): static
    {
        if ($this->url !== null) {
            throw new InvalidMessageException('An in-app link has a URL already. Use url() or route(), not both.');
        }

        $this->routeName   = $name;
        $this->routeParams = array_map(fn ($param) => match (true) {
            $param instanceof UrlRoutable => $param->getRouteKey(),
            $param instanceof BackedEnum  => $param->value,
            default                       => $param,
        }, is_array($params) ? $params : [$params]);

        return $this;
    }

    protected function linkToArray(): ?array
    {
        return match (true) {
            $this->routeName !== null => ['route' => $this->routeName, 'params' => (object) $this->routeParams],
            $this->url !== null       => ['url' => $this->url],
            default                   => null,
        };
    }
}
