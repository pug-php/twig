<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use Phug\Compiler;
use Phug\Compiler\Event\NodeEvent;
use Phug\Formatter\Element\AssignmentElement;
use Phug\Formatter\Element\AttributeElement;
use Phug\Formatter\Element\ExpressionElement;
use Phug\Parser\Node\AttributeNode;
use Phug\Parser\Node\ElementNode;
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
     * @covers ::convert
     * @covers \Phug\Formatter\AbstractTwigFormat::__construct
     * @covers \Phug\Formatter\AbstractTwigFormat::replaceTwigBlocks
     * @covers \Phug\Formatter\AbstractTwigFormat::restoreBlockSubstitutes
     * @covers \Phug\Formatter\AbstractTwigFormat::replaceTwigPhpBlocks
     * @covers \Phug\Formatter\AbstractTwigFormat::replaceTwigTemplateBlocks
     * @covers \Phug\Formatter\AbstractTwigFormat::extractStatement
     * @covers \Phug\Formatter\AbstractTwigFormat::mustBeHandleWithPhp
     * @covers \Phug\Formatter\AbstractTwigFormat::formatAttributes
     * @covers \Phug\Formatter\AbstractTwigFormat::formatElementChildren
     * @covers \Phug\Formatter\AbstractTwigFormat::formatTwigChildElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::__construct
     * @covers \Phug\Formatter\Format\TwigXmlFormat::provideTwigArrayEscape
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
            '      //comment',
            '      = user.name',
            '      | Email: #{user.email}',
            '      a(href=user.url) Home page',
        ]));

        self::assertSame('<ul id="users">{% for user in users %}'.
            '<li class="user">{# comment #}{{ user.name | e }}'.
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
            '  p=myVar',
            '    block',
            'section',
            '  div: +foo()',
            '    span bar',
        ]));

        self::assertSame('<section><div><p>{{ myVar | e }}<span>bar</span></p></div></section>', $html);

        $html = static::render(implode("\n", [
            'mixin foo()',
            '  if true',
            '    block',
            '  else',
            '    div',
            '      block',
            '+foo()',
            '  span bar',
        ]));

        self::assertSame('{% if (true) %}<span>bar</span>{% else %}<div><span>bar</span></div>{% endif %}', $html);

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
            '- for apple in apples',
            '  p= apple',
            '- for banana in bananas',
            '  p= banana',
        ]));

        self::assertSame(
            '{% for apple in apples %}<p>{{ apple | e }}</p>{% endfor %}'.
            '{% for banana in bananas %}<p>{{ banana | e }}</p>{% endfor %}',
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
            '{% if (false) %}A{% else %}{% if (true) %}B{% else %}C{% endif %}{% endif %}',
            $html
        );

        $html = static::render(implode("\n", [
            'if 1 != 1',
            '  | A',
            'elseif 1 == 1',
            '  | B',
            'else',
            '  | C',
        ]));

        self::assertSame(
            '{% if (1 != 1) %}A{% elseif (1 == 1) %}B{% else %}C{% endif %}',
            $html
        );

        $html = static::render(implode("\n", [
            'if 1 == 1',
            '  div',
            '    if 1 != 2',
            '      strong Bye',
        ]));

        self::assertSame(
            '{% if (1 == 1) %}<div>{% if (1 != 2) %}<strong>Bye</strong>{% endif %}</div>{% endif %}',
            $html
        );
    }

    /**
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeElement
     * @covers \Phug\Formatter\Format\TwigXmlFormat::formatAttributeFlag
     */
    public static function testFlagAttribute()
    {
        $html = static::render(implode("\n", [
            'input&attributes(["checked" => true])',
        ]));

        self::assertSame(
            '<input checked="checked" />',
            $html
        );

        $html = static::render(implode("\n", [
            '- attr = "checked"',
            'input({{ attr }}=true)',
        ]));

        self::assertSame(
            '{% attr = "checked" %}<input {{ attr }}="{{ attr }}" />',
            $html
        );

        $html = static::render(implode("\n", [
            '- $var = "checked"',
            'input(dynamic=true)',
        ]), [
            'on_node' => function (NodeEvent $nodeEvent) {
                $node = $nodeEvent->getNode();
                if ($node instanceof ElementNode && $node->getName() === 'input') {
                    /** @var AttributeNode $attribute */
                    foreach ($node->getAttributes() as $attribute) {
                        if ($attribute->getName() === 'dynamic') {
                            $expression = new ExpressionElement();
                            $expression->setValue('$var');
                            $attribute->setName($expression);
                        }
                    }
                }
            },
        ]);

        self::assertSame(
            '{% $var = "checked" %}<input {{ $__value=$var }}="{{ $__value }}" />',
            $html
        );
    }

    /**
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isSelfClosingTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isBlockTag
     * @covers \Phug\Formatter\Format\TwigXmlFormat::isWhiteSpaceSensitive
     * @covers \Phug\Formatter\Format\TwigXhtmlFormat::isBlockTag
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
            'pre="hello"',
            'pre="hello" | upper',
        ]));

        self::assertSame(
            '<!DOCTYPE html><img><img/><pre>hello</pre><pre>{{ "hello" | upper | e }}</pre>',
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

        $html = static::render('div(foo="", \'bar\'=true)');

        self::assertSame(
            '<div foo="" bar="bar"></div>',
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
            'article',
            '  item(name=var)',
            '  item(name="bar")',
        ]));

        self::assertSame(
            '<?xml version="1.0" encoding="utf-8" ?><img></img>'.
            '<article><item name="{{ var | e }}"></item><item name="bar"></item></article>',
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
     * @covers \Phug\Formatter\AbstractTwigFormat::formatAttributes
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

    public function testInclude()
    {
        $compiler = new Compiler([
            'compiler_modules' => [TwigExtension::class],
        ]);

        self::assertSame(
            '<div class="container"><div id="inner">Hop<p>Inner</p></div></div>',
            $compiler->compileFile(__DIR__.'/a.pug')
        );
    }

    public function testExtend()
    {
        $compiler = new Compiler([
            'compiler_modules' => [TwigExtension::class],
        ]);

        self::assertSame(
            '<body><p>Hello</p></body>',
            $compiler->compileFile(__DIR__.'/c.pug')
        );
    }
}
