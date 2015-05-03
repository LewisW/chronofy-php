<?php

namespace Vivait\Chronofy\Exception;

class BadRequestException extends HttpException
{
    public function __construct($message = null)
    {
        parent::__construct($message, 400);
    }
}
