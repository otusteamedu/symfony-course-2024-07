<?php

namespace App\Application\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class MyTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('my_twice', static fn ($val) => $val.$val),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('my_greet', static fn ($val) => 'Hello, '.$val),
        ];
    }
}
