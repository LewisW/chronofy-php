<?php

namespace Vivait\Chronofy\Exception;

use Vivait\Chronofy\Http\HttpAdapter;

class TokenExpiredException extends UnauthorizedException
{
    public function __construct($message = HttpAdapter::TOKEN_EXPIRED)
    {
        parent::__construct($message);
    }
}
