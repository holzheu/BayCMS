<?php

namespace BayCMS\Field;

class Password extends TextInput {
    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $this->input_options.=' type="password"';
        return parent::getInput($form);
    }
}