<?php

namespace BayCMS\Field;

use function PHPUnit\Framework\returnSelf;

class Textarea extends TextInput
{

    protected int $max_display_length;
    protected bool $htmleditor;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
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
        int $max_display_length = 300,
        bool $htmleditor = false,
        int $max_length = 0,
        int $min_length = 0

    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id, $max_length, $min_length);
        $this->max_display_length = $max_display_length;
        $this->htmleditor = $htmleditor;
        $this->max_length = $max_length;
    }


    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = "<textarea id=\"" . $this->getID($form) . "\" name=\"" . $this->name . "\" ";
        if ($this->placeholder)
            $out .= 'placeholder="' . htmlspecialchars($this->placeholder) . '"';

        $out .= $this->input_options . ">";
        $out .= htmlspecialchars($this->value);
        $out .= '</textarea>' . "\n";
        if ($this->htmleditor)
            $out .= "<a href=\"#\" onclick='itext = document.forms[\"" . $form->getName() . "\"].elements[\"" . $this->name . "\"]; 
        editor = window.open(\"/" . $this->context->org_folder . '/' . $this->context->lang . "/intern/gru/editor.php?id_obj=" . $form->getId() . "\", 
        \"EDITOR\", \"toolbar=no,menubar=no,scrollbars=yes,width=700,height=500\"); 
        editor.itext = itext; window.itext = itext;'>...HTML-Editor</a>\n";
        if ($this->error)
            $out .= $this->addErrorClass($this->getID($form));

        return $out;
    }

    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        if (!$form->uname)
            return true;
        $matches = [];
        preg_match_all(
            "&src=\"[^\"]+(/|=)[ot]?([0-9]+)\\.(jpg|gif|png|svg)\"&",
            $this->value,
            $matches,
            PREG_PATTERN_ORDER
        );

        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_bild from objekt_bild where id_obj=$1 and name=$2',
            [$form->getId(), $this->name]
        );
        $del = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$id] = pg_fetch_row($res, $i);
            $index = array_search($id, $matches[2]);
            if ($index !== false) {
                unset($matches[2][$index]);
            } else {
                $del[] = $id;
            }
        }
        if (count($matches[2])) {
            pg_prepare(
                $this->context->getRwDbConn(),
                $this->name . 'insert_objekt_bild',
                'insert into objekt_bild(id_obj,id_bild,name) values($1,$2,$3)'
            );
            foreach ($matches[2] as $id) {
                pg_execute(
                    $this->context->getRwDbConn(),
                    $this->name . 'insert_objekt_bild',
                    [$form->getId(), $id, $this->name]
                );
            }
        }

        if (count($del)) {
            pg_prepare(
                $this->context->getRwDbConn(),
                $this->name . 'delete_objekt_bild',
                'delete from objekt_bild where id_obj=$1 and id_bild=$2 and name=$3'
            );
            foreach ($del as $id) {
                pg_execute(
                    $this->context->getRwDbConn(),
                    $this->name . 'delete_objekt_bild',
                    [$form->getId(), $id, $this->name]
                );
            }

        }
        pg_query_params(
            $this->context->getRwDbConn(),
            'delete from objekt_bild where id_obj=$1 and name is null',
            [$form->getId()]
        );

        return true;


    }
    public function setValue($value): bool
    {
        Field::setValue($value);        
        if($this->error) return (bool) $this->error;

        if (!$this->max_length) {
            $count = mb_strlen(trim(
                preg_replace(
                    '/&[a-z]+;/',
                    'x',
                    strip_tags($value)
                )
            ),'UTF-8');
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
            $count = mb_strlen(trim(
                preg_replace(
                    '/&[a-z]+;/',
                    'x',
                    strip_tags($value)
                )
            ),'UTF-8');
            $this->error = $count < $this->min_length;
            if ($this->error)
                $this->inline_error = $this->t(
                    'Not enough characters. You have to enter at least ' . $this->min_length . '. Counting ' . $count . '.',
                    'Zu wenig Zeichen. Notwendig sind ' . $this->min_length . '. Zähle ' . $count . '.'
                );
        }
        return (bool) $this->error;
    }

    public function getDisplayValue(): string
    {
        $v = $this->value;
        if (strlen($v) > $this->max_display_length) {
            $v = substr($v, 0, $this->max_display_length) . "...";
        }
        return htmlspecialchars($v);
    }

}