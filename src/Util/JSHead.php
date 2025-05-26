<?php

namespace BayCMS\Util;

class JSHead
{
    /**
     * Creates a JS-add(id,name) function
     * @param mixed $tinyurl 
     * @param mixed $target defaults to $_GET['target']
     * @param mixed $target_dp defaults to $_GET['target'].'_dp'
     * @return string
     */
    public static function get($tinyurl = '', $target = '', $target_dp = '')
    {

        $out = '';
        $out .= "<script>\n";
        switch ($_GET['js_select']) {
            case 'tiny':
                $out .= "function add(id,name){
                       top.tinymce.activeEditor.windowManager.getParams().oninsert('" . $tinyurl . "'+id,{'text':name});
                       top.tinymce.activeEditor.windowManager.close();
                       return false;
                    }";
                break;
            case "n":
                $out .= "function add(id, name){
                    top.sel[top.sel.length] = id;
                    top.selr[top.selr.length] = name;
                    top.frames[1].location = top.frames[1].location;
                    top.frames[1].location.reload();
                    return false;
            }";
                break;
            case "1":
                if (!$target)
                    $target = $_GET['target'];
                if (!$target_dp)
                    $target_dp = $target . "_dp";
                $out .= "function add(id, name){
                    opener.$target.value= id;
                    opener.$target_dp.value= name;
                    window.close();
                    return false;
            }";

                break;
        }
        $out .= "\n</script>\n";

        return $out;
    }
}