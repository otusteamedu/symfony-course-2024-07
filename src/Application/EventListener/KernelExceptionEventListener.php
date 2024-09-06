<?php

namespace App\Application\EventListener;

use App\Controller\Exception\HttpCompliantExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class KernelExceptionEventListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpCompliantExceptionInterface) {
            $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
        } elseif ($exception instanceof HttpExceptionInterface && $exception->getPrevious() instanceof ValidationFailedException) {
            $event->setResponse($this->getValidationFailedResponse($exception->getPrevious()));
        }
    }

    private function getHttpResponse($message, $code): Response {
        return new JsonResponse(['message' => $message], $code);
    }

    private function getValidationFailedResponse(ValidationFailedException $exception): Response {
        $response = [];
        foreach ($exception->getViolations() as $violation) {
            $response[$violation->getPropertyPath()] = $violation->getMessage();
        }
        return new JsonResponse($response, Response::HTTP_BAD_REQUEST);
    }
}
