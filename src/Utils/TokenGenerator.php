<?php


namespace App\Utils;


class TokenGenerator
{
    public static function generate($length = 32)
    {
        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }
}
