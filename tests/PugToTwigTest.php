<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use Phug\Compiler;
use Phug\TwigExtension;
use PugToTwig;

/**
 * @coversDefaultClass PugToTwig
 */
class PugToTwigTest extends TestCase
{
    /**
     * @covers ::convert
     * @covers \Phug\Formatter\AbstractTwigFormat::__construct
     * @covers \Phug\Formatter\AbstractTwigFormat::formatAttributes
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXmlFormat::addAttributeAssignment
     * @covers \Phug\Formatter\Format\TwigXmlFormat::requireHelper
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__invoke
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isWhiteSpaceSensitive
     * @covers \Phug\Formatter\Format\TwigXmlFormat::hasNonStaticAttributes
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatPairTagChildren
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatPairTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAssignmentElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::hasDuplicateAttributeNames
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatMarkupElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatMarkupElement
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isWhiteSpaceSensitive
     * @covers \Phug\Formatter\Format\TwigHtmlFormat::__construct
     */
    public static function testConvert()
    {
        $html = str_replace("\n", '', trim(PugToTwig::convert(implode("\n", [
            'ul#users',
            '  - for user in users',
            '    li.user',
            '      // comment',
            '      = user.name',
            '      | Email: #{user.email}',
            '      a(href=user.url) Home page',
        ]))));

        self::assertSame('<ul id="users">{% for user in users %}'.
            '<li class="user">{#  comment #}{{ user.name | e }}'.
            'Email: {{ user.email | e }}<a href="{{ user.url | e }}">Home page</a></li>'.
            '{% endfor %}</ul>', $html);
    }

    /**
     * @covers ::enableTwigFormatter
     * @covers \Phug\TwigExtension::__construct
     */
    public static function testExtension()
    {
        class_exists('\Phug\Renderer') || class_alias('\Phug\Renderer', '\Phug\Renderer');
        new TwigExtension(new \Phug\Renderer());
        $compiler = new Compiler([
            'compiler_modules' => [TwigExtension::class],
        ]);
        $html = str_replace("\n", '', trim($compiler->compile(implode("\n", [
            'ul#users',
            '  - for user in users',
            '    li.user',
            '      // comment',
            '      = user.name',
            '      | Email: #{user.email}',
            '      a(href=user.url) Home page',
        ]))));

        self::assertSame('<ul id="users">{% for user in users %}'.
            '<li class="user">{#  comment #}{{ user.name | e }}'.
            'Email: {{ user.email | e }}<a href="{{ user.url | e }}">Home page</a></li>'.
            '{% endfor %}</ul>', $html);
    }
}
