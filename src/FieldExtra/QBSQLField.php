<?php

namespace BayCMS\FieldExtra;

use BayCMS\Field\TextInput;
use BayCMS\Field\Textarea;
class QBSQLField extends QBOrder
{
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        ?string $description = null,
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
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id = ''

    ) {
        \BayCMS\Field\Field::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);


        $this->fields = new \BayCMS\Fieldset\Fieldset();
        $this->fields->addField(new TextInput(
            $context,
            name: $this->name . '_name',
            description: $this->t('Field Name', 'Feld Name')
        ));
        $this->fields->addField(new Textarea(
            $context,
            name: $this->name . '_sql',
            description: 'SQL-Code'
        ));
    }

    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        $this->value = [];
        foreach ($this->fields->getFields() as $f) {
            $f->setValueFromArray($v, $id);
            $this->value[$f->getName()] = $f->getValue();
        }
        return $this->error;
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = '';
        $f = $this->fields->getField(0);
        $out .= '<div style="float:left;"><label for="' . $f->getID($form) . '"';
        $out .= ' style="width:auto;"';
        $out .= '>' . $f->getDescription() . '</label><br/>';
        $out .= $f->getInput($form);
        $out .= '</div><div>';
        $f = $this->fields->getField(1);
        $out .= $f->getInput($form);
        $out .= '</div>';
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