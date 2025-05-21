<?php

namespace BayCMS\Fieldset;

class TabFieldset extends Fieldset {
    protected array $div_names;
    protected array $divs = [];

    protected string $name;

    protected function getFieldsetTab()
    {
        $out = '';
        if (!count($this->divs))
            return $out;
        if ($this->context->TE->isBootstrap()) {
            $out .= '<ul class="nav nav-tabs">
          ';
            $active = true;
            foreach ($this->divs as $id => $name) {
                $out .= '<li class="' . ($active ? 'active' : '') . '">
            <a href="#" class="formclass_tablink" data-id="' . $id . '" id="' . $id . '_tab">' . $name . '</a></li>' . "\n";
                $active = false;
            }
            $out .= '</ul>';
            $out .= '<script>
        $(".formclass_tablink").on("click",function(){
            $(".formclass_tablink").parent().removeClass("active")
            $(this).parent().addClass("active")
            $(".formclass_fieldset").css("display","none")
            var id=$(this).attr("data-id")
            $("#"+id).css("display","block")
        })
        </script>';
        } else {
            $rows = [];
            $row_length = 0;
            $row_index = 0;
            $max_length = ($GLOBALS['TEMPLATE_REITER_MAX_LENGTH'] ?? 89);
            foreach ($this->divs as $id => $name) {
                $rows[$row_index][$id] = $name;
                $row_length += 4 + mb_strlen($name, 'UTF-8');
                if ($row_length > $max_length) {
                    $row_index++;
                    $row_length = 0;
                }
            }

            krsort($rows);
            $count = 0;
            $out = '<div id="navsite"><ul id="' . $this->name . '_tab">';
            $active = true;
            foreach ($rows as $k => $r) {
                foreach ($r as $id => $name) {
                    $out .= '<li class="' . $this->name . '_tabrow' . $k . '"><a href="#" class="formclass_tablink ' . (!$k && $active ? 'active' : '') . '" 
                    data-id="' . $id . '" id="' . $id . '_tab">' . $name . '</a></li>';
                    if (!$k && $active)
                        $active = false;
                }
                $out .= '<br class="' . $this->name . '_tabrow' . $k . '"/>';
            }
            $out .= '</ul></div>';
            $out .= '<script>
        $(".formclass_tablink").on("click",function(){
            $(".formclass_tablink").removeClass("active")
            $(this).addClass("active")
            $(".formclass_fieldset").css("display","none")
            var id=$(this).attr("data-id")
            $("#"+id).css("display","block")
            /* move rows... */
            var class_name=$(this).parent().attr("class")
            var ul=$(this).parent().parent()
            console.log(class_name)
            
            $("."+class_name).each(function(){
                $(this).appendTo("#' . $this->name . '_tab")
            })
        })
        </script>';
        }

        return $out;

    }

}