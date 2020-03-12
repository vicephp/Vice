<?php

namespace Virtue\Api;

use Psr\Container\ContainerInterface as Locator;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Slim\ResponseEmitter;
use Virtue\Api\Middleware\RoutingMiddleware;
use Virtue\Api\Routing;
use Virtue\Api\Testing;

class AppTest extends AppTestCase
{
    public function testRun()
    {
        $kernel = $this->container->build();
        $app = $kernel->get(App::class);
        $app->add(RoutingMiddleware::class);
        $app->get('/run', function ($request, $response, $args) {
            return $response;
        });
        $request = $kernel->get(ServerRequest::class);
        $request = $request->withUri($request->getUri()->withPath('/run'));
        $app->run($request);
        /** @var Testing\ResponseEmitterStub $emitter */
        $emitter = $kernel->get(ResponseEmitter::class);
        $response = $emitter->last();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
    }

    public function testHandle()
    {
        $this->container->addDefinitions(
            [
                Routing\RouteRunner::class => function (Locator $kernel) {
                    return new Testing\RequestHandlerStub(
                        $kernel->get(ResponseFactory::class)->createResponse()
                    );
                },
            ]
        );
        $kernel = $this->container->build();
        /** @var App $app */
        $app = $kernel->get(App::class);
        $app->add(RoutingMiddleware::class);
        $app->get('/handle', function ($request, $response, $args) {
            return $response;
        });
        $request = $kernel->get(ServerRequest::class);
        $request = $request->withUri($request->getUri()->withPath('/handle'));
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
    }
}
