<?php

namespace BayCMS\Page\Admin;

class NavIndex extends \BayCMS\Page\Page
{

    private int $id_kat;
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        $this->context = $context;
        $id = $_GET['id'] ?? ($_GET['id_parent'] ?? 0);
        $this->setIdKat($id);

    }

    private function getHTMLFileId()
    {
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select k.link,f.id from kategorie k left outer join file f on f.name=k.link||\'/gru/html.php\'where k.id=$1',
            [$this->id_kat]
        );
        if (!pg_num_rows($res))
            $this->error(500, 'Kat-ID ' . $this->id_kat . ' not found');
        [$kat, $id] = pg_fetch_row($res, 0);
        if ($id)
            return $id;

        $this->context->setSystemUser();
        $file = new \BayCMS\Base\BayCMSFile($this->context);
        $file->set([
            'source' => $this->context->BayCMSRoot . '/admin/gru/html.php',
            'name' => 'html.php',
            'path' => $kat . '/gru/',
            'de' => 'HTML-Seite',
            'en' => 'HTML-File'
        ]);
        $id = $file->save();
        $this->context->setSystemUser(false);
        return $id;
    }
    private function setIdKat($id)
    {
        $this->id_kat = 0;
        if (!$id)
            return;
        $res = pg_query_params($this->context->getDbConn(), 'select id from kategorie where id=$1', [$id]);
        if (pg_num_rows($res)) {
            $this->id_kat = $id;
            return;
        }

        while (!$this->id_kat && $id) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select i.id_super,k.id from index_files i left outer join kategorie k on k.id=i.id_super where i.id=$1',
                [$id]
            );
            if (pg_num_rows($res)) {
                [$id, $id_kat] = pg_fetch_row($res, 0);
                if($id_kat) $this->id_kat=$id_kat;
            } else
                $id = 0;
        }


    }
    public function indexForm()
    {
        $id = $_GET['id'] ?? 0;
        if ($id > 10000) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select i.*,h.id as html from index_files i left outer 
                join html_seiten h on i.id_obj=h.id where i.id=$1',
                [$id]
            );
            if (pg_num_rows($res)) {
                $r = pg_fetch_array($res, 0);
                if (!($_REQUEST['type'] ?? false)) {
                    if ($r['html'])
                        $_REQUEST['type'] = 'html';
                    else if ($r['id_file'])
                        $_REQUEST['type'] = 'file';
                    else if ($r['url_de'] || $r['url_en'])
                        $_REQUEST['type'] = 'url';
                    else
                        $_REQUEST['type'] = 'text';
                }

            }
        }
        $type = $_REQUEST['type'] ?? '';
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'index_files',
            submit: ($type ? $this->t('save', 'speichern') : $this->t('next', 'weiter')),
            qs: 'id_parent=' . ($_GET['id_parent'] ?? 0) . '&type=' . $type
        );
        if (!$type) {
            $v = ['html', 'file', 'url', 'text', 'expert'];
            $d = $this->context->lang == 'de' ?
                ['HTML Seite', 'Datei', 'URL', 'Text', 'Experten-Dialog'] :
                ['HTML Page', 'File', 'URL', 'Text', 'Expert-Dialog'];
            $values = [];
            for ($i = 0; $i < count($v); $i++) {
                $values[] = [$v[$i], $d[$i]];
            }
            $form->addField(new \BayCMS\Field\Select(
                $this->context,
                'type',
                $this->t('Type', 'Art'),
                values: $values
            ));
            return $form;
        }



        $form->addField(new \BayCMS\Field\Hidden(
            $this->context,
            'id_lehr',
            default_value: $this->context->getOrgId(),
            type: 'integer'
        ));
        $form->addField(new \BayCMS\Field\Hidden(
            $this->context,
            'id_super',
            default_value: $_GET['id_parent'] ?? 0,
            type: 'integer'
        ));
        $form->addField(new \BayCMS\Field\Number(
            $this->context,
            'ordnung',
            $this->t('Priority', 'Gewichtung'),
            default_value: 0,
            non_empty: 1
        ));

        if ($type == 'html') {
            $form->addField(
                new \BayCMS\Field\SelectJS(
                    $this->context,
                    'id_obj',
                    $_SERVER['SCRIPT_NAME'].'/html',
                    $this->t('HTML-Page', 'HTML-Seite'),
                    non_empty: 1,
                    db_query: 'select id,non_empty(' . $this->context->getLangLang2('') . ') as description from html_seiten',
                    post_input:'<script>
        function set_de_en(){
            $.ajax({url:"?q="+$("#form1_id_obj").val(),dataType: "json",
            success: function(result){
                $("#form1_de").val(result.de);
                $("#form1_en").val(result.en);
                }});
        }
        </script>',
        js_callback:'set_de_en()'
                )
            );
            $form->addField(new \BayCMS\Field\Hidden(
                $this->context,
                'id_file',
                default_value: $this->getHTMLFileId(),
                type: 'integer'
            ));
            $form->addField(new \BayCMS\Field\Hidden(
                $this->context,
                'qs'
            ));
        }
        if ($type == 'file') {
            $form->addField(
                new \BayCMS\Field\SelectJS(
                    $this->context,
                    'id_file',
                    $_SERVER['SCRIPT_NAME'].'/file?js_select=1&id_kat='.$this->id_kat,
                    $this->t('File', 'Datei'),
                    non_empty: 1,
                    db_query: 'select id,non_empty(' . $this->context->getLangLang2('') . ') as description from file',
                    post_input:'<script>
        function set_de_en(){
            $.ajax({url:"?q="+$("#form1_id_file").val(),dataType: "json",
            success: function(result){
                $("#form1_de").val(result.de);
                $("#form1_en").val(result.en);
                }});
        }
        </script>',
        js_callback:'set_de_en()'
                )
            );
        }
        if ($type == 'expert') {
            $form->addField(new \BayCMS\Field\Number(
                $this->context,
                'id_file',
                $this->t('File-ID', 'Datei-ID')
            ));
            $form->addField(new \BayCMS\Field\Number(
                $this->context,
                'id_obj',
                $this->t('Object-ID', 'Objekt-ID')
            ));
        }

        if ($type == 'html' || $type == 'file' || $type == 'url')
            $form->addField(
                new \BayCMS\Field\BilangInput(
                    $this->context,
                    '',
                    $this->t('Link Name', 'Link Name'),
                    non_empty: 1
                )
            );
        if ($type == 'text' || $type == 'expert') {
            $form->addField(
                new \BayCMS\Field\BilangTextarea(
                    $this->context,
                    '',
                    $this->t('Text', 'Text'),
                    non_empty: $type == 'text'
                )
            );
        }


        if ($type == 'url' || $type == 'expert')
            $form->addField(
                new \BayCMS\Field\BilangInput(
                    $this->context,
                    'url_',
                    $this->t('Link Target', 'Link Ziel'),
                    non_empty: $type == 'url'
                )
            );

        if ($type == 'expert') {
            $form->addField(
                new \BayCMS\Field\TextInput(
                    $this->context,
                    'qs',
                    'Query-String'
                )
            );
            $form->addField(
                new \BayCMS\Field\BilangTextarea(
                    $this->context,
                    'auto_query_',
                    'Auto-Query',
                    'Text'
                )
            );
        }


        if ($type != 'text')
            $form->addField(
                new \BayCMS\Field\Checkbox(
                    $this->context,
                    'target_blank',
                    $this->t('Open link in new window', 'Link in neuem Fenster öffnen')
                )
            );
        if (($_GET['id'] ?? 0)){
            $res=pg_query_params($this->context->getDbConn(),
            'select i.*, h.id as html_id from index_files i left outer join html_seiten h
            on h.id=i.id_obj where i.id=$1',
            [$_GET['id']]);
            $edit='';
            if(pg_num_rows($res)){
                $r=pg_fetch_array($res,0);
                if($r['html_id'])
                    $edit=$this->context->TE->getActionLink($_SERVER['SCRIPT_NAME'].'/html?id='.$r['html_id'],
                $this->t('Edit HTML-Page','HTML-Seite bearbeiten'),
                '',
                'edit');
            }
            $form->addField(
                new \BayCMS\Field\Comment(
                    $this->context,
                    'exp',
                    $this->context->TE->getActionLink(
                        '?aktion=edit&type=' . ($type != 'expert' ? 'expert' : '') . '&id=' . $_GET['id'],
                        $type != 'expert' ?
                        $this->t('Open in Expert-Dialog', 'Im Experten-Dialog öffnen') :
                        $this->t('Open in Standard-Dialog', 'Im Normalen-Dialog öffnen'),
                        '',
                        'edit'
                    ).$edit
                )
            );
        }
            

        if ($id)
            $form->load($id);
        return $form;
    }

    private function katForm()
    {
        $id = $_GET['id'] ?? 0;
        if (!$id)
            $id = 0;
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            action: '?aktion=ksave&id=' . $id,
            table: 'kat_aliases',
            submit: $id ? $this->t('save', 'speichern') : $this->t('show', 'anzeigen')
        );
        $lang = $this->context->lang;
        $lang2 = $this->context->lang2;
        $id_org = $this->context->getOrgId();
        if (!$id) {
            $form->addField(new \BayCMS\Field\Select(
                $this->context,
                'id',
                $this->t('Categorie', 'Kategorie'),
                db_query: "select k.id,non_empty(non_empty(a.$lang,k.$lang),non_empty(a.$lang2,k.$lang2)) as description
            from kategorie k left outer join kat_aliases a on k.id=a.id_kat and k.id_lehr=$id_org
            where k.id not in (select id_super from index_files where id_lehr=$id_org) and k.id>100"
            ));
            return $form;
        }

        $form->addField(new \BayCMS\Field\BilangInput(
            $this->context,
            '',
            'Name',
            non_empty: 1
        ));
        $values = [];
        if ($id) {
            $res = pg_query(
                $this->context->getDbConn(),
                "select case when a.ordnung is null then k.ordnung else
                    a.ordnung end as ordnung from kategorie k left outer join
                    (select * from kat_aliases where id_lehr=$id_org) a on k.id=a.id_kat
                    where k.id=" . $id
            );
            if (pg_num_rows($res)) {
                [$v] = pg_fetch_row($res, 0);
                $values[] = [$v, $this->t('do not move', 'nicht verschieben')];
            }
        }
        $prev = 0;
        $res = pg_query(
            $this->context->getDbConn(),
            "select * from (select distinct on(k_ord) * from
                (select case when a.min_power>k.min_power then a.min_power else k.min_power end as min_power,
                case when a.ordnung is null then k.ordnung else a.ordnung end as k_ord, k.id,
                non_empty(non_empty(a.$lang,a.$lang2),
                k.$lang) as text from index_files i,
                kategorie k left outer join (select * from kat_aliases where id_lehr=$id_org) a on
                k.id=a.id_kat where i.id_lehr=$id_org and i.id_super=k.id and k.id!=1000) a ) 
                a order by k_ord,text"
        );
        $min_diff = 1000;
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            $value = round(($r['k_ord'] - $prev) / 2 + $prev);
            $values[] = [$value, $this->t('above', 'über') . ' ' . $r['text']];
            if ($i > 0)
                $min_diff = min(array(($r['k_ord'] - $prev), $min_diff));
            $prev = $r['k_ord'];
        }
        $value = $prev + 200;
        $values[] = [$value, $this->t('put to the end', 'ans Ende stellen')];

        if ($min_diff < 4)
            $form->addField(new \BayCMS\Field\Hidden(
                $this->context,
                'fix',
                no_add_to_query: 1,
                default_value: $min_diff
            ));
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'ordnung',
            $this->t('Position', 'Position'),
            values: $values
        ));
        if ($id && $id < 10000)
            $db_query = "select 0 as id,'extern' as description from kategorie where id=$id and min_power=0
	union select p.power,non_empty(p.$lang,p.$lang2) from power p, kategorie k
	where k.id=$id and p.power>=k.min_power order by id";
        else
            $db_query = "select 0 as id,'extern' as description union select power as id,non_empty($lang,$lang2) from power order by id";
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'min_power',
            $this->t('Access for', 'Zugang für'),
            db_query: $db_query
        ));
        $form->addField(new \BayCMS\Field\SelectJSMulti(
            $this->context,
            'id_benutzer',
            'select id,kommentar||\' (\'||login||\')\' as description from benutzer where id in (${in})',
            '/' . $this->context->getOrgLinkLang() . '/verwaltung/gru/user_select.php',
            $this->t('Additional access for selected user/groups', 'Zusätzlicher Zugang für ausgewählte Nutzer/Gruppen')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'no_dp_first',
            $this->t('Do not show first menu item', 'Ersten Indexeintrag nicht anzeigen')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'exclude_from_top_navi',
            $this->t('Exclude category from TAB-navigation', 'Kategorie von Karteireiter-Navigation ausschließen')
        ));
        if ($id) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select * from kat_aliases where id_kat=$1 and id_lehr=$2',
                [$id, $this->context->getOrgId()]
            );
            if (!pg_num_rows($res))
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select *,$2 as id_lehr from kategorie where id=$1',
                    [$id, $this->context->getOrgId()]
                );
            $r = pg_fetch_array($res, 0);
            $form->setId($id);
            $res=pg_query_params(
                $this->context->getDbConn(),
                'select comma(id_benutzer) from hat_zugang where id_kat=$1 and id_lehr=$2',
                [$id, $this->context->getOrgId()]
            );
            [$ids]=pg_fetch_row($res,0);
            $ids=str_replace(', ',',',$ids);
            $r['id_benutzer']=$ids;
            $_POST['id_benutzer_alt']=$ids;
            $form->setValues($r);
        }
        return $form;
    }

    private function katSave(int $id)
    {
        //Nicht mehr benötigte Kategorien suchen und löschen:
        $id_org = $this->context->getOrgId();
        $res = pg_query(
            $this->context->getDbConn(),
            "select k.id,f.id as id_file from 
            kategorie k left outer join file f on f.id_kat=k.id and f.name ilike '%/gru/html.php'
            where k.id_lehr=$id_org and k.id>10000" .
            ($id ? " and k.id!=$id" : '') . " and k.id not in (select id_super from index_files where id_super>10000)
        and k.id not in (select f.id_kat from file f, kategorie k where f.id_kat=k.id and k.id_lehr=$id_org
        and not f.name ilike '%/gru/html.php') "
        );
        $file = new \BayCMS\Base\BayCMSFile($this->context);
        $this->context->setSystemUser();
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            if ($r['id_file']) {
                $file->load($r['id_file']);
                $file->erase(true);
            }
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from kategorie where id=$1',
                [$r['id']]
            );
        }
        $this->context->setSystemUser(false);
        $ok = pg_query($this->context->getRwDbConn(), "begin");

        $de = $_POST['de'];
        $en = $_POST['en'];
        if (!$de)
            $de = $en;
        if (!$en)
            $en = $de;
        $min_power = $_POST['min_power'];
        $ordnung = $_POST['ordnung'];
        $no_dp_first = ($_POST['no_dp_first'] ?? false) ? 'true' : 'false';
        $exclude_from_top_navi = ($_POST['exclude_from_top_navi'] ?? false) ? 'true' : 'false';
        if (!$id) {
            $link = \BayCMS\Base\BayCMSFile::replaceLocalDe($de);
            $res = pg_query(
                $this->context->getDbConn(),
                "select id from kategorie where link='$link'"
            );
            if (pg_num_rows($res))
                [$id] = pg_fetch_row($res, 0);
        }
        if ($id) {
            if ($id > 10000)
                $ok = pg_query(
                    $this->context->getDbConn(),
                    "update kategorie set 
                    de='" . htmlspecialchars($de) . "',
                    en='" . htmlspecialchars($en) . "',
                    ordnung=$ordnung,min_power=$min_power,no_dp_first=$no_dp_first 
                    where id=$id and id_lehr=$id_org"
                );
        } else {
            $res = pg_query($this->context->getRwDbConn(), "select nextval('index_files_id_seq')");
            [$id] = pg_fetch_row($res, 0);
            $link = substr(\BayCMS\Base\BayCMSFile::replaceLocalDe(strtolower($de)), 0, 10);

            $linkbase = $link;
            $res = pg_query($this->context->getRwDbConn(), "select id from kategorie where link='$link'");
            $nr = 1;
            while (pg_num_rows($res)) {
                $link = $linkbase . $nr;
                $res = pg_query($this->context->getRwDbConn(), "select id from kategorie where link='$link'");
                $nr++;
            }
            $ok = pg_query(
                $this->context->getRwDbConn(),
                "insert into kategorie(id,ordnung,link,de,en,min_power,id_lehr,no_dp_first) 
            values ($id,$ordnung,'$link','" . str_replace(' ', '&nbsp;', htmlspecialchars($de)) . "','" . str_replace(' ', '&nbsp;', htmlspecialchars($en)) . "',$min_power,$id_org,$no_dp_first)"
            );
            if ($ok)
                $ok = pg_query(
                    $this->context->getRwDbConn(),
                    "insert into index_files(id,de,en) select id,de,en from kategorie where id=$id"
                );
        }

        if ($ok) {
            if ($_POST['id_benutzer'] != $_POST['id_benutzer_alt']) {
                $ok = pg_query($this->context->getRwDbConn(), "delete from hat_zugang where id_kat=$id and id_lehr=" . $id_org);
                $ids = explode(",", $_POST['id_benutzer']);
                for ($i = 0; $i < count($ids); $i++) {
                    if (is_numeric($ids[$i]) && $ok)
                        $ok = pg_query($this->context->getRwDbConn(), "insert into hat_zugang(id_kat,id_lehr,id_benutzer) values ($id," . $id_org . ",$ids[$i])");
                }
            }
        }
        if ($ok) {
            $ok = pg_query($this->context->getRwDbConn(), "delete from kat_aliases where id_kat=$id and id_lehr=$id_org");
            if ($ok)
                $ok = pg_query($this->context->getRwDbConn(), "insert into kat_aliases(id_kat,id_lehr,de,en,ordnung,min_power,no_dp_first,exclude_from_top_navi)
                    values ($id,$id_org,'" . str_replace(' ', '&nbsp;', htmlspecialchars($de)) . "',
                    '" . str_replace(' ', '&nbsp;', htmlspecialchars($en)) . "',
                    $ordnung,$min_power,$no_dp_first,$exclude_from_top_navi)");
        }

        if ($ok && isset($_POST['fix'])) {
            $res = pg_query($this->context->getRwDbConn(), "select * from (select distinct on(k_ord) * from
                (select case when a.ordnung is null then k.ordnung else a.ordnung end as k_ord, a.id_kat as id
                from index_files i,	kategorie k left outer join (select * from kat_aliases where id_lehr=$id_org) a on
                k.id=a.id_kat where i.id_lehr=$id_org and i.id_super=k.id and k.id!=1000) a )
                a order by k_ord");
            $o_hash = array();
            $prev = 0;
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_array($res, $i);
                $o_hash[] = array('ord' => $r['k_ord'], 'a_id_kat' => $r['id'], 'to_prev' => ($r['k_ord'] - $prev));
                $prev = $r['k_ord'];
            }

            $current_i = 0;
            $current_ord = 100;
            for ($i = 0; $i < count($o_hash); $i++) {
                if (!$o_hash[$i]['a_id_kat']) {
                    for ($j = $current_i; $j < $i; $j++) {
                        $o_hash[$j]['new_ord'] = round($o_hash[$i]['ord'] -
                            ($o_hash[$i]['ord'] - $current_ord) / ($i - $current_i) * ($i - $j));
                    }
                    $current_i = $i;
                    $current_ord = $o_hash[$i]['ord'];

                }
            }

            pg_prepare(
                $this->context->getRwDbConn(),
                'update_aliases',
                'update kat_aliases set ordnung=$1 where id_kat=$2 and id_lehr=$3'
            );
            for ($i = 0; $i < count($o_hash); $i++) {
                if ($o_hash[$i]['ord'] != $o_hash['$i']['new_ord'] && $o_hash[$i]['a_id_kat']) {
                    pg_execute(
                        $this->context->getRwDbConn(),
                        'update_aliases',
                        [$o_hash[$i]['new_ord'], $o_hash[$i]['a_id_kat'], $id_org]
                    );
                }
            }

        }
        if (!$ok) {
            $this->context->TE->printMessage(
                $this->t(
                    'Could not save data to database',
                    'Probleme beim Speichern in der Datenbank'
                ) . ': ' . pg_last_error($this->context->getRwDbConn()),
                'danger'
            );
            $k_aktion = "edit";
        } else {
            pg_query($this->context->getRwDbConn(), "commit");
            $this->context->TE->printMessage($this->t('Saved entry', 'Eintrag gespeichert'));
        }
    }

    public function getIndex()
    {
        $out = $this->context->TE->getActionLink('?aktion=kedit', $this->t('New Category', 'Neue Kategorie'), '', 'plus') . "<br>\n";

        $res = pg_query(
            $this->context->getDbConn(),
            "select * from (select distinct on(k_ord) * from 
        (select case when a.min_power>k.min_power then a.min_power else k.min_power end as min_power, 
        case when a.ordnung is null then k.ordnung else a.ordnung end as k_ord, k.id, 
        non_empty(non_empty(" . $this->context->getLangLang2('a.') . "),k." . $this->context->lang . ") as text 
        from index_files i,
        kategorie k left outer join (select * from kat_aliases where id_lehr=" . $this->context->getOrgId() . ") a on 
        k.id=a.id_kat where i.id_lehr=" . $this->context->getOrgId() . " and (i.id_super=k.id or k.id=" . $this->id_kat . ")) a ) a order by min_power>0,k_ord"
        );
        $out .= "<h4>" . $this->t('External Categories', 'Externe Kategorien') . "</h4>\n";
        $extern = 1;
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            if ($r['min_power'] > 0 && $extern) {
                $out .= "<h4>" . $this->t('Internal Categories', 'Interne Kategorien') . "</h4>\n";
                $extern = 0;
            }
            if ($r['id'] == $this->id_kat) {
                $out .= "<a href=\"?\"><b><i>$r[text]</i></b></a> -- " . $this->context->TE->getActionLink('?aktion=kedit&id=' . $this->id_kat, $this->t('edit', 'bearbeiten'), '', 'edit') .
                    "<br/>\n";
                $out .= '<form action="?aktion=sort&id=' . ($_GET['id'] ?? 0) . '" method="post">';

                $bootstrap = $this->context->TE->isBootstrap();
                $t = new \BayCMS\Util\Tree(
                    $this->context,
                    "select i.id,
                    'folder' as type,
                    '<input name=\"o['||i.id||']\" value=\"'||i.ordnung||'\" type=\"number\" style=\"width:50px;\">&nbsp;'||
                    non_empty(" . $this->context->getLangLang2('i.') . ",50) as description,
                    true as write_access,
                    case when k.id is not null then true else
                    " . ($bootstrap ? 'id_file is null' : "id_file is not null and not (auto_query_de>'' or auto_query_en>'')") . "
                    end as child_allowed 
                    from index_files i left outer join kategorie k on i.id_super=k.id where i.id_lehr=" . $this->context->getOrgId() . ' and i.id_super=$1
                    order by i.ordnung desc, 3',
                    'select id_super as id from index_files where id=$1',
                    ['edit', 'del'],
                    $r['id']
                );
                $new_item_string = $this->context->TE->getActionLink('?aktion=edit&id_parent=$1', $this->t('new item', 'neuer Eintrag'), '', 'plus');
                $out .= $t->getTree($new_item_string);
                $out .= '<input type="submit" value="' . $this->t('Save Order', 'Ordnung speichern') . '"></form>';

            } else
                $out .= "<a href=\"?id=$r[id]\">$r[text]</a><br/>\n";
        }

        return $out;

    }



    public function page(string $pre_content='')
    {
        $this->context->printHeader();
        echo $pre_content;
        $action = $_GET['aktion'] ?? '';
        if ($action == 'del') {
            $res = pg_query_params(
                $this->context->getRwDbConn(),
                'delete from index_files where id_lehr=$1 and id=$2',
                [$this->context->getOrgId(), $_GET['id']]
            );
            if ($res)
                $this->context->TE->printMessage($this->t('Navigation entry deleted', 'Navigationseintrag gelöscht'));
            else
                $this->context->TE->printMessage('Error: ' . pg_last_error($this->context->getRwDbConn(), 'danger'));
        }
        if ($action == 'sort' && isset($_POST['o'])) {
            pg_prepare($this->context->getRwDbConn(), 'update_ord', 'update index_files set ordnung=$1 where id=$2 and id_lehr=$3');
            pg_query($this->context->getRwDbConn(), 'begin');
            foreach ($_POST['o'] as $id => $v) {
                pg_execute($this->context->getRwDbConn(), 'update_ord', [$v, $id, $this->context->getOrgId()]);
            }
            pg_query($this->context->getRwDbConn(), 'commit');
        }

        if ($action == 'ksave' && !$_GET['id']) {
            $_GET['id'] = $_POST['id'];
            $this->id_kat = $_POST['id'];
            $action = '';
        }

        if ($action == 'kedit' || $action == 'ksave') {
            $form = $this->katForm();
        }
        if ($action == 'ksave') {
            $error = $form->setValues($_POST);
            if ($error)
                $action = 'kedit';
        }

        if ($action == 'ksave')
            $this->katSave($_GET['id']);
        if ($action == 'kedit')
            echo $form->getForm();


        if ($action == 'edit' || $action == 'save') {
            $form = $this->indexForm();
            if($_POST['type']??'') $action='edit';
            if ($action == 'save') {
                if($_POST['id_obj']??false){
                    $_POST['qs']='?id_obj='.$_POST['id_obj'];
                }
                $error = $form->setValues($_POST);
                if ($error)
                    $action = 'edit';
            }
            if ($action == 'save' && ($_GET['type']??false)) {
                try {
                    $_GET['id'] = $form->save();
                } catch (\Exception $e) {
                    $this->error(500, $e->getMessage());
                }
                $this->context->TE->printMessage($this->t('Entry saved', 'Eintrag gespeichert'));
            } else
                echo $form->getForm();
        }

        echo $this->getIndex();

        $this->context->printFooter();
    }

}