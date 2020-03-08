<?php

namespace Vice;

use Psr\Container\ContainerInterface as Locator;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as HandlesServerRequests;
use Slim\CallableResolver;
use Slim\Interfaces\MiddlewareDispatcherInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteResolverInterface;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Slim\Middleware\RoutingMiddleware;
use Slim\ResponseEmitter;
use Vice\Routing\RouteCollectorProxy;

class App extends RouteCollectorProxy implements HandlesServerRequests
{
    /** @var string */
    public const VERSION = '0.0.0';
    /** @var MiddlewareDispatcherInterface */
    protected $middlewareStack;
    /** @var Locator */
    private $services;

    public function __construct(Locator $services) {
        parent::__construct(
            $services->get(ResponseFactory::class),
            $services->get(CallableResolver::class),
            $services->get(RouteCollectorInterface::class)
        );
        $this->services = $services;
        $this->middlewareStack = $services->get(MiddlewareDispatcherInterface::class);
    }

    /**
     * @param MiddlewareInterface|string|callable $middleware
     * @return self
     */
//    public function add($middleware): self
//    {
//        $this->middlewareStack->add($middleware);
//
//        return $this;
//    }

    /**
     * @param MiddlewareInterface $middleware
     * @return self
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewareStack->addMiddleware($middleware);

        return $this;
    }

    /**
     * Add the Slim built-in routing middleware to the app middleware stack
     *
     * This method can be used to control middleware order and is not required for default routing operation.
     *
     * @return RoutingMiddleware
     */
    public function addRoutingMiddleware(): RoutingMiddleware
    {
        $routingMiddleware = new RoutingMiddleware(
            $this->services->get(RouteResolverInterface::class),
            $this->services->get(RouteCollectorInterface::class)->getRouteParser()
        );

        $this->addMiddleware($routingMiddleware);

        return $routingMiddleware;
    }

    /**
     * Add the Slim built-in error middleware to the app middleware stack
     *
     * @param bool $displayErrorDetails
     * @param bool $logErrors
     * @param bool $logErrorDetails
     *
     * @return ErrorMiddleware
     */
    public function addErrorMiddleware(
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ErrorMiddleware {
        $errorMiddleware = new ErrorMiddleware(
            $this->services->get(CallableResolver::class),
            $this->services->get(ResponseFactory::class),
            $displayErrorDetails,
            $logErrors,
            $logErrorDetails
        );
        $this->addMiddleware($errorMiddleware);

        return $errorMiddleware;
    }

    /**
     * Add the Slim body parsing middleware to the app middleware stack
     *
     * @param callable[] $bodyParsers
     *
     * @return BodyParsingMiddleware
     */
    public function addBodyParsingMiddleware(array $bodyParsers = []): BodyParsingMiddleware
    {
        $bodyParsingMiddleware = new BodyParsingMiddleware($bodyParsers);
        $this->addMiddleware($bodyParsingMiddleware);

        return $bodyParsingMiddleware;
    }

    /**
     * Run application
     *
     * This method traverses the application middleware stack and then sends the
     * resultant Response object to the HTTP client.
     *
     * @param ServerRequest|null $request
     * @return void
     */
    public function run(?ServerRequest $request = null): void
    {
        if (!$request) {
            $request = $this->services->get(ServerRequest::class);
        }

        $response = $this->handle($request);
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($response);
    }

    /**
     * Handle a request
     *
     * This method traverses the application middleware stack and then returns the
     * resultant Response object.
     *
     * @param ServerRequest $request
     * @return Response
     */
    public function handle(ServerRequest $request): Response
    {
        $response = $this->middlewareStack->handle($request);

        /**
         * This is to be in compliance with RFC 2616, Section 9.
         * If the incoming request method is HEAD, we need to ensure that the response body
         * is empty as the request may fall back on a GET route handler due to FastRoute's
         * routing logic which could potentially append content to the response body
         * https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
         */
        $method = strtoupper($request->getMethod());
        if ($method === 'HEAD') {
            $emptyBody = $this->services->get(ResponseFactory::class)->createResponse()->getBody();
            return $response->withBody($emptyBody);
        }

        return $response;
    }
}