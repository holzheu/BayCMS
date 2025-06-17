<?php

namespace BayCMS\Field;

class TextInput extends Field
{
    protected int $max_length;
    protected int $min_length;

    public function getValue(): mixed
    {
        if ($this->value !== null)
            return $this->value;
        return "";
    }

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
        string $div_id = '',
        int $max_length = 0,
        int $min_length = 0

    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
        $this->max_length = $max_length;
        $this->min_length = $min_length;
    }

    public function setValue($value): bool
    {
        parent::setValue($value);
        if($this->error) return (bool) $this->error;
        if ($this->max_length) {
            $count = mb_strlen(trim($value),'UTF-8');
            $this->error = $count > $this->max_length;
            if ($this->error){
                $this->inline_error = $this->t(
                    'To many characters. Only ' . $this->max_length . ' are allowed. Counting ' . $count . '.',
                    'Zu viele Zeichen. Erlaubt sind ' . $this->max_length . '. Zähle ' . $count . '.'
                );
                return (bool) $this->error;
            }
                
        }

        if ($this->min_length) {
            $count = mb_strlen(trim($value),'UTF-8');
            $this->error = $count < $this->min_length;
            if ($this->error)
                $this->inline_error = $this->t(
                    'Not enough characters. You have to enter at least ' . $this->min_length . '. Counting ' . $count . '.',
                    'Zu wenig Zeichen. Notwendig sind ' . $this->min_length . '. Zähle ' . $count . '.'
                );
        }

        return (bool) $this->error;
    }


    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "<input id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        if ($this->placeholder)
            $out .= 'placeholder="' . htmlspecialchars($this->placeholder) . '"';

        $out .= " value=\"" . htmlspecialchars($this->value) . "\"";
        $out .= $this->input_options . ">";
        if($this->error) $out.=$this->addErrorClass($this->getID($form));
        return $out;

    }



}