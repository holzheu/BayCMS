<?php

namespace BayCMS\Field;

class SelectMulti extends Select
{
    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $this->loadValues();
        $out = "<select id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "[]\" ";
        $out .= $this->input_options . " multiple>\n";

        $group = '';
        if (!isset($this->value) || !$this->value || !is_array($this->value))
            $this->value = [];
        foreach ($this->values as $v) {
            if ($group != ($v[2] ?? '')) {
                if ($group)
                    $out .= "</optgroup>\n";
                $group = $v[2];
                $out .= '<optgroup label="' . $group . '">' . "\n";
            }
            $out .= '<option value="' . htmlspecialchars($v[0]) . '"';
            if (in_array($v[0], $this->value))
                $out .= " selected";
            $out .= '>' . ($v[1] ?? $v[0]) . "</option>\n";
        }
        if ($group)
            $out .= "</optgroup>\n";
        $out .= "</select>\n";
        if ($this->error)
            $out .= $this->addErrorClass($this->getID($form));

        return $out;
    }
    public function setValue($value): bool
    {
        $this->value = $value;
        if ($value === null)
            $this->value = [];

        $this->error = false;
        if (!is_array($this->value) && $this->value)
            $this->value = explode(',', $this->value);

        if ($this->non_empty && !count($this->value))
            $this->error = true;
        return $this->error;
    }

    public function getValue(): string
    {
        return implode(',', $this->value);
    }


    public function getDisplayValue(): string
    {
        if (!is_array($this->value))
            return '';
        $this->loadValues();
        $value = [];
        foreach ($this->value as $v) {
            $index = $this->v_hash[$v] ?? -1;
            if ($index < 0) {
                $value[] = htmlspecialchars($v);
                continue;
            }
            $v = $this->values[$index] ?? $v;
            if (!is_array($v))
                $v = [$v];
            $value[] = htmlspecialchars($v[1] ?? $v[0]);
        }


        return htmlspecialchars(implode(', ', $value));
    }




}