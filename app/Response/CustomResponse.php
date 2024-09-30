<?php

namespace  App\Response;

class CustomResponse
{

    public function is200Response($response, $responseMessage, $data = [])
    {
        $responseMessage = json_encode(["status"=>"success","response"=>$responseMessage, "data"=>$data]);
        $response->getBody()->write($responseMessage);
        return $response->withHeader("Content-Type", "application/json")
            ->withStatus(200);
    }


    public function is400Response($response, $responseMessage, $data = [])
    {
        $responseMessage = json_encode(["status"=>"error","response"=>$responseMessage,"data"=>$data]);
        $response->getBody()->write($responseMessage);
        return $response->withHeader("Content-Type", "application/json")
            ->withStatus(400);
    }

    public function is422Response($response, $responseMessage, $data = [])
    {
        $responseMessage = json_encode(["status"=>"error","response"=>$responseMessage]);
        $response->getBody()->write($responseMessage);
        return $response->withHeader("Content-Type", "application/json")
            ->withStatus(422);
    }
}
