<?php

namespace BayCMS\FieldExtra;

use BayCMS\Field\Select;
use BayCMS\Field\Checkbox;
class QBOrder extends \BayCMS\Field\Field
{
    protected \BayCMS\Fieldset\Fieldset $fields;
    protected array $values;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        array $values,
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
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
        $this->fields = new \BayCMS\Fieldset\Fieldset();
        $this->values = $values;
        $this->fields->addField(new Select(
            $context,
            name: $this->name . '_name',
            values: $this->values,
            null: 1
        ));
        $this->fields->addField(new Checkbox($context, name: $this->name . '_desc', description: $this->t('descending', 'absteigend')));
    }

    public function getFieldSet(): \BayCMS\Fieldset\Fieldset
    {
        return $this->fields;
    }

    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        $this->value = [];
        foreach ($this->fields->getFields() as $f) {
            $f->setValueFromArray($v, $id);
            $this->value[$f->getName()] = $f->getValue();
        }
        return (bool) $this->error;
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = '';
        foreach ($this->fields->getFields() as $f) {
            $out .= $f->getInput($form);
        }
        $out .= ' <label for="' . $f->getID($form) . '"';
        $out .= ' style="width:auto;"';
        $out .= '>' . $f->getDescription() . '</label>';
        return $out;
    }

    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        $out = '<div class="' . $this->context->TE->getCSSClass('form_div') . '">';
        $out .= $this->getInput($form);
        if ($this->inline_error)
            $out .= "\n<br/><span class=\"form_inlineerror\">" . $this->inline_error . "</span>";
        $out .= "</div>\n";
        return $out;
    }
}