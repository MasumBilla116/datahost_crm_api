<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class LaravelApiMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler) : Response {

        $secretKey = 'SSDFSDF8S7D6FSD7SDFIJI3U2IU3KH23JH4J234JK24G2JH3J64J56H3J5J35J3H4J5';
        $LARAVEL_TO_SLIM_SECRET_KEY = $request->getHeaderLine('LARAVEL_TO_SLIM_SECRET_KEY');

        if (empty($LARAVEL_TO_SLIM_SECRET_KEY) || $LARAVEL_TO_SLIM_SECRET_KEY !== $secretKey) {
            $response = new Response();
            $response->getBody()->write('Unauthorized: Missing LARAVEL_TO_SLIM_SECRET_KEY header');
            return $response->withStatus(401);
        }

        return $handler->handle($request);
    }
}