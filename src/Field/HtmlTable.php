<?php

namespace BayCMS\Field;

class HtmlTable extends Field
{

    protected int $sum_col;
    protected int $min_rows;
    protected int $max_rows;
    protected int $read_only_rows;
    protected array $col_input_options;
    protected array $cols;
    protected bool $readonly;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        array $cols,
        ?string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = ' rows="5" cols="35" wrap="virtual"',
        string $post_input = '',
        string $placeholder = '',
        bool $no_add_to_query = false,
        bool $not_in_table = false,
        bool $non_empty = false,
        ?array $footnote = null,
        mixed $default_value = null,
        string $div_id = '',
        array $col_input_options = [],
        int $sum_col = -1,
        int $min_rows = 3,
        int $max_rows = 100,
        int $read_only_rows = 0,
        bool $readonly = false


    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id);
        $this->cols = $cols;
        $this->col_input_options = $col_input_options;
        $this->sum_col = $sum_col;
        $this->min_rows = $min_rows;
        $this->max_rows = $max_rows;
        $this->read_only_rows = $read_only_rows;
        $this->readonly = $readonly;
    }
    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $rows = explode("\n", $this->value);
        array_pop($rows);
        $r_count = min($this->max_rows, max($this->min_rows, count($rows)));
        $out = '<input type="hidden" name="' . $this->name . '_r_count" value="' . $r_count . '">
				<table><tr>';
        for ($i = 0; $i < count($this->cols); $i++) {
            $out .= '<th>' . htmlspecialchars($this->cols[$i]) . '</th>';
        }
        $out .= '</tr>' . "\n";
        $ro_sting = ($this->readonly ? ' readonly' : '');
        $sum_col = $this->sum_col;

        $sum = 0;
        for ($j = 1; $j <= $r_count; $j++) {
            $cols = explode("<td>", $rows[$j]??'');
            $out .= "<tr>";
            for ($i = 0; $i < count($this->cols); $i++) {
                $ro_input = ($i == 0 && $j <= $this->read_only_rows);
                $out .= '<td><input name="' . $this->name . 'c' . ($i) . 'r' . ($j) . '" value="' .
                    htmlspecialchars(strip_tags($cols[$i + 1]??'')) . '"' .
                    ($i == 0 && $j <= $this->read_only_rows ? ' readonly' : $ro_sting) .
                    ($this->col_input_options[$i] ?? '') . ' ' .
                    ($ro_input ? ' type="hidden">' . htmlspecialchars(strip_tags($cols[$i + 1])) : '>') . '</td>';
            }
            if ($sum_col > -1 && ($cols[$sum_col + 1]??0))
                $sum += floatval(str_replace(",", ".", $cols[$sum_col + 1]));

            $out .= "</tr>\n";
        }
        if ($sum_col > -1) {
            $out .= '<tr><td colspan=' . $sum_col . '><i>' .
                $this->t('Total', 'Summe') . '</i></td><td><span id="' . $this->getID($form) . '_sum">' . $sum . '</span></td></tr>';
        }
        $out .= '</table>';
        if ($sum_col > -1) {
            $out .= "<script>
$(document).ready(function(){           
    $(\"input[name*='" . $this->name . 'c' . $sum_col . "']\").keyup(function(){   
        " . $this->getID($form) . "calculateTotal(this);
    });
});

function " . $this->getID($form) . "calculateTotal( src ) {
    var sum = 0;
    $(\"input[name*='" . $this->name . 'c' . $sum_col . "']\").each(function( index, elem ) {
        var val = parseFloat($(elem).val().replace(',','.'));
        if( !isNaN( val ) ) {
            sum += val;
        }
    });
    sum = Math.round(100 * sum)/100;
    $('#" . $this->getID($form) . "_sum').text(sum);
}					
					</script>";
        }

        if ($this->error)
            $out .= $this->addErrorClass($this->getID($form));
        return $out;

    }

    private function post2value(){
        $out='<table><tr>';
		$n=$this->name;
		$sum_col=$this->sum_col;
		$sum=0;
		for($i=0;$i<count($this->cols);$i++){
			$out.='<th>'.htmlspecialchars($this->cols[$i]).'</th>';
		}
		$out.='</tr>'."\n";
		for($j=1;$j<=$_POST[$n.'_r_count'];$j++){
			$cols=array();
			$found=0;
			for($i=0;$i<count($this->cols);$i++){
				if($_POST[$n.'c'.($i).'r'.($j)]) $found=1;
				if($i==$sum_col && $_POST[$n.'c'.($i).'r'.($j)]) $sum+=str_replace(",",".",$_POST[$n.'c'.($i).'r'.($j)]);
				$cols[]=htmlspecialchars(str_replace("\n", "", $_POST[$n.'c'.($i).'r'.($j)]));
			}
			if($found) $out.="<tr><td>".implode('</td><td>', $cols)."</td></tr>\n";
		}
		if($sum_col>-1) $out.='<tr><td colspan='.$sum_col.'><i>'.
		$this->t('Total','Summe').'</i></td><td>'.$sum.'</td></tr>';
        if($sum_col>-1 && $this->non_empty && ! $sum) $this->error=true;
		$out.='</table>';
        return $out;

    }
    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        $this->error=false;
        if($v[$this->name.'_r_count']??false)            
            $this->value=$this->post2value();
        else 
            $this->value=$v[$this->name]??'';
        return $this->error;

    }


    public function getDisplayValue(): string
    {
        return $this->value;
    }

}