<?php

namespace Middlewares;

use Helpers\HttpRequest;
use Helpers\Token;

class AuthMiddleware
{

    public function __construct(HttpRequest $request)
    {
        $restrictedRoutes = (array)$_ENV['config']->restricted;
        $params = $request->stringRequest;

        if (isset($request->route[1]) && $request->route[1] === "*" || 
            isset($request->route[1]) && $request->route[1] === "0") {
            $this->id = null;
        } else {
            $this->id = isset($request->route[1]) ? $request->route[1] : null;
        }

        $params = str_replace($this->id, ":id", $params);
        if (isset($restrictedRoutes[$params])) {
            $this->condition = $restrictedRoutes[$params];
        }

        foreach ($restrictedRoutes as $k => $v) {
            
            $restricted = str_replace(":id", $this->id, $k);
            if ($restricted == $request->stringRequest) {
                $this->condition = $v;
                break;
            }
        }
    }

    public function verify()
    {
        if (isset($this->condition)) {
            $headers = apache_request_headers();

            if (isset($headers["Authorization"])) {
                $token = $headers["Authorization"];
            }

            if (isset($token) && !empty($token)) {
                $tokenFromEncodedString = Token::create($token);
                $test = $tokenFromEncodedString->isValid();

                if ($test == true) {
                    return true;
                }
            }

            header('HTTP/1.0 401 Unauthorized');
            die;
        }

        return true;
    }
}
