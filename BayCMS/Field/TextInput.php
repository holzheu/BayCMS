<?php

namespace BayCMS\Field;

class TextInput extends Field
{

    public function getValue(): mixed
    {
        if ($this->value !== null)
            return $this->value;
        return "";
    }




    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "<input id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        if ($this->placeholder)
            $out .= 'placeholder="' . htmlspecialchars($this->placeholder) . '"';

        $out .= " value=\"" . htmlspecialchars($this->value) . "\"";
        $out .= $this->input_options . ">";
        if($this->error) $out.=$this->addErrorClass($this->getID($form));
        return $out;

    }



}