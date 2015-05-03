<?php

namespace Vivait\Chronofy\Exception;


use Vivait\Chronofy\Http\HttpAdapter;

class TokenInvalidException extends UnauthorizedException
{
    public function __construct($message = HttpAdapter::TOKEN_INVALID)
    {
        parent::__construct($message);
    }
}
