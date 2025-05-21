<?php

namespace BayCMS\Field;

use function PHPUnit\Framework\returnSelf;

abstract class Field extends \BayCMS\Base\BayCMSBase
{

    /**
     * Settings array
     * @var array
     */
    protected bool $error = false;
    protected string $inline_error = '';
    protected string $name;
    protected mixed $value = null;
    protected mixed $default_value = null;
    protected string $description;
    protected string $id;
    protected string $sql;
    protected string $help;
    protected string $label_css;
    protected string $input_options;
    protected string $post_input;
    protected string $placeholder;
    protected bool $no_add_to_query;
    protected bool $not_in_table;
    protected bool $non_empty;
    protected ?array $footnote;
    protected string $div_id;


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
        if (is_null($description))
            $description = $name;
        $this->context = $context;
        $this->description = $description;
        $this->name = strtolower($name);
        $this->id = $id;
        $this->sql = $sql;
        $this->help = $help;
        $this->label_css = $label_css;
        $this->input_options = $input_options;
        $this->post_input = $post_input;
        $this->placeholder = $placeholder;
        $this->no_add_to_query = $no_add_to_query;
        $this->not_in_table = $not_in_table;
        $this->non_empty = $non_empty;
        $this->footnote = $footnote;
        $this->div_id = $div_id;
        if (!is_null($default_value))
            $this->value = $default_value;
        $this->default_value = $default_value;
    }

    public function __set(string $name, mixed $value)
    {
        $this->$name = $value;
    }
    public function __get(string $name): mixed
    {
        return $this->$name;
    }
    /**
     * Returns the value of the field.
     * Some fields may return an array
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getDivID(): string
    {
        return $this->div_id;
    }

    public function getNoAddToQuery(): bool
    {
        return $this->no_add_to_query;
    }

    public function setDivID(string $div_id): void
    {
        $this->div_id = $div_id;
    }


    /**
     * Returns the value of the field to display in the table
     * @return string
     */
    public function getDisplayValue(): string
    {
        return htmlspecialchars($this->value);
    }

    /**
     * Returns the name of the field
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    public function getLabelCSS(): string
    {
        return $this->label_css;
    }
    public function getPostInput(): string
    {
        return $this->post_input;
    }

    /**
     * Returns the id of the field. 
     * Per default this is FormName_FieldName
     * @param \BayCMS\Fieldset\Form $form
     * @return string
     */
    public function getID(\BayCMS\Fieldset\Form $form): string
    {
        if ($this->id)
            return $this->id;
        return $form->getName() . '_' . $this->name;
    }

    public function setID($id): void
    {
        $this->id = $id;
    }

    /**
     * Returns a SQL string used in table
     * @param mixed $prefix
     * @return mixed
     */
    public function getSQL($prefix = '', $target = 'html'): string
    {
        if ($this->sql)
            return $this->sql;
        return $prefix . '' . $this->name;
    }

    public function setSQL(string $sql)
    {
        $this->sql = $sql;
    }
    /**
     * Returns HTML-Code for the help
     * @param mixed $form
     * @return string
     */
    public function getHelp($form)
    {
        if (!$this->help)
            return '';

        $description = htmlspecialchars($this->getDescription(false) ?? '');
        $help_text = preg_replace('/\s\s+/', ' ', str_replace('"', '&quot;', $this->help));
        $help_id = 'help_' . $this->getID($form);
        $help =
            '<a id="' . $help_id . '"  data-trigger="click hover" data-html="true" data-toggle="popover" title="' . $description . '"
                data-content="' . $help_text . '"> <span class="glyphicon glyphicon-info-sign"></span></a>';
        return $help;
    }

    /**
     * Set the value of a field
     * @param mixed $value
     * @return bool
     */
    public function setValue($value): bool
    {
        if ($value === null)
            $value = '';
        $this->value = trim($value);
        $this->error = false;
        if($this->default_value && ! $this->value && $this->non_empty)
            $this->value=$this->default_value;
        if ($this->non_empty && !$this->value)
            $this->error = true;
        return (bool) $this->error;
    }

    public function disable()
    {
        if (!strstr($this->input_options, 'disabled'))
            $this->input_options .= " disabled";

    }

    /**
     * Set the error
     * @param bool $error
     * @return void
     */
    public function setError(bool $error)
    {
        $this->error = $error;
    }

    public function addErrorClass(string $id): string
    {
        return '<script>
        $("#' . $id . '").addClass("error")
        </script>';
    }
    /**
     * Typically called with $_POST or database array
     * in the form-object
     * @param array $v
     * @param int $id
     * @return bool
     */
    public function setValueFromArray(array &$v, int $id = null): bool
    {
        if (!isset($v[$this->name]) && $this->get('setvalue_sql') && $id) {
            $res = pg_query_params($this->context->getDbConn(), $this->get('setvalue_sql'), [$id]);
            if (pg_num_rows($res))
                [$v[$this->name]] = pg_fetch_row($res, 0);
        }
        return $this->setValue($v[$this->name] ?? null);
    }

    /**
     * Returns the description of a field
     * (with or without footnote)
     * @param bool $with_footnote
     * @return string
     */
    public function getDescription(bool $with_footnote = true)
    {
        $out = $this->description;
        if ($with_footnote) {
            if ($this->footnote ?? false)
                $out .= " " . $this->footnote[0];
            else if ($this->non_empty ?? false)
                $out .= " *";

            if ($this->error)
                $out = '<span style="color:#ff0000">' . $out . "</span>";

        }
        return $out;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Creates a HTML table row
     * @return string
     */
    public function getTableRow()
    {
        if ($this->not_in_table ?? false)
            return '';
        $out = "<tr><td>" . $this->description . "</td><td>"
            . $this->getDisplayValue() . "</td></tr>\n";
        return $out;
    }

    /**
     * Creates the input 
     * @param \BayCMS\Fieldset\Form $form
     * @return string
     */
    abstract public function getInput(\BayCMS\Fieldset\Form $form): string;

    /**
     * Create the form row
     * @param \BayCMS\Fieldset\Form $form
     * @return string
     */
    public function getFormRow(\BayCMS\Fieldset\Form $form): string
    {
        $out = '<div class="' . $this->context->TE->getCSSClass('form_div') . '">';
        $out .= '<label for="' . $this->getID($form) . '"';
        if ($this->label_css ?? '')
            $out .= ' style="' . $this->label_css . '"';
        $out .= '>' . $this->getDescription() . $this->getHelp($form) . '</label>';
        $out .= $this->getInput($form);
        if ($this->inline_error)
            $out .= "\n<br/><span class=\"form_inlineerror\">" . $this->inline_error . "</span>";
        $out .= "</div>\n";
        return $out;
    }

    /**
     * Returns a array of footnotes in the form
     * ['**','Footnote']
     * @return mixed
     */
    public function getFootnote()
    {
        if (isset($this->footnote))
            return $this->footnote;
        if (isset($this->non_empty))
            return ['*', $this->t('mandatory', 'Pflichtfeld')];
        return false;
    }

    /**
     * Callback function to save the field data
     * to the database
     * @param \BayCMS\Fieldset\Form $form
     * @return bool
     */
    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        return true;
    }


}