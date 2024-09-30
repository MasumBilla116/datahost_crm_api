<?php
namespace App\Auth;

use Psr\Http\Message\ServerRequestInterface as Request;

class Auth
{
    public static function user(Request $request)
    {
        $path = $request->getUri()->getPath();

        if(!in_array($path, ["/auth/login","/auth/register"])){
            return (object) $request->getAttribute("token");
        } else {
            return false;
        }
    }
}