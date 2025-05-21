<?php

namespace BayCMS\Field;

class SelectJS extends Select
{

    protected string $chooser_options;
    protected string $target;
    protected bool $autocomplete;
    protected bool $button;
    protected string $json_param;
    protected int $json_length;

    protected string $js_callback;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $target,
        string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = '',
        string $post_input='',
        string $placeholder = '',
        bool $no_add_to_query = false,
        bool $not_in_table = false,
        bool $non_empty = false,
        array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        string $db_query='',
        string $chooser_options="toolbar=no,menubar=no,scrollbars=yes,width=700,height=500",
        bool $autocomplete=true,
        bool $button=true,
        string $json_param='',
        int $json_length=1,
        string $js_callback=''
    ) {
        \BayCMS\Field\TextInput::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id);
        $this->db_query=$db_query;
        $this->chooser_options=$chooser_options;
        $this->target=$target;
        $this->autocomplete=$autocomplete;
        $this->button=$button;
        $this->json_param=$json_param;
        $this->json_length=$json_length;
        $this->js_callback=$js_callback;

    }


    public function getInput($form): string
    {
        $out = "";
        $target = $this->target;
        if (!strstr($target, "?"))
            $target .= "?js_select=1";

        $name = $this->name;
        
        $out .= '<input id="' . $this->getID($form).'" type="hidden" name="' . $name . '" 
        value="' . htmlspecialchars($this->value) . '">';
        $out .= '<input id="' . $this->getID($form) . '_dp" name="' . $name . '_dp" ';
        $out .= 'value="' . htmlspecialchars($this->getDisplayValue()) . '" ';
        if ($this->placeholder)
            $out .= ' placeholder="' . htmlspecialchars($this->placeholder) . '"';
        if (!$this->autocomplete)
            $out .= " readonly";
        $out .= $this->input_options. '>';
        //  $out.= '<input type="button" id="'.$this->getID($form).'_button" value="...">';

        if ($this->button)
            $out .= "
        <input type=button onClick='i" . $name . "=document." . $form->getName() .
                "." . $name . ";i" . $name . "_dp=document." .
                $form->getName() . '.' . $name . "_dp; " . $name .
                "_chooser=window.open(\"" . $target . "&target=i" . $name . '",
          "' . $name . "_chooser\", \"" .
                $this->chooser_options . '"); ' . $name .
                "_chooser.i" . $name . "=i" . $name . "; " . $name . "_chooser.i" .
                $name . "_dp=i" . $name . "_dp; 
        window.i$name=i$name; window.i" . $name . "_dp=i" . $name . "_dp;' value=\"...\">";
        if ($this->autocomplete) {
            $out .= '<script>
            $("#' . $this->getID($form) . '_dp").autocomplete({
                source: function(request, response){
                    $.getJSON("' . $this->target . '", {
                        json_query: request.term' .
                $this->json_param . '
                    }, response )
                },
                select: function(event,ui){
                    $("#' . $this->getID($form) . '").val(ui.item.id)
                    '.$this->js_callback.'
                },
                change: function(event,ui){
                    if(! ui.item){
                        this.value=""
                        $("#' . $this->getID($form) . '").val("")
                    }
                },
                mustMatch: true,
                minLength: ' . $this->json_length . '
            })
            </script>';
        }
        if($this->error) $out.=$this->addErrorClass($this->getID($form).'_dp');
        return $out;

    }

}