<?php

namespace BayCMS\Field;

class File extends TextInput
{
    protected string $accept;
    protected int $max_size;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = '',
        string $post_input='',
        string $placeholder = '',
        bool $no_add_to_query = true,
        bool $not_in_table = false,
        bool $non_empty = false,
        array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        string $accept='',
        int $max_size=0
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id);
        $this->accept=$accept;
        $this->max_size=$max_size;
    }



    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "<input type=\"file\" id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        if ($this->placeholder)
            $out .= 'placeholder="' . htmlspecialchars($this->placeholder). '"';
        if ($this->accept)
            $out .= ' accept="' . $this->accept . '"';

        $out .= ' ' . $this->input_options . ">";
        if($this->max_size){
            $out.='<script> $("#'.$this->getID($form).'").bind("change",function(){
            if(this.files[0].size > '.$this->max_size.') {
       alert("'.$this->t('File is too big. Maximum accepted file size is',
       'Die Datei ist zu groß. Die maximal akzeptierte Dateigröße ist').' '.round($this->max_size/1024).' kb'.'");
       this.value = "";
        }
        });</script>';
        }
        if($this->error) $out.=$this->addErrorClass($this->getID($form));
        return $out;
    }


}

