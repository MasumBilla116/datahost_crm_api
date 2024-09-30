<?php

namespace App\Controllers\Auth;

use Firebase\JWT\JWT;
use App\Interfaces\SecretKeyInterface;

class GenerateTokenController implements SecretKeyInterface
{
    public static function generateToken($data)
    {
        $now = time();
        // $future = strtotime('+12 hour', $now);
        $future = strtotime('+1 month', $now);
        $secretKey = self::JWT_SECRET_KEY;
        $payload = [
            "id" => $data->id,
            "email" => $data->email,
            //         "role"=>$data->role,
            "name" => $data->name,
            "data_access_type" => $data->data_access_type,
            "role_id" => $data->role->id,
            "iat" => $now,
            "exp" => $future
        ];


        return JWT::encode($payload, $secretKey, "HS256");
    }

    public static function generateTokenForWeb($data)
    {
        $now = time();
        // $future = strtotime('+12 hour', $now);
        $future = strtotime('+1 month', $now);
        $secretKey = self::JWT_SECRET_KEY;
        $payload = [
            "id" => $data->id,
            "email" => $data->email,
            //         "role"=>$data->role,
            "name" => $data->name,
            "data_access_type" => $data->data_access_type,
            "iat" => $now,
            "exp" => $future
        ];

        return JWT::encode($payload, $secretKey, "HS256");
    }
}
