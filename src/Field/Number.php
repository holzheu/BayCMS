<?php

namespace BayCMS\Field;

class Number extends TextInput
{
    protected ?float $min;
    protected ?float $max;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = '',
        string $post_input = '',
        string $placeholder = '',
        bool $no_add_to_query = false,
        bool $not_in_table = false,
        bool $non_empty = false,
        array $footnote = null,
        mixed $default_value = null,
        string $div_id = '',
        float $step = null,
        float $min = null,
        float $max = null
    ) {
        $input_options .= ' type="number"';
        if (!is_null($step))
            $input_options .= ' step="' . $step . '"';
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
        $this->min = $min;
        $this->max = $max;
    }


    public function getDisplayValue(): string
    {
        if (!$this->value)
            return '';
        if ($this->context->lang == 'en')
            return $this->value;
        return str_replace('.', ',', $this->value);
    }

    public function getValue(): mixed
    {
        if ($this->value !== null)
            return $this->value;
        return null;
    }

    public function setValue($value): bool
    {
        if ($value === "null")
            $value = null;
        if($value === '')
            $value=null;
        $this->error = false;
        if (!is_null($value)) {
            if (!is_null($this->min) && !is_null($value) && $value < $this->min) {
                $this->error = true;
                $this->inline_error = $this->t('The value must be above ' . $this->min, 'Der Wert muss größer als ' . $this->min . ' sein.');

            }
            if (!is_null($this->max) && !is_null($value) && $value > $this->max) {
                $this->inline_error = $this->t('The value must be below ' . $this->max, 'Der Wert muss kleiner als ' . $this->max . ' sein.');
                $this->error = true;
            }
        }
        $this->value = $value;
        if ($this->non_empty && $this->value === null)
            $this->error = true;
        return (bool) $this->error;
    }
}