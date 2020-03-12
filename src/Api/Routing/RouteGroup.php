<?php
namespace Virtue\Api\Routing;

use Psr\Container\ContainerInterface as Locator;
use Psr\Http\Server\MiddlewareInterface as ServerMiddleware;
use Psr\Http\Server\RequestHandlerInterface as HandlesServerRequests;
use Virtue\Api\Middleware\MiddlewareContainer;
use Virtue\Api\Middleware\Stackable;

class RouteGroup implements Stackable
{
    /** @var callable|string */
    private $callable;
    /** @var Locator */
    private $kernel;
    /** @var Api */
    private $api;
    /** @var ServerMiddleware[] */
    private $middlewares = [];

    public function __construct($callable, Locator $kernel, Api $api) {
        $this->callable = $callable;
        $this->kernel = $kernel;
        $this->api = $api;
    }

    public function collectRoutes(): void
    {
        ($this->callable)($this->api);
    }

    public function add(string $middleware): self
    {
        $this->middlewares[] = $this->kernel->get($middleware);

        return $this;
    }

    public function stack(HandlesServerRequests $bottom): MiddlewareContainer
    {
        return new MiddlewareContainer($bottom, $this->middlewares);
    }
}
