<?php

namespace BayCMS\Field;

class Email extends TextInput
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
        string $post_input='',
        string $placeholder = '',
        bool $no_add_to_query = false,
        bool $not_in_table = false,
        bool $non_empty = false,
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id=''
    ) {
        $input_options .= ' type="email"';
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id);
    }
    

    public function getDisplayValue(): string
    {
        if (!$this->value)
            return '';
        return '<a href="mailto:' . $this->value . '">' . $this->value . '</a>';
    }

}