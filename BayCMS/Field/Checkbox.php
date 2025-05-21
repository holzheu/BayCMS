<?php

namespace BayCMS\Field;


class Checkbox extends Radio
{
 
    public function getValue(): string
    {
        if (!$this->value && $this->value!=='f')
            return 'f';
        return 't';
    }

    public function setValue($value): bool
    {
        if($value=='f') $value=false;
        $this->error = false;
        if ($this->non_empty && !$value)
            $this->error = true;
        $this->value = $value;
        return (bool) $this->error;
    }

    public function getDisplayValue(): string
    {
        if ($this->value && $this->value!=='f')
            return ($this->context->lang == 'de' ? 'Ja' : 'Yes');
        return ($this->context->lang == 'de' ? 'Nein' : 'No');
    }


    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "<input type=\"checkbox\" id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        if ($this->value && $this->value!=='f')
            $out .= ' checked';
        $out .= $this->input_options . ">";
        if($this->error) $out.=$this->addErrorClass($this->getID($form));
        return $out;

    }



}