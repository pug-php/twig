<?php

namespace Phug\Formatter\Format;

use Phug\Formatter;
use Phug\Formatter\AbstractTwigFormat;
use Phug\Formatter\Element\AbstractValueElement;
use Phug\Formatter\Element\AssignmentElement;
use Phug\Formatter\Element\AttributeElement;
use Phug\Formatter\Element\CodeElement;
use Phug\Formatter\Element\ExpressionElement;
use Phug\Formatter\Element\MarkupElement;
use Phug\Formatter\Element\TextElement;
use Phug\Formatter\ElementInterface;
use Phug\Formatter\MarkupInterface;
use Phug\Formatter\Partial\AssignmentHelpersTrait;

class TwigXmlFormat extends AbstractTwigFormat
{
    use AssignmentHelpersTrait;

    const DOCTYPE = '<?xml version="1.0" encoding="utf-8" ?>';
    const OPEN_PAIR_TAG = '<%s>';
    const CLOSE_PAIR_TAG = '</%s>';
    const SELF_CLOSING_TAG = '<%s />';
    const ATTRIBUTE_PATTERN = ' %s="%s"';
    const BOOLEAN_ATTRIBUTE_PATTERN = ' %s="%s"';
    const BUFFER_VARIABLE = '$__value';

    public function __construct(Formatter $formatter = null)
    {
        parent::__construct($formatter);

        $defaults = [];
        foreach (['attributes_mapping', 'assignment_handlers', 'attribute_assignments'] as $option) {
            $defaults[$option] = ($formatter->hasOption($option) ? $formatter->getOption($option) : null) ?: [];
        }
        $this
            ->setOptionsDefaults($defaults)
            ->registerHelper('available_attribute_assignments', [])
            ->addPatterns([
                'open_pair_tag'             => static::OPEN_PAIR_TAG,
                'close_pair_tag'            => static::CLOSE_PAIR_TAG,
                'self_closing_tag'          => static::SELF_CLOSING_TAG,
                'attribute_pattern'         => static::ATTRIBUTE_PATTERN,
                'boolean_attribute_pattern' => static::BOOLEAN_ATTRIBUTE_PATTERN,
                'save_value'                => static::SAVE_VALUE,
                'buffer_variable'           => static::BUFFER_VARIABLE,
            ])
            ->provideAttributeAssignments()
            ->provideAttributeAssignment()
            ->provideStandAloneAttributeAssignment()
            ->provideMergeAttributes()
            ->provideTwigArrayEscape()
            ->provideAttributesAssignment()
            ->provideClassAttributeAssignment()
            ->provideStandAloneClassAttributeAssignment()
            ->provideStyleAttributeAssignment()
            ->provideStandAloneStyleAttributeAssignment();

        $handlers = $this->getOption('attribute_assignments');
        foreach ($handlers as $name => $handler) {
            $this->addAttributeAssignment($name, $handler);
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function provideTwigArrayEscape()
    {
        if (!method_exists($this, 'provideArrayEscape')) {
            return $this;
        }

        return $this->provideArrayEscape();
    }

    protected function addAttributeAssignment($name, $handler)
    {
        $availableAssignments = $this->getHelper('available_attribute_assignments');
        $this->registerHelper($name.'_attribute_assignment', $handler);
        $availableAssignments[] = $name;

        return $this->registerHelper('available_attribute_assignments', $availableAssignments);
    }

    public function __invoke(ElementInterface $element)
    {
        return $this->format($element);
    }

    public function isSelfClosingTag(MarkupInterface $element, $isSelfClosing = null)
    {
        if (is_null($isSelfClosing)) {
            $isSelfClosing = $element->isAutoClosed();
        }

        if ($isSelfClosing && $element->hasChildren()) {
            $visibleChildren = array_filter($element->getChildren(), function ($child) {
                return $child && (
                    !($child instanceof TextElement) ||
                    trim($child->getValue()) !== ''
                );
            });
            if (count($visibleChildren) > 0) {
                $this->throwException(
                    $element->getName().' is a self closing element: '.
                    '<'.$element->getName().'/> but contains nested content.',
                    $element
                );
            }
        }

        return $isSelfClosing;
    }

    protected function isBlockTag(MarkupInterface $element)
    {
        return true;
    }

    public function isWhiteSpaceSensitive(MarkupInterface $element)
    {
        return false;
    }

    protected function formatAttributeElement(AttributeElement $element)
    {
        $value = $element->getValue();
        $name = $element->getName();
        $nonEmptyAttribute = ($name === 'class' || $name === 'id');
        if ($value instanceof TextElement && $nonEmptyAttribute && (!$value->getValue() || $value->getValue() === '')) {
            return '';
        }
        if ($nonEmptyAttribute && (!$value || (is_string($value) && in_array(trim($value), ['', '""', "''"])))) {
            return '';
        }
        if ($value instanceof ExpressionElement) {
            if ($nonEmptyAttribute && in_array(trim($value->getValue()), ['', '""', "''"])) {
                return '';
            }
            if (strtolower($value->getValue()) === 'true') {
                return $this->formatAttributeFlag($name);
            }
            if (in_array(strtolower($value->getValue()), ['false', 'null', 'undefined'])) {
                return '';
            }
        }

        return $this->pattern(
            'attribute_pattern',
            $this->format($name),
            $this->format($value)
        );
    }

    protected function formatAttributeFlag($name)
    {
        $formattedValue = null;

        if ($name instanceof ExpressionElement) {
            $bufferVariable = $this->pattern('buffer_variable');
            $name = $this->pattern(
                'php_display_code',
                $this->pattern(
                    'save_value',
                    $bufferVariable,
                    $this->formatCode($name->getValue(), $name->isChecked())
                )
            );
            $value = new ExpressionElement($bufferVariable);
            $formattedValue = $this->format($value);
        }

        $formattedName = $this->format($name);
        $formattedValue = $formattedValue || $formattedValue === '0'
            ? $formattedValue
            : $formattedName;

        return $this->pattern(
            'boolean_attribute_pattern',
            $formattedName,
            $formattedValue
        );
    }

    protected function formatPairTagChildren(MarkupElement $element)
    {
        $firstChild = $element->getChildAt(0);
        $needIndent = (
            (
                (
                    $firstChild instanceof CodeElement &&
                    $this->isBlockTag($element)
                ) || (
                    $firstChild instanceof MarkupInterface &&
                    $this->isBlockTag($firstChild)
                )
            ) &&
            !$this->isWhiteSpaceSensitive($element)
        );

        return sprintf(
            $needIndent
                ? $this->getNewLine().'%s'.$this->getIndent()
                : '%s',
            $this->formatElementChildren($element)
        );
    }

    protected function formatPairTag($open, $close, MarkupElement $element)
    {
        return $this->pattern(
            'pair_tag',
            $open,
            $element->hasChildren()
                ? $this->formatPairTagChildren($element)
                : '',
            $close
        );
    }

    protected function formatAssignmentElement(AssignmentElement $element)
    {
        $handlers = $this->getOption('assignment_handlers');
        $newElements = [];
        array_walk(
            $handlers,
            function (callable $handler) use (&$newElements, $element) {
                $iterator = $handler($element) ?: [];
                foreach ($iterator as $newElement) {
                    $newElements[] = $newElement;
                }
            }
        );

        $markup = $element->getContainer();

        $arguments = [];
        $attributes = $markup->getAssignmentsByName('attributes');
        array_walk(
            $attributes,
            function (AssignmentElement $attributesAssignment) use (&$arguments, $markup) {
                $attributes = iterator_to_array($attributesAssignment->getAttributes());
                array_walk(
                    $attributes,
                    function (AbstractValueElement $attribute) use (&$arguments) {
                        $value = $attribute;
                        $checked = method_exists($value, 'isChecked') && $value->isChecked();
                        while (method_exists($value, 'getValue')) {
                            $value = $value->getValue();
                        }
                        $arguments[] = $this->formatCode($value, $checked);
                    }
                );
                $markup->removedAssignment($attributesAssignment);
            }
        );

        $attributes = $markup->getAttributes();
        $attributesArray = iterator_to_array($attributes);
        array_walk(
            $attributesArray,
            function (AttributeElement $attribute) use (&$arguments) {
                $arguments[] = $this->formatAttributeAsArrayItem($attribute);
            }
        );
        $attributes->removeAll($attributes);

        if (count($arguments)) {
            $newElements[] = $this->attributesAssignmentsFromPairs($arguments);
        }

        return implode('', array_map([$this, 'format'], $newElements));
    }
}
