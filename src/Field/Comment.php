<?php

namespace BayCMS\Field;


class Comment extends Field
{
    private bool $not_in_form;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        ?string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options='',
        string $post_input='',
        string $placeholder='',
        bool $no_add_to_query = true,
        bool $not_in_table = false,
        bool $non_empty=false,
        ?array $footnote = null,
        string $div_id='',
        bool $not_in_form = false
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder,$no_add_to_query, $not_in_table, $non_empty, $footnote,'',$div_id);
        $this->not_in_form=$not_in_form;
    }


    public function getDisplayValue(): string
    {
        return $this->description;
    }


    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        return '';
    }

    public function setValue($value): bool
    {
        return false;
    }

    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        if( $this->not_in_form) return '';
        $out = '<div class="' . $this->context->TE->getCSSClass('form_div') . '">'. 
            $this->getDescription() . "</div>\n";
        return $out;
    }


    public function getTableRow(): string
    {
        if( $this->not_in_table) return '';
        $out = "<tr><td colspan=2>" . $this->getDisplayValue() . "</td></tr>\n";
        return $out;
    }

}