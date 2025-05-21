<?php

namespace BayCMS\Field;

class NumSelect extends Select
{
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
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
        string $div_id = '',
        bool $null = false,
        int $min = 1,
        int $max = 10,
        int $step = 1
    ) {
        if (($max < $min && $step > 0) || ($max > $min && $step < 0) || $step == 0)
            throw new \BayCMS\Exception\invalidData("Invalid settings for min, max and step");
        $values = [];
        $v = $min;
        while ($v <= $max) {
            $values[] = $v;
            $v += $step;
        }
        parent::__construct(
            $context,
            $name,
            $description,
            $id,
            $sql,
            $help,
            $label_css,
            $input_options,
            $post_input,
            $placeholder,
            $no_add_to_query,
            $not_in_table,
            $non_empty,
            $footnote,
            $default_value,
            $div_id,
            $null,
            $values
        );
    }



}