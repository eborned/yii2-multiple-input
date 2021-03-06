<?php

/**
 * @link https://github.com/unclead/yii2-multiple-input
 * @copyright Copyright (c) 2014 unclead
 * @license https://github.com/unclead/yii2-multiple-input/blob/master/LICENSE.md
 */

namespace unclead\widgets;

use Closure;
use yii\base\InvalidConfigException;
use yii\base\Object;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;

/**
 * Class MultipleInputColumn
 * @package unclead\widgets
 */
class MultipleInputColumn extends Object
{
    const TYPE_TEXT_INPUT       = 'textInput';
    const TYPE_HIDDEN_INPUT     = 'hiddenInput';
    const TYPE_DROPDOWN         = 'dropDownList';
    const TYPE_LISTBOX          = 'listBox';
    const TYPE_CHECKBOX_LIST    = 'checkboxList';
    const TYPE_RADIO_LIST       = 'radioList';
    const TYPE_STATIC           = 'static';
    const TYPE_CHECKBOX         = 'checkbox';
    const TYPE_RADIO            = 'radio';

    /**
     * @var string input name
     */
    public $name;

    /**
     * @var string the header cell content. Note that it will not be HTML-encoded.
     */
    public $title;

    /**
     * @var string input type
     */
    public $type;

    /**
     * @var string|Closure
     */
    public $value;

    /**
     * @var mixed default value for input
     */
    public $defaultValue = '';

    /**
     * @var array
     */
    public $items;

    /**
     * @var array
     */
    public $options;

    /**
     * @var array the HTML attributes for the header cell tag.
     */
    public $headerOptions = [];

    /**
     * @var bool whether to render inline error for the input. Default to `false`
     */
    public $enableError = false;

    /**
     * @var array the default options for the error tag
     */
    public $errorOptions = ['class' => 'help-block help-block-error'];

    /**
     * @var MultipleInput
     */
    public $widget;

    public function init()
    {
        parent::init();

        if (empty($this->name)) {
            throw new InvalidConfigException("The 'name' option is required.");
        }

        if ($this->enableError && empty($this->widget->model)) {
            throw new InvalidConfigException('Property "enableError" available only when model is defined.');
        }

        if (is_null($this->type)) {
            $this->type = self::TYPE_TEXT_INPUT;
        }

        if (empty($this->options)) {
            $this->options = [];
        }
    }

    /**
     * @return bool whether the type of column is hidden input.
     */
    public function isHiddenInput()
    {
        return $this->type == self::TYPE_HIDDEN_INPUT;
    }


    /**
     * @return bool whether the column has a header
     */
    public function hasHeader()
    {
        return !empty($this->title);
    }

    /**
     * Renders the header cell.
     * @return null|string
     */
    public function renderHeaderCell()
    {
        if ($this->isHiddenInput()) {
            return null;
        }

        $options = $this->headerOptions;
        Html::addCssClass($options, 'list-cell__' . $this->name);
        return Html::tag('th', $this->title, $options);
    }

    /**
     * Prepares the value of column.
     *
     * @param array|ActiveRecord $data
     * @return mixed
     */
    public function prepareValue($data)
    {
        if ($this->value !== null) {
            $value = $this->value;
            if ($value instanceof \Closure) {
                $value = call_user_func($value, $data);
            }
        } else {
            if ($data instanceof ActiveRecord) {
                $value = $data->getAttribute($this->name);
            } elseif (is_array($data)) {
                $value = ArrayHelper::getValue($data, $this->name, null);
            } elseif(is_string($data)) {
                $value = $data;
            }else {
                $value = $this->defaultValue;
            }
        }
        return $value;
    }

    /**
     * Renders the cell content.
     *
     * @param string $value
     * @param int|null $index
     * @return string
     * @throws InvalidConfigException
     */
    public function renderCellContent($value, $index)
    {
        $name = $this->widget->getElementName($this->name, $index);

        $options = $this->options;
        $options['id'] = $this->widget->getElementId($this->name, $index);

        $input = $this->renderInput($name, $value, $options);

        if ($this->isHiddenInput()) {
            return $input;
        }

        $hasError = false;
        if ($this->enableError) {
            $attribute = $this->widget->attribute . $this->widget->getElementName($this->name, $index, false);
            $error = $this->widget->model->getFirstError($attribute);
            $hasError = !empty($error);
            $input .= "\n" . $this->renderError($error);
        }

        $wrapperOptions = [
            'class' => 'form-group field-' . $options['id']
        ];

        if ($hasError) {
            Html::addCssClass($wrapperOptions, 'has-error');
        }
        $input = Html::tag('div', $input, $wrapperOptions);

        return Html::tag('td', $input, [
            'class' => 'list-cell__' . $this->name,
        ]);
    }

    /**
     * Renders the input.
     *
     * @param string $name name of the input
     * @param mixed $value value of the input
     * @param array $options the input options
     * @return string
     */
    private function renderInput($name, $value, $options)
    {
        $method = 'render' . Inflector::camelize($this->type);

        if (method_exists($this, $method)) {
            $input = call_user_func_array([$this, $method], [$name, $value, $options]);
        } else {
            $input = $this->renderDefault($name, $value, $options);
        }
        return $input;
    }


    protected function renderDropDownList($name, $value, $options)
    {
        Html::addCssClass($options, 'form-control');
        return Html::dropDownList($name, $value, $this->items, $options);
    }

    protected function renderListBox($name, $value, $options)
    {
        Html::addCssClass($options, 'form-control');
        return Html::listBox($name, $value, $this->items, $options);
    }

    protected function renderHiddenInput($name, $value, $options)
    {
        return Html::hiddenInput($name, $value, $options);
    }

    protected function renderRadio($name, $value, $options)
    {
        if (!isset($options['label'])) {
            $options['label'] = '';
        }
        $input = Html::radio($name, $value, $options);
        return Html::tag('div', $input, ['class' => 'radio']);
    }

    protected function renderRadioList($name, $value, $options)
    {
        $options['item'] = function ($index, $label, $name, $checked, $value) {
            return '<div class="radio">' . Html::radio($name, $checked, ['label' => $label, 'value' => $value]) . '</div>';
        };
        $input = Html::radioList($name, $value, $this->items, $options);
        return Html::tag('div', $input, ['class' => 'radio']);
    }

    protected function renderCheckbox($name, $value, $options)
    {
        if (!isset($options['label'])) {
            $options['label'] = '';
        }
        $input = Html::checkbox($name, $value, $options);
        return Html::tag('div', $input, ['class' => 'checkbox']);
    }

    protected function renderCheckboxList($name, $value, $options)
    {
        $options['item'] = function ($index, $label, $name, $checked, $value) {
            return '<div class="checkbox">' . Html::checkbox($name, $checked, ['label' => $label, 'value' => $value]) . '</div>';
        };
        $input = Html::checkboxList($name, $value, $this->items, $options);
        return Html::tag('div', $input, ['class' => 'checkbox']);
    }

    protected function renderDefault($name, $value, $options)
    {
        $type = $this->type;

        if ($type == self::TYPE_STATIC) {
            $input = Html::tag('p', $value, ['class' => 'form-control-static']);
        } elseif (method_exists('yii\helpers\Html', $type)) {
            Html::addCssClass($options, 'form-control');
            $input = Html::$type($name, $value, $options);
        } elseif (class_exists($type) && method_exists($type, 'widget')) {
            $input = $type::widget(array_merge($options, [
                'name'  => $name,
                'value' => $value,
            ]));
        } else {
            throw new InvalidConfigException("Invalid column type '$type'");
        }
        return $input;
    }

    /**
     * @param string $error
     * @return string
     */
    private function renderError($error)
    {
        $options = $this->errorOptions;
        $tag = isset($options['tag']) ? $options['tag'] : 'div';
        $encode = !isset($options['encode']) || $options['encode'] !== false;
        unset($options['tag'], $options['encode']);
        return Html::tag($tag, $encode ? Html::encode($error) : $error, $options);
    }
}