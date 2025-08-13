<?php

namespace BayCMS\Field;


/**
 * Select
 * 
 * Settings:
 * values, db_query:
 * You have to give either "values" in the form
 * [ key1, key2, ..]
 * or
 * [ [key1, description1, group1 ],
 *   [key2, description2, group2],
 * ... ]
 * 
 * or db_query in the form
 * select xx as id, yy as description, zz as group from table where ...
 * 
 * null: adds null value to Select
 * 
 * 
 * 
 */
class Select extends TextInput
{
    protected ?array $values;
    protected string $db_query;
    protected array $v_hash=[];
    protected bool $null;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        ?string $description = null,
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
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        bool $null=false,
        ?array $values=null,
        string $db_query=''
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id);
        $this->null=$null;
        $this->db_query=$db_query;
        $this->values=$values;
    }

    protected function loadValues(){
        if (is_null($this->values)) {
            if (! $this->db_query)
                throw new \BayCMS\Exception\missingData("no values and no db_query");
            $res = pg_query($this->context->getDbConn(), $this->db_query);
            $this->values = [];
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_row($res, $i);
                $this->values[] = $r;
            }
        }

        for($i=0;$i<count($this->values);$i++){
            if(! is_array($this->values[$i])) $this->values[$i]=[$this->values[$i]];
            $this->v_hash[$this->values[$i][0]]=$i;
        }
        
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $this->loadValues();
        $out = "<select id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        $out .= $this->input_options . ">\n";
        if ($this->null)
            $out .= '<option value="null">&nbsp;</option>\n';
       
        $group = '';
        foreach ($this->values as $v) {
            if ($group != ($v[2] ?? '')) {
                if ($group)
                    $out .= "</optgroup>\n";
                $group = $v[2];
                $out .= '<optgroup label="' . $group . '">' . "\n";
            }
            $out .= '<option value="' . htmlspecialchars($v[0]) . '"';
            if ($this->value!==null && $this->value == $v[0])
                $out .= " selected";
            $out.='>'.($v[1]??$v[0])."</option>\n";
        }
        if ($group)
                    $out .= "</optgroup>\n";
        $out.="</select>\n";
        if($this->error) $out.=$this->addErrorClass($this->getID($form));
        return $out;
    }

    public function getValue(): mixed
    {
        if ($this->value !== null)
            return $this->value;
        return null;
    }

    public function setValue($value): bool
    {
        if($value==="null") $value=null;
        if($value==='') $value=null;
        $this->error = false;
        if($this->default_value && $value===null && $this->non_empty)
            $value=$this->default_value;
        if ($this->non_empty && $value===null)
            $this->error = true;
        $this->value = $value;
        return $this->error;
    }
    public function getDisplayValue(): string{
        if(is_null($this->value)) return '';
        if($this->db_query){
            $res=pg_query_params($this->context->getDbConn(),
            "select description from (".$this->db_query.") a where id=\$1",
            [$this->value]);
            $r=pg_fetch_row($res,0);
            return htmlspecialchars($r[0]);
        } else 
            $this->loadValues();
        $i=$this->v_hash[$this->value]??-1;
        if($i<0) return '';
        $v=$this->values[$i];
        return htmlspecialchars($v[1]??$v[0]);        
    }

}