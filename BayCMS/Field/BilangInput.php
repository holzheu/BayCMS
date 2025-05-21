<?php

namespace BayCMS\Field;

class BilangInput extends Field
{
    protected \BayCMS\Fieldset\Fieldset $fields;
    protected static array $lang = ['de' => 'deutsch', 'en' => 'english'];

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $description = null,
        string $id = '',
        string $sql = '',
        array $help = ['de'=>'','en'=>''],
        string $label_css = '',
        array $input_options=['de'=>'','en'=>''],
        array $post_input=['de'=>'','en'=>''],
        array $placeholder=['de'=>'','en'=>''],
        bool $no_add_to_query = false,
        bool $not_in_table = false,
        bool $non_empty=false,
        array $footnote = null,
        array $default_value = ['de'=>'','en'=>''],
        string $div_id=''
    ) {
        parent::__construct(context: $context, name: $name, description: $description, id: $id, sql: $sql, no_add_to_query: $no_add_to_query, not_in_table: $not_in_table, non_empty: $non_empty, footnote: $footnote, div_id: $div_id);
        $this->fields = new \BayCMS\Fieldset\Fieldset();
        foreach (BilangInput::$lang as $key => $value) {
            $this->fields->addField(new \BayCMS\Field\TextInput(
                context: $context,
                name: $name . $key,
                description: $this->description . ' (' . $value . ')',
                help: $help[$key],
                label_css: $label_css,
                input_options: $input_options[$key],
                post_input: $post_input[$key],
                placeholder: $placeholder[$key],
                not_in_table: $this->not_in_table,
                non_empty: $this->non_empty,
                footnote: $this->footnote,
                default_value: $default_value[$key],
                div_id: $div_id
            ));
        }
    }


    public function getFields(): array
    {
        return $this->fields->getFields();
    }

    public function getValue(): array
    {
        $value = [];
        foreach (BilangInput::$lang as $key => $v) {
            $value[$this->name . $key] = $this->fields->getField($this->name . $key)->getValue();
        }
        return $value;
    }

    public function setValue($value): bool
    {
        return (bool) $this->error;
    }


    public function setValueFromArray(array &$v, int $id = null): bool
    {
        $this->error = false;
        $v2 = '';
        foreach ($this->fields->getFields() as $f) {
            $f->setValueFromArray($v, $id);
            $v2 .= $f->getValue();
        }
        if ($this->non_empty && !$v2)
            $this->error = true;
        foreach ($this->fields->getFields() as $f) {
            $f->setError($this->error);
        }
        return (bool) $this->error;

    }


    public function getSQL($prefix = 't.', $target='html'): string
    {
        $name = $this->name;
        $name = $prefix . $name;
        return "non_empty(" . $name . $this->context->lang . "," . $name . $this->context->lang2 . ")";
    }

    public function getDisplayValue(): string
    {
        $v = $this->fields->getField($this->name . $this->context->lang)->getDisplayValue();
        if (!$v)
            $v = $this->fields->getField($this->name . $this->context->lang2)->getDisplayValue();
        return $v;
    }


    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        return '';
    }

    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        $out = "";
        foreach ($this->fields->getFields() as $f) {
            $out .= $f->getFormRow($form);
        }
        return $out;
    }


}