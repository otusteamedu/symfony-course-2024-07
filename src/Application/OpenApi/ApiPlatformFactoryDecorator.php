<?php

namespace App\Application\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiPlatformFactoryDecorator implements OpenApiFactoryInterface
{
    public const FEED_AREA_NAME = 'feed';

    public function __construct(
        private readonly OpenApiFactoryInterface $decoratedFactory,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $area = $this->getAreaName();
        $openApi = $this->decoratedFactory->__invoke($context);
        if ($area !== self::FEED_AREA_NAME) {
            return $openApi;
        }

        return $openApi->withPaths(new Paths())->withComponents(new Components());
    }

    private function getAreaName(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->getAreaNameFromConsole();
        }

        return $this->getAreaNameFromRequest($request);
    }

    private function getAreaNameFromConsole(): ?string
    {
        $request = Request::createFromGlobals();
        foreach ($request->server->get('argv') as $arg) {
            $matches = [];
            preg_match('/^--area=(?<area>.+)/', (string) $arg, $matches);
            if (isset($matches['area'])) {
                return $matches['area'];
            }
        }

        return null;
    }

    private function getAreaNameFromRequest(Request $request): ?string
    {
        $pathInfo = $request->getPathInfo();
        $matches = [];
        preg_match('/^\/api\/doc\/(?<area>[^\/.]+)/', $pathInfo, $matches);
        return $matches['area'] ?? null;
    }
}
