<?php

namespace Phug;

use Phug\Formatter\Format\TwigBasicFormat;
use Phug\Formatter\Format\TwigFramesetFormat;
use Phug\Formatter\Format\TwigHtmlFormat;
use Phug\Formatter\Format\TwigMobileFormat;
use Phug\Formatter\Format\TwigOneDotOneFormat;
use Phug\Formatter\Format\TwigPlistFormat;
use Phug\Formatter\Format\TwigStrictFormat;
use Phug\Formatter\Format\TwigTransitionalFormat;
use Phug\Formatter\Format\TwigXmlFormat;

class PugToTwig extends AbstractCompilerModule
{
    /**
     * @var string
     */
    protected static $defaultFormat = TwigBasicFormat::class;

    /**
     * @var array
     */
    protected static $formats = [
        'basic'        => TwigBasicFormat::class,
        'frameset'     => TwigFramesetFormat::class,
        'html'         => TwigHtmlFormat::class,
        'mobile'       => TwigMobileFormat::class,
        '1.1'          => TwigOneDotOneFormat::class,
        'plist'        => TwigPlistFormat::class,
        'strict'       => TwigStrictFormat::class,
        'transitional' => TwigTransitionalFormat::class,
        'xml'          => TwigXmlFormat::class,
    ];

    /**
     * @param CompilerInterface $compiler
     *
     * @return $compiler
     */
    public static function enableTwigFormatter(CompilerInterface $compiler)
    {
        $options = [
            'default_format' => static::$defaultFormat,
            'formats'        => static::$formats,
        ];
        $formatter = $compiler->setOptionsRecursive($options)->getFormatter();
        $formatter->setOptionsRecursive($options);
        $formatter->setFormat('basic');

        return $compiler;
    }

    /**
     * Convert pug code to twig code.
     *
     * @param string $pugCode
     * @param array  $options
     *
     * @return string
     */
    public static function convert($pugCode, $options = [])
    {
        if (!isset($options['default_format'])) {
            $options['default_format'] = static::$defaultFormat;
        }
        if (!isset($options['formats'])) {
            $options['formats'] = static::$formats;
        }
        $compiler = new Compiler($options);

        return $compiler->compile($pugCode);
    }
}
