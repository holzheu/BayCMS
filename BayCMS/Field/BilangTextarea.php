<?php

namespace BayCMS\Field;

class BilangTextarea extends BilangInput
{
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
        string $div_id='',
        bool $htmleditor=false
    ) {
        \BayCMS\Field\Field::__construct(context: $context, name: $name, description: $description, id: $id, sql: $sql, no_add_to_query: $no_add_to_query, not_in_table: $not_in_table, non_empty: $non_empty, footnote: $footnote, div_id: $div_id);
        $this->fields = new \BayCMS\Fieldset\Fieldset();
        foreach (BilangInput::$lang as $key => $value) {
            $this->fields->addField(new \BayCMS\Field\Textarea(
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
                div_id: $div_id,
                htmleditor:$htmleditor
            ));
        }
    }

    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        foreach ($this->fields->getFields() as $f) {
            $f->save($form);
        }
        return true;
    }
}