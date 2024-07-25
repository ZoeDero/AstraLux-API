<?php 

namespace Helpers;

class HttpResponse{

    public static function send(array $data, int $status = 200) : void
    {
        if($status >= 300){
            self::exit($status);
        }
        http_response_code($status);
        echo json_encode($data);
        die;
    }

    public static function exit(int $status = 404) : void
    {
        http_response_code($status);
        if($status >= 300){
            die;
        }
    }
}