<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Specific exception for the API (will return an XML response)
 */
class BBBApiException extends HttpException
{
    public function __construct(?string $errorCode = '')
    {
        $this->statusCode = 200;
        parent::__construct(200, $errorCode, null);
    }
}