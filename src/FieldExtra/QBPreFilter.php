<?php

namespace BayCMS\FieldExtra;

use BayCMS\Field\Select;
class QBPreFilter extends QBFilter
{
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        array $values,
        int $nr,
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
        mixed $default_value = null,
        string $div_id = ''

    ) {
        \BayCMS\Field\Field::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
        $this->values = $values;
        $this->nr = $nr;
        $this->fields = new \BayCMS\Fieldset\Fieldset();

        $this->fields->addField(new Select(
            $context,
            name: $this->name . '_con',
            values: $this->nr ? ['and', 'and not', 'or', 'or not'] : ['not'],
            null: 1
        ));
        $this->fields->addField(new Select(
            $context,
            name: $this->name . '_bo',
            values: ['('],
            null: 1
        ));
        $this->fields->addField(new Select(
            $context,
            name: $this->name . '_name',
            values: $this->values,
            null: 1
        ));
        $this->fields->addField(new Select(
            $context,
            name: $this->name . '_bc',
            values: [')'],
            null: 1
        ));
    }

}