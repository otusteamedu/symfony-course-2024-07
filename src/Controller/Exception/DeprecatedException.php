<?php

namespace App\Controller\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class DeprecatedException extends Exception implements HttpCompliantExceptionInterface
{
    public function getHttpCode(): int
    {
        return Response::HTTP_GONE;
    }

    public function getHttpResponseBody(): string
    {
        return 'This API method is deprecated';
    }
}
