<?php

namespace BayCMS\Field;

class Radio extends TextInput
{
    protected string $field_value;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        ?string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = 'width:auto;',
        string $input_options = '',
        string $post_input='',
        string $placeholder = '',
        bool $no_add_to_query = false,
        bool $not_in_table = false,        
        bool $non_empty=false,
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        ?string $field_value = null
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css,$input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id);
        if(is_null($field_value)) $field_value=$name;
        $this->field_value=$field_value;
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "<input type=\"radio\" id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        if ($this->placeholder ?? '')
            $out .= 'placeholder="' . htmlspecialchars($this->placeholder) . '"';

        $out .= " value=\"" . htmlspecialchars($this->field_value) . "\"";
        $out .= $this->input_options;
        if ($this->value == $this->field_value)
            $out .= ' checked';
        $out .= ">";
        if($this->error) $out.=$this->addErrorClass($this->getID($form));
        return $out;
    }

    public function getValue(): mixed
    {
        if ($this->value == $this->field_value)
            return $this->value;
        return null;
    }

    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        $out = '<div class="' . $this->context->TE->getCSSClass('form_div') . '">';
        $out .= $this->getInput($form);
        $out .= ' <label for="' . $this->getID($form) . '"';
        $out .= ' style="' . $this->label_css . '"';
        $out .= '>' . $this->getDescription() . $this->getHelp($form) . '</label>';
        $out .= "</div>\n";
        return $out;
    }




}