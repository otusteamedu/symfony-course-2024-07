<?php

namespace App\Application\EventListener;

use App\Controller\Exception\HttpCompliantExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class KernelExceptionEventListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpCompliantExceptionInterface) {
            $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
        }
    }

    private function getHttpResponse($message, $code): Response {
        return new JsonResponse(['message' => $message], $code);
    }
}
