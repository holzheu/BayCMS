<?php

namespace BayCMS\Field;

class SelectRadio extends Select
{
    protected array $fields = [];

    protected string $free_input;
    


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
        string $div_id='',
        bool $null = false,
        ?array $values = null,
        string $db_query = '',
        string $free_input = ''
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help,
         $label_css, $input_options, $post_input, $placeholder, 
         $no_add_to_query, $not_in_table, $non_empty, 
         $footnote, $default_value, $div_id, $null, 
         $values, $db_query);
        $this->free_input = $free_input;

        if (is_null($this->values)) {
            $res = pg_query($this->context->getDbConn(), $this->db_query);
            $this->values = [];
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_row($res, $i);
                $this->values[] = $r;
            }
        }
        foreach ($this->values as $v) {
            $this->fields[] = new Radio(
                context: $context,
                name: $this->name,
                field_value: $v[0],
                description: $v[1],
                default_value: $default_value
            );
        }
        if ($this->free_input) {
            $this->fields[] = new TextInput(
                context: $context,
                name: $this->name . '_free',
                description: $this->free_input
            );
        }

    }


    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        if ($v[$this->name . '_free'] ?? false) {
            return $this->setValue($v[$this->name . '_free']);
        }
        return $this->setValue($v[$this->name] ?? null);
    }

    public function setValue($value): bool
    {
        $this->error = 0;
        foreach ($this->fields as $f) {
            $f->setValue($value);
            if ($value == $f->getValue()) {
                break;
            }
        }
        if (!$value && ($this->non_empty)) {
            $this->error = 1;
        }
        foreach ($this->fields as $f) {
            $f->setError($this->error);
        }
        $this->value = $value;
        return $this->error;
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        return '';
    }


    public function getDisplayValue(): string
    {
        $display_value = parent::getDisplayValue();
        if (!$display_value)
            $display_value = htmlspecialchars($this->value);
        return $display_value;
    }

    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        $out = '<div class="' . $this->context->TE->getCSSClass('form_div') . '">';
        $out .= '<label';
        if ($this->label_css)
            $out .= ' style="' . $this->label_css . '"';
        $out .= '>' . $this->getDescription() . $this->getHelp($form) . '</label>' . "<br/>\n";
        $i = 0;
        foreach ($this->fields as $f) {
            $f->setID($form->getName() . '_' . $this->name . $i);
            $i++;
            $out .= $f->getInput($form);
            $out .= ' <label for="' . $f->getID($form) . '"';
            $out .= ' style="' . $f->getLabelCSS() . '"';
            $out .= '>' . $f->getDescription() . '</label>';
            $out .= "<br/>\n";
        }
        if ($this->free_input) {
            $out .= "<script>$('#" . $f->getID($form) . "').on('click', function(){
            $('input[name=\"" . $this->name . "\"]').prop('checked', false)
        })</script>";
        }

        $out .= "</div>\n";
        return $out;
    }

}