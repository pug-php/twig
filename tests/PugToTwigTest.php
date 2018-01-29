<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use Phug\Compiler;
use Phug\Formatter\Element\AssignmentElement;
use Phug\Formatter\Element\AttributeElement;
use Phug\TwigExtension;
use PugToTwig;

/**
 * @coversDefaultClass \Phug\PugToTwig
 */
class PugToTwigTest extends TestCase
{
    protected static function compile($pugCode, $options = [])
    {
        return str_replace("\n", '', trim(PugToTwig::convert($pugCode, $options)));
    }

    protected static function render($pugCode, $options = [])
    {
        $php = trim(PugToTwig::convert($pugCode, $options));
        ob_start();
        eval('?>'.$php);
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    /**
     * @group i
     * @covers ::convert
     * @covers \Phug\Formatter\AbstractTwigFormat::__construct
     * @covers \Phug\Formatter\AbstractTwigFormat::mustBeHandleWithPhp
     * @covers \Phug\Formatter\AbstractTwigFormat::formatAttributes
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXmlFormat::addAttributeAssignment
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__invoke
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isWhiteSpaceSensitive
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatPairTagChildren
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatPairTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAssignmentElement
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isWhiteSpaceSensitive
     * @covers \Phug\Formatter\Format\TwigHtmlFormat::__construct
     */
    public static function testConvert()
    {
        $html = static::compile(implode("\n", [
            'ul#users',
            '  - for user in users',
            '    li.user',
            '      // comment',
            '      = user.name',
            '      | Email: #{user.email}',
            '      a(href=user.url) Home page',
        ]));

        self::assertSame('<ul id="users">{% for user in users %}'.
            '<li class="user">{#  comment #}{{ user.name | e }}'.
            'Email: {{ user.email | e }}<a href="{{ user.url | e }}">Home page</a></li>'.
            '{% endfor %}</ul>', $html);

        $html = static::render(implode("\n", [
            'mixin foo()',
            '  p=myVar',
            '+foo()',
        ]));

        self::assertSame('<p>{{ myVar | e }}</p>', $html);

        $html = static::render(implode("\n", [
            'mixin foo()',
            '  p Hello',
            'if 1 == 1',
            '  +foo()',
        ]));

        self::assertSame('{% if (1 == 1) %}<p>Hello</p>{% endif %}', $html);

        $html = static::render(implode("\n", [
            'mixin foo()',
            '  div',
            '    block',
            'if 1 == 1',
            '  +foo()',
            '    if 1 != 2',
            '      strong Bye',
        ]));

        self::assertSame(
            '{% if (1 == 1) %}<div>{% if (1 != 2) %}<strong>Bye</strong>{% endif %}</div>{% endif %}',
            $html
        );

        $html = static::render(implode("\n", [
            'if false',
            '  | A',
            'else',
            '  if true',
            '    | B',
            '  else',
            '    | C',
        ]));

        self::assertSame(
            '{% if (1 == 1) %}<div>{% if (1 != 2) %}<strong>Bye</strong>{% endif %}</div>{% endif %}',
            $html
        );

//        $html = static::render(implode("\n", [
//            'if 1 != 1',
//            '  | A',
//            '- else',
//            '  - if 1 == 1',
//            '    | B',
//            '  - else',
//            '    | C',
//        ]));
//
//        self::assertSame(
//            '{% if (1 == 1) %}<div>{% if (1 != 2) %}<strong>Bye</strong>{% endif %}</div>{% endif %}',
//            $html
//        );
    }

    /**
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isWhiteSpaceSensitive
     */
    public static function testPreHandle()
    {
        $html = static::render(implode("\n", [
            'pre',
            '  div',
            '    span',
        ]));

        self::assertSame(
            '<pre><div><span></span></div></pre>',
            $html
        );
    }

    /**
     * @covers \Phug\Formatter\Format\TwigHtmlFormat::__construct
     */
    public static function testHtmlDoctype()
    {
        $html = static::render(implode("\n", [
            'doctype html',
            'img',
            'img/',
        ]));

        self::assertSame(
            '<!DOCTYPE html><img><img/>',
            $html
        );
    }

    /**
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeElement
     */
    public static function testAttributes()
    {
        $html = static::render('div(class="" foo=null, bar=true biz=0 boz="0")');

        self::assertSame(
            '<div bar="bar" biz="0" boz="0"></div>',
            $html
        );
    }

    /**
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXmlFormat::addAttributeAssignment
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__invoke
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isWhiteSpaceSensitive
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatPairTagChildren
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatPairTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAssignmentElement
     */
    public static function testXmlDoctype()
    {
        $html = static::render(implode("\n", [
            'doctype xml',
            'img',
        ]));

        self::assertSame(
            '<?xml version="1.0" encoding="utf-8" ?><img></img>',
            $html
        );
    }

    /**
     * @covers                   \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @expectedException        \Phug\FormatterException
     * @expectedExceptionMessage input is a self closing element: <input/> but contains nested content
     */
    public static function testNestedInSelfClosing()
    {
        static::compile('input: div');
    }

    /**
     * @covers                   \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @expectedException        \Phug\FormatterException
     * @expectedExceptionMessage input is a self closing element: <input/> but contains nested content
     */
    public static function testMissingAssignment()
    {
        static::compile('input: div');
    }

    /**
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAssignmentElement
     */
    public static function testAssignments()
    {
        $GLOBALS['debug'] = true;
        $html = static::render(implode("\n", [
            'img&foo()&attributes(["foo" => "bar", "biz" => true])',
        ]), [
            'attribute_assignments' => [
                'foo' => function () {
                    return 'not-bar';
                },
            ],
            'assignment_handlers' => [
                function (AssignmentElement $assignment) {
                    if ($assignment->getName() === 'foo') {
                        $assignment->detach();

                        yield new AttributeElement('data-foo', '123');
                    }
                },
            ],
        ]);

        self::assertSame(
            '<img data-foo="123" foo="not-bar" biz="biz" />',
            $html
        );
    }

    /**
     * @covers ::enableTwigFormatter
     * @covers \Phug\TwigExtension::__construct
     * @covers \Phug\Formatter\AbstractTwigFormat::__construct
     */
    public static function testExtension()
    {
        class_exists('\Phug\Renderer') || class_alias('\Phug\Parser', '\Phug\Renderer');
        new TwigExtension(new \Phug\Renderer()); // This should fail silently

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

        self::assertSame(
            '$myPhpStyleVariable',
            $compiler->getFormatter()->formatCode('$myPhpStyleVariable', true)
        );
    }
}
