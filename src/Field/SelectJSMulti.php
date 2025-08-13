<?php

namespace BayCMS\Field;

class SelectJSMulti extends Select
{
 
    protected string $target;
    protected string $frame;
    protected string $chooser_options;
    protected bool $sorted;
    protected int $order_num;
    protected int $limit;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $db_query,
        string $target,
        ?string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = ' rows="5" cols="35" wrap="virtual"',
        string $post_input='',
        string $placeholder = '',
        bool $no_add_to_query = true,
        bool $not_in_table = false,
        bool $non_empty = false,
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        ?string $frame=null,
        string $chooser_options="toolbar=no,menubar=no,scrollbars=yes,width=700,height=500",
        bool $sorted=false,
        int $order_num=1,
        int $limit=999

    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
        $this->db_query=$db_query;
        $this->target=$target;
        if(is_null($frame)) $frame='/'.$context->getOrgLinkLang()."/intern/gru/js_frame.php";
        $this->frame=$frame;
        $this->chooser_options=$chooser_options;
        $this->sorted=$sorted;
        $this->order_num=$order_num;
        $this->limit=$limit;
    }



    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "";
        $target = $this->target;
        $frame = $this->frame;
        if (!strstr($frame, '?'))
            $frame .= "?js_phpfile=$target";
        if ($this->sorted)
            $frame .= "&sorted=1";
        $name = $this->name;
        $id=$this->getID($form);
        $out .= '<input type="hidden" name="' . $name . '" id="' . $id . '"
        value="' . htmlspecialchars($this->value) . '">';
        $out .= '
        <textarea id="' . $id . '_dp" name="' . $name . '_dp" readonly' .
            $this->input_options. '>';
        $out .= str_replace(", ", "\n", $this->getDisplayValue());
        $out .= "</textarea>\n";
        $out .= "
        <input type=button onClick='i" . $name . "=document.getElementById(\"$id\");i" . $name . "_dp=document.getElementById(\"$id"."_dp\");" . $name .
            "_chooser=window.open(\"" . $frame . "&target=i" . $name . '",
          "' . $name . "_chooser\", \"" .
            $this->chooser_options . '"); ' . $name .
            "_chooser.i" . $name . "=i" . $name . "; " . $name . "_chooser.i" .
            $name . "_dp=i" . $name . "_dp; 
        window.i$name=i$name; window.i" . $name . "_dp=i" . $name . "_dp' value=\"...\">";
        if($this->error) $out.=$this->addErrorClass($id.'_dp');

        return $out;

    }

    public function getDisplayValue(): string
    {
        if (!$this->value)
            return '';

        $in = [];
        $val = preg_split("/, ?/", $this->value);

        for ($i = 1; $i <= count($val); $i++) {
            $in[] = '$' . $i;
        }
        $in = implode(',', $in);
        $res = pg_query_params(
            $this->context->getDbConn(),
            str_replace('${in}', $in, $this->db_query),
            $val
        );

        $out = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);
            $out[$r[0]] = htmlspecialchars($r[1]);
        }
        $out2 = [];
        foreach ($val as $v) {
            if (!isset($out[$v]))
                continue;
            $out2[] = $out[$v];
        }
        return implode(", ", $out2);
    }


    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        if (isset($v[$this->name]))
            return parent::setValue($v[$this->name]);
        if (!$id)
            return false;

        $subquery = 'select id_auf from verweis where id_von=$1 and ' .
            ($this->sorted ? 'ordnung>=$2 and ordnung<=($2+' . $this->limit . ')' : 'ordnung = $2');
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            str_replace('${in}', $subquery, $this->db_query),
            [$id, $this->order_num]
        );
        $v2 = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);
            $v2[] = $r[0];
        }
        $this->value = implode(',', $v2);
        return false;
    }

    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        if (!$form->getId())
            return false;
        $order_num = $this->order_num;
    
        $conn = $this->context->getRwDbConn();

        pg_prepare($conn, $this->name . 'insert', 'insert into verweis(id_von, id_auf, ordnung) values ($1, $2, $3)');
        pg_prepare($conn, $this->name . 'delete', 'delete from verweis where id_von=$1 and id_auf=$2 and ordnung=$3');

        $query = 'select id_auf from verweis where id_von=$1 and ' .
            ($this->sorted ? 'ordnung>=$2 and ordnung<=($2+' . $this->limit . ')' : 'ordnung = $2') ;
        $query= str_replace('${in}', $query, $this->db_query);
        $query = 'select id_auf,ordnung from verweis where id_von=$1 and id_auf in (select id from ('.$query.') a ) and ' .
            ($this->sorted ? 'ordnung>=$2 and ordnung<=($2+' . $this->limit . ')' : 'ordnung = $2') . ' order by 2,1';
        
        $res = pg_query_params(
            $conn,
            $query,
            [$form->getId(), $order_num]
        );



        $old = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            $old[$r['id_auf']] = $r['ordnung'];
        }

        $new = [];
        if ($this->value && $ids = preg_split("/, ?/", $this->value)) {
            foreach ($ids as $id) {
                $new[$id] = $order_num;
                if ($this->sorted)
                    $order_num++;
            }
        }

        foreach ($new as $id => $order_num) {
            if (isset($old[$id]) && $old[$id] == $order_num) {
                unset($old[$id]);
                continue;
            }
            pg_execute($conn, $this->name . 'insert', [$form->getId(), $id, $order_num]);
        }

        foreach ($old as $id => $order_num) {
            pg_execute($conn, $this->name . 'delete', [$form->getId(), $id, $order_num]);
        }
        return true;
    }

}