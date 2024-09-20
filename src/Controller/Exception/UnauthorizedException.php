<?php

namespace App\Controller\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class UnauthorizedException extends Exception implements HttpCompliantExceptionInterface
{
    public function getHttpCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getHttpResponseBody(): string
    {
        return empty($this->getMessage()) ? 'Unauthorized' : $this->getMessage();
    }
}
