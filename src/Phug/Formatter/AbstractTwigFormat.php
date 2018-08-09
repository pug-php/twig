<?php

namespace Phug\Formatter;

use Phug\Formatter;
use Phug\Formatter\Element\CodeElement;
use Phug\Formatter\Element\MarkupElement;
use Phug\Formatter\Format\XhtmlFormat;

abstract class AbstractTwigFormat extends XhtmlFormat
{
    protected $nestedCodes = [];
    protected $codeBlocks = [];
    protected $phpMode = true;
    protected $statements = [
        'if',
        'else',
        'elseif',
        'for',
        'autoescape',
        'block',
        'do',
        'embed',
        'extends',
        'filter',
        'flush',
        'from',
        'import',
        'include',
        'macro',
        'sandbox',
        'set',
        'spaceless',
        'use',
        'verbatim',
        'with',
    ];

    protected function replaceTwigBlocks($input, $wrap = false)
    {
        $pugModuleName = '$'.$this->formatter->getOption('dependencies_storage');
        if ($this->mustBeHandleWithPhp($input, $pugModuleName)) {
            $input = preg_replace_callback(
                '/\{\[block:(\d+)\]\}/',
                function ($match) {
                    return ' ?>'.$this->codeBlocks[intval($match[1])].'<?php ';
                },
                $input
            );
            $this->phpMode = true;

            return $wrap ? "<?php $input ?>" : $input;
        }

        $this->phpMode = false;
        $statement = $input;
        $parts = explode(' ', $input, 2);
        if (count($parts) === 2) {
            list($statement, $input) = $parts;
            $statement = $statement === 'each' ? 'for' : $statement;
            $input = "$statement $input";
        }
        $hasBlocks = false;
        $input = preg_replace_callback(
            '/\{\[block:(\d+)\]\}/',
            function ($match) use (&$hasBlocks) {
                $hasBlocks = true;

                return ' %}'.$this->codeBlocks[intval($match[1])].'{% ';
            },
            $input
        );
        if ($hasBlocks) {
            $statement = preg_replace('/^([^\\{]+)\\{.*$/', '$1', $statement);
            if (in_array($statement, $this->statements)) {
                $input .= 'end'.preg_replace('/^else/', 'if', $statement);
            }
        }

        return $wrap && trim($input) !== '' ? "{% $input %}" : $input;
    }

    public function __construct(Formatter $formatter = null)
    {
        parent::__construct($formatter);

        $this->nestedCodes = [];
        $this->codeBlocks = [];
        $this->phpMode = true;
        $this
            ->setOptionsRecursive([
                'php_token_handlers' => [
                    T_VARIABLE => function ($string) {
                        return $string;
                    },
                ],
            ])
            ->setPatterns([
                'class_attribute'        => '%s',
                'string_attribute'       => '%s',
                'expression_in_text'     => '%s',
                'html_expression_escape' => '%s | e',
                'php_nested_html'        => function ($input) {
                    return $this->phpMode ? " ?>$input<?php " : " %}$input{% ";
                },
                'php_handle_code'        => function ($input) {
                    return $this->replaceTwigBlocks($input, true);
                },
                'php_nested_html' => '%s',
                'php_block_code'  => function ($input) {
                    $id = count($this->codeBlocks);
                    $this->codeBlocks[] = $input;

                    return '{[block:'.$id.']}';
                },
                'php_display_code' => function ($input) {
                    $pugModuleName = '$'.$this->formatter->getOption('dependencies_storage');
                    if ($this->mustBeHandleWithPhp($input, $pugModuleName)) {
                        return "<?= $input ?>";
                    }

                    return "{{ $input }}";
                },
                'display_comment' => '{# %s #}',
            ]);
    }

    protected function mustBeHandleWithPhp($input, $pugModuleName)
    {
        return strpos($input, $pugModuleName) !== false ||
            strpos($input, '$__pug_mixins') !== false ||
            strpos($input, '$__pug_children') !== false;
    }

    protected function formatAttributes(MarkupElement $element)
    {
        $code = '';

        foreach ($element->getAttributes() as $attribute) {
            $code .= $this->format($attribute);
        }

        foreach ($element->getAssignments() as $assignment) {
            $code .= $this->format($assignment);
        }

        return $code;
    }

    protected function formatElementChildren(ElementInterface $element, $indentStep = 1)
    {
        $indentLevel = $this->formatter->getLevel();
        $this->formatter->setLevel($indentLevel + $indentStep);
        $content = '';
        $previous = null;
        $commentPattern = $this->getOption('debug');
        foreach ($this->getChildrenIterator($element) as $child) {
            if (!($child instanceof ElementInterface)) {
                continue;
            }

            $childContent = $this->formatter->format($child);

            if ($child instanceof CodeElement &&
                $previous instanceof CodeElement &&
                $previous->isCodeBlock()
            ) {
                $content = preg_replace('/\\s\\?>$/', '', $content);
                $childContent = preg_replace('/^<\\?(?:php)?\\s/', '', $childContent);
                if ($commentPattern &&
                    ($pos = mb_strpos($childContent, $commentPattern)) !== false && (
                        ($end = mb_strpos($childContent, '?>')) === false ||
                        $pos < $end
                    ) &&
                    preg_match('/\\}\\s*$/', $content)
                ) {
                    $content = preg_replace(
                        '/\\}\\s*$/',
                        preg_replace('/\\?><\\?php(?:php)?(\s+\\?><\\?php(?:php)?)*/', '\\\\0', $childContent, 1),
                        $content
                    );
                    $childContent = '';
                }
            }

            if (preg_match('/^\{% else/', $childContent)) {
                $content = preg_replace('/\{% end(if)+ %\}$/', '', $content);
            }
            $content .= $this->replaceTwigBlocks($childContent);
            $previous = $child;
        }
        $this->formatter->setLevel($indentLevel);

        return $content;
    }
}
