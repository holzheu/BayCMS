<?php

namespace BayCMS\Util;

class Tree extends \BayCMS\Base\BayCMSBase
{
    private string $child_query;
    private string $parent_query;
    private array $path = [];
    private string $id_name;

    private ?int $root_id;
    private array $actions=[];

    private int $level = 0;


    /**
     * Create Tree instance
     * @param \BayCMS\Base\BayCMSContext $context
     * @param string $child_query must return id,type,description,icon?,folder?,write_access?,child_allowed? - input $1
     * @param string $parent_query must return id - input $1
     * @param array $actions array with actions e.g. ['edit','del']
     * @param int $root_id
     * @param string $id_name
     */
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $child_query,
        string $parent_query,
        array $actions=[],
        int $root_id = null,
        string $id_name = 'id'
    ) {
        $this->context = $context;
        $this->child_query = $child_query;
        $this->parent_query = $parent_query;
        $this->actions=$actions;
        $this->root_id = $root_id;
        $this->id_name = $id_name;
        $this->path();
    }

    private function path()
    {
        $this->path = [];
        $id = $_GET[$this->id_name] ?? null;
        
        pg_prepare(
            $this->context->getDbConn(),
            'get_parent',
            $this->parent_query
        );
        while ($id) {
            $res = pg_execute(
                $this->context->getDbConn(),
                'get_parent',
                [$id]
            );
            $this->path[] = $id;
            $r = pg_fetch_array($res, 0);
            $id = $r['id'];
            if ($id == $this->root_id)
                break;
        }
        if ($id == '')
            $id = null;
        if($id===null) $id=$this->root_id;
        $this->path[] = $id;
        $this->path = array_reverse($this->path);
    }

    private function folder($id, $folder = 'folder')
    {
        $ip = '/' . $this->context->getOrgLinkLang() . '/image/';
        if ($id == ($this->path[$this->level + 1] ?? 0))
            return '<a href="?' . $this->id_name . '=' . ($this->path[$this->level] ?? '') . '"><img src="' . $ip . 'tm.gif"><img src="' . $ip . $folder . '.open.gif"></a>';
        return '<a href="?' . $this->id_name . '=' . $id . '"><img src="' . $ip . 'tp.gif"><img src="' . $ip . $folder . '.gif"></a>';

    }

    private function actionLinks(bool $write,$id){
        if(! $write) return;
        $css=' style="min-width:20px;" ';
        $out='';
        foreach($this->actions as $action){
            switch($action){
                case 'del':
                    $out.=$this->context->TE->getActionLink('?aktion=del&'.$this->id_name.'='.$id,
                    '',$css.'title="'.$this->t('delete','löschen').'" onClick="return confirm(\''.$this->t('Are you sure?','Sind Sie sicher?').'\');"','del');
                    break;
                case 'edit':
                    $out.=$this->context->TE->getActionLink('?aktion=edit&'.$this->id_name.'='.$id,
                    '',$css.' title="'.$this->t('edit','bearbeiten').'"','edit');
                    break;

            }
        }
        return $out;
    }
    private function tree($prefix = '',$new_item_string='',$new_item=true)
    {
        $res = pg_execute($this->context->getDbConn(), 'get_childs', [$this->path[$this->level]]);
        $num = pg_num_rows($res);
        $out = '';
        for ($i = 0; $i < $num; $i++) {
            $last = ($i == ($num - 1));
            if($new_item_string && $new_item) $last=false;
            $r = pg_fetch_array($res, $i);
            $title='<span id="tree_item'.$r['id'].'" class="baycms_tree_item">'.$r['description'].
                '<span id="tree_item'.$r['id'].'_action" style="display:none;">&nbsp;'.$this->actionLinks(($r['write_access']??false)=='t',$r['id']).'</span></span>';
            if ($r['type'] == 'folder') {
                $out .= $prefix.$this->folder($r['id'],$r['folder']??'folder').'&nbsp;';
                $out .= $title."<br/>\n";
                if ($r['id'] == ($this->path[$this->level + 1] ?? 0)) {
                    $this->level++;
                    $out .= $this->tree($prefix . '<img src="/' . $this->context->getOrgLinkLang() . '/image/' . ($last ? 'blank' : 'l') . '.gif">',
                    $new_item_string,($r['child_allowed']??'')=='t');
                    $this->level--;
                }
            } else {
                $out.= $prefix. '<img src="/' . $this->context->getOrgLinkLang() . '/image/' . ($last ? 'L' : 't') . '.gif">'.$r['icon'].' '.$title."<br/>\n"; 
            }
        }
        if($new_item_string && $new_item){
            $out.=$prefix.'<img src="/' . $this->context->getOrgLinkLang() . '/image/L.gif">'.str_replace('$1',$this->path[$this->level],$new_item_string)."<br/>\n";
            $out.=$prefix."<br/>\n";
        }
        return $out;
    }

    public function getTree($new_item_string='',$new_item=true)
    {
        pg_prepare($this->context->getDbConn(), 'get_childs', $this->child_query);
        $out=$this->tree('',$new_item_string,$new_item);
        $out.='<script>
        $(".baycms_tree_item").on("click",function(){
            target="#"+$(this).attr("id")+"_action";
            $(target).toggle();
        });
        $(".baycms_tree_item").on("mouseover",function(){
            target="#"+$(this).attr("id")+"_action";
            $(target).css("display","inline");
        });
        $(".baycms_tree_item").on("mouseout",function(){
            target="#"+$(this).attr("id")+"_action";
            $(target).css("display","none");
        });
        </script>';
        return $out;

    }

}