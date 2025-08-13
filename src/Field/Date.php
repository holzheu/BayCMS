<?php

namespace BayCMS\Field;

class Date extends TextInput
{
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        ?string $description = null,
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
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id = ''
    ) {
        $input_options .= ' type="date"';
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
    }


    public function getDisplayValue(): string
    {
        if (!$this->value)
            return '';
        $dt = new \DateTime($this->value);
        if ($this->context->lang == 'en')
            return $dt->format('Y-m-d');
        else
            return $dt->format('d.m.Y');
    }

    public function getValue(): mixed
    {
        if ($this->value !== null && $this->value > '')
            return $this->value;
        return null;
    }

    public function setValue($value): bool
    {
        if (!$value)
            $value = null;
        if ($value) {
            $dt = new \DateTime($value);
            $value = $dt->format('Y-m-d');
        }
        return parent::setValue($value);
    }

    public function getSQL($prefix = '', $target = 'html'): string
    {
        if ($this->sql)
            return $this->sql;
        if ($target != 'html')
            return $prefix . $this->name;
        if ($this->context->lang == 'de')
            return "to_char($prefix" . $this->name . ",'DD.MM.YYYY')";
        return parent::getSQL($prefix);
    }

}