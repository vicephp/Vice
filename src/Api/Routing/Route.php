<?php

namespace Virtue\Api\Routing;

use Psr\Container\ContainerInterface as Locator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Handlers\Strategies\RequestHandler;
use Slim\Interfaces\AdvancedCallableResolverInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RequestHandlerInvocationStrategyInterface;
use Slim\MiddlewareDispatcher;
use Virtue\Api\Middleware\MiddlewareStack;
use function array_replace;
use function class_implements;
use function in_array;
use function is_array;

class Route implements RequestHandlerInterface
{
    /** @var string[] */
    protected $methods = [];
    /** @var string */
    protected $identifier;
    /** @var null|string */
    protected $name;
    /** @var RouteGroup[] */
    protected $groups;
    /** @var array */
    protected $arguments = [];
    /** @var array */
    protected $savedArguments = [];
    /** @var MiddlewareDispatcher */
    protected $middlewareStack;
    /** @var callable|string */
    protected $callable;
    /** @var Locator */
    protected $kernel;
    /** @var string */
    protected $pattern;
    /** @var bool */
    protected $groupMiddlewareAppended = false;

    public function __construct(
        array $methods,
        string $pattern,
        $callable,
        Locator $kernel,
        array $groups = [],
        int $identifier = 0
    ) {
        $this->methods = $methods;
        $this->pattern = $pattern;
        $this->callable = $callable;
        $this->kernel = $kernel;
        $this->groups = $groups;
        $this->identifier = "route{$identifier}";
        $this->middlewareStack = new MiddlewareStack($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * {@inheritdoc}
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function add($middleware): void
    {
        $this->middlewareStack->append($this->kernel->get($middleware));
    }

    public function prepare(array $arguments): void
    {
        $this->arguments = array_replace($this->savedArguments, $arguments) ?? [];
    }

    public function run(ServerRequest $request): Response
    {
        if (!$this->groupMiddlewareAppended) {
            $this->appendGroupMiddlewareToRoute();
        }

        return $this->middlewareStack->handle($request);
    }

    protected function appendGroupMiddlewareToRoute(): void
    {
        $this->middlewareStack = new MiddlewareStack($this->middlewareStack);

        /** @var RouteGroup $group */
        foreach ($this->groups as $group) {
            $group->appendMiddlewareToDispatcher($this->middlewareStack);
        }

        $this->groupMiddlewareAppended = true;
    }

    public function handle(ServerRequest $request): Response
    {
        $callableResolver = $this->kernel->get(AdvancedCallableResolverInterface::class);
        $callable = $callableResolver->resolveRoute($this->callable);
        $strategy = $this->kernel->get(InvocationStrategyInterface::class);

        if (
            is_array($callable)
            && $callable[0] instanceof RequestHandlerInterface
            && !in_array(RequestHandlerInvocationStrategyInterface::class, class_implements($strategy))
        ) {
            $strategy = new RequestHandler();
        }

        $response = $this->kernel->get(Response::class);
        return $strategy($callable, $request, $response, $this->arguments);
    }
}