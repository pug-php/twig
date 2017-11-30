<?php

namespace Phug;

use Phug\Util\ModuleContainerInterface;

class TwigExtension extends AbstractCompilerModule
{
    public function __construct(ModuleContainerInterface $container)
    {
        parent::__construct($container);

        if ($container instanceof Renderer) {
            return;
        }

        /* @var Compiler $container */
        PugToTwig::enableTwigFormatter($container);
    }
}
