<?php

namespace BayCMS\Field;

use BayCMS\Fieldset\Form;

class SelectCheckbox extends SelectMulti
{
    protected array $fields = [];

    protected array $v_hash = [];

    protected string $free_input;

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
        array $default_value = [],
        string $div_id = '',
        bool $null = false,
        array $values = null,
        string $db_query = '',
        string $free_input = ''
    ) {
        parent::__construct(
            $context,
            $name,
            $description,
            $id,
            $sql,
            $help,
            $label_css,
            $input_options,
            $post_input,
            $placeholder,
            $no_add_to_query,
            $not_in_table,
            $non_empty,
            $footnote,
            $default_value,
            $div_id,
            $null,
            $values,
            $db_query
        );
        $this->free_input = $free_input;

        if (is_null($this->values)) {
            $res = pg_query($this->context->getDbConn(), $this->db_query);
            $this->values = [];
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_row($res, $i);
                $this->values[] = $r;
            }
        }
        for ($i = 0; $i < count($this->values); $i++) {
            if (!is_array($this->values[$i]))
                $this->values[$i] = [$this->values[$i], $this->$values[$i]];
            $this->v_hash[$this->values[$i][0]]=1;
            $this->fields[] = new Checkbox(
                context: $context,
                name: $this->name . '_' . $i,
                description: $this->values[$i][1],
                default_value: in_array($this->values[$i][0], $default_value)
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
        $this->error = false;
        if (! ($v[$this->name] ?? false)) {
            //from POST!
            $value = [];
            for ($i = 0; $i < count($this->values); $i++) {
                if ($v[$this->name . '_' . $i] ?? false)
                    $value[] = $this->values[$i][0];
            }
        } else {
            $value = explode(",", $v[$this->name] ?? '');
            foreach($value as $v2){
                if(! ($this->v_hash[$v2]??false)){
                    $v[$this->name . '_free']=$v2;
                    break;
                }
            }
        }
        $i = 0;
        while ($i < count($this->values)) {
            $this->fields[$i]->setValue(in_array($this->values[$i][0], $value)?'t':'f');
            $i++;
        }


        if (($v[$this->name . '_free'] ?? false) && !in_array($v[$this->name . '_free'], $this->v_hash)) {
            $this->fields[$i]->setValue($v[$this->name . '_free']);
            $value[] = $v[$this->name . '_free'];
        }
        $this->value = $value;
        if ($this->non_empty && !count($this->value))
            $this->error = true;
        return (bool) $this->error;
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        return '';
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





