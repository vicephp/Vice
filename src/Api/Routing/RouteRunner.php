<?php

namespace Virtue\Api\Routing;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Server\RequestHandlerInterface as HandlesServerRequests;

class RouteRunner implements HandlesServerRequests
{
    public function handle(ServerRequest $request): Response
    {
        return $request->getAttribute(Route::class)->handle($request);
    }
}
