<?php

namespace Helpers;

class HttpRequest
{
    public string $stringRequest;
    public string $method;
    public array $route;

    private function __construct()
    {
        $this->stringRequest = $_SERVER['REQUEST_METHOD'] . "/" . 
        filter_var(trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), '/'), FILTER_SANITIZE_URL);

        $requestArray = explode('/', $this->stringRequest);
        $this->method = array_shift($requestArray);

        $this->route = $requestArray;
    }

    private static $instance;

    public static function instance(): HttpRequest
    {
        if (is_null(self::$instance)) {
            self::$instance = new HttpRequest();
        }
        return self::$instance;
    }
}
