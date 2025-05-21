<?php

namespace BayCMS\Field;

class Hidden extends TextInput{
    protected string $type;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $description = null,
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
        array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        string $type='string'
    ) {
        $input_options .= ' type="hidden"';
        $this->type=$type;
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id);
    }

    public function getTableRow(): string
    {
        return '';
    }

    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        return $this->getInput($form);
    }

    public function getValue(): mixed
    {
        if($this->type=='int')
            return (int) $this->value;
        if($this->type=='float')
            return (float) $this->value;
        return $this->value;
    }

}