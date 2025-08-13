<?php

namespace BayCMS\Field;

class Time extends TextInput{

    protected ?float $step;
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
        bool $no_add_to_query = false,
        bool $not_in_table = false,
        bool $non_empty=false,
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        ?float $step = null
    ) {
        $input_options.=' type="time"';
        if(! is_null($step)) $input_options.=' step="' . $step . '"';
        $this->step=$step;
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder,$no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
    }
    

    public function getValue(): mixed
    {
        if(! $this->value) return null;
        return $this->value;
    }
    
    public function getSQL($prefix = '', $target='html'): string
    {
        if($this->sql) return $this->sql;
        if($target=='html') return "to_char($prefix".$this->name.",'HH24:MI')";
        return $prefix.$this->name;
    }

    public function setValue($value): bool
    {
        if($value){
            if(is_null($this->step) || $this->step==60) $format='H:i';
            else $format='H:i:s';
            $dt= new \DateTime($value);
            $value=$dt->format($format);
        } else $value=null;
        return parent::setValue($value);
    }
}