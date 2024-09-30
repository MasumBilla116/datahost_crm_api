<?php

use Slim\App;

return function (App $app) {
  $app->getContainer()->get('settings');
  $app->addRoutingMiddleware();

  $app->add(
    new \Tuupola\Middleware\JwtAuthentication([
      "ignore" => ["/api/*", "/auth/login", "/auth/register", "/uploads/*", "/app/currency/*", "/web", "/app/settings/general/*"],
      "secure" => false,
      "secret" => \App\Interfaces\SecretKeyInterface::JWT_SECRET_KEY,
      "error" => function ($response, $arguments) {
        $data["success"] = false;
        $data["response"] = $arguments["message"];
        $data["status_code"] = "401";

        return $response
          ->withHeader("Content-type", "application/json")
          ->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
      }
    ])
  );

  $app->addErrorMiddleware(true, true, true);
};
