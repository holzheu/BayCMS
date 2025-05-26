<?php

namespace BayCMS\Page;

class Image extends Page
{

    private \BayCMS\Fieldset\Form $form;
    private string $qs;

    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct($context);
        $qs = [];
        if ($_GET['id_parent'] ?? false)
            $qs[] = 'id_parent=' . $_GET['id_parent'];
        if ($_GET['such'] ?? false)
            $qs[] = 'such=' . urlencode($_GET['such']);
        if ($_GET['js_select'] ?? false)
            $qs[] = 'js_select=' . $_GET['js_select'];
        if ($_GET['target'] ?? false)
            $qs[] = 'target=' . $_GET['target'];
        $this->qs = implode('&', $qs);

        $this->form = new \BayCMS\Fieldset\Form($context, table: 'bild', qs: $this->qs);
        $this->form->addField(new \BayCMS\Field\UploadImage(
            $context,
            'datei',
            $this->t('Image File', 'Bild Datei'),
            help: $this->t('Accepted files', 'Akzeptierte Dateien') . ': gif, png, jpeg'
        ));
        $this->form->addField(new \BayCMS\Field\BilangInput(
            $context,
            '',
            'Name'
        ));
        $this->form->addField(new \BayCMS\Field\Number($context, 'height', $this->t('Scale to', 'Skalieren auf') . ' (px)', default_value: 800));
        $this->form->addField(new \BayCMS\Field\Select(
            $context,
            'crop',
            $this->t('crop to', 'Zuschneiden auf'),
            values: [['0', '-'], ['1', '1:1'], ['1.5', '3:2'], ['2.0', '2:1'], ['2.5', '5:2 (UBT-Style)']]
        ));
        $this->form->addField(new \BayCMS\Field\Number($context, 'theight', $this->t('Thumbnail size', 'Thumbnail Größe'), default_value: 120));
        $this->form->addField(new \BayCMS\Field\Select(
            $context,
            'tcrop',
            $this->t('crop thumbnail to', 'Thumbnail zuschneiden auf'),
            values: [['0', '-'], ['1', '1:1'], ['1.5', '3:2'], ['2.0', '2:1'], ['2.5', '5:2 (UBT-Style)']]
        ));
        $this->form->addField(new \BayCMS\Field\Checkbox($context, 'intern', $this->t('internal only', 'nur intern')));


    }

    private function getObjectId()
    {
        if ($_GET['id_parent'] ?? false)
            return $_GET['id_parent'];
        if (
            pg_num_rows(pg_query(
                $this->context->getDbConn(),
                "select 1 where check_objekt(" . $this->context->getOrgId() . ',' . $this->context->getUserId() . ")"
            ))
        )
            return $this->context->getOrgId();
        if (
            pg_num_rows(
                $res = pg_query(
                    $this->context->getDbConn(),
                    "select o.id from objekt_verwaltung" . $this->context->getOrgId() . " o, art_objekt ao 
            where ao.uname='bild_container' 
				and ao.id=o.id_art and check_objekt(o.id," . $this->context->getUserId() . ") limit 1"
                )
            )
        ) {
            [$id] = pg_fetch_row($res, 0);
            return $id;
        }
        $obj = new \BayCMS\Base\BayCMSObject($this->context);
        $obj->set(['uname' => 'bild_container']);
        $id = $obj->save();
        return $id;

    }

    private function save()
    {
        $img = new \BayCMS\Base\BayCMSImage($this->context);
        if ($_GET['id'] ?? false)
            $img->load($_GET['id']);


        $prop = [
            'de' => $_POST['de'] ?? '',
            'en' => $_POST['en'] ?? '',
            'id_obj' => $this->getObjectId(),
            'internal' => $_POST['intern'] ?? false,
            'height' => intval($_POST['height'] ?? 0),
            'theight' => intval($_POST['theight'] ?? 0),
            'crop' => floatval($_POST['crop'] ?? 0),
            'tcrop' => floatval($_POST['tcrop'] ?? 0)
        ];


        $source = $this->form->getField('datei')->getFileLocation();

        if (strstr($source, '/tmp/')) {
            $prop['source'] = $source;
            $prop['name'] = $this->form->getField('datei')->getFileName();
        }
        $img->set($prop);
        return $img->save();
    }


    protected function getHead()
    {
        $head = ($_GET['js_select'] ?? false) ?
            $this->t('Select image', 'Bild auswählen') :
            $this->t('Manage images', 'Bilder verwalten');
        $out = '';
        $out .= "<h3>$head</h3>\n";
        if ($_GET['js_select'] ?? false) {
            $out .= \BayCMS\Util\JSHead::get(target: 'iurl', target_dp: 'ialt');
        }
        return $out;

    }
    public function pageTiny()
    {
        reset($_FILES);
        $temp = current($_FILES);
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
        header('P3P: CP="There is no P3P policy."');
        // Sanitize input
        if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
            header("HTTP/1.0 500 Invalid file name.");
            exit();
        }

        // Verify extension
        if (!in_array(strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION)), array("gif", "jpg", "png"))) {
            header("HTTP/1.0 500 Invalid extension.");
            exit();
        }
        $_POST['height'] = 800;
        $_POST['theight'] = 120;
        $_POST['de'] = 'TinyMCE Upload';
        $_POST['datei_name']=$temp['name'];
        $_FILES['datei'] = $temp;
        $this->form->setValues($_POST);
        if ($id = $this->save()) {
            [$name] = pg_fetch_row(pg_query_params(
                $this->context->getRwDbConn(),
                'select name from bild where id=$1',
                [$id]
            ));
            echo json_encode(['location' => '/' . $this->context->org_folder . '/de/image/' . $name]);
        } else
            header("HTTP/1.0 500 Server Error");
        exit();

    }

    public function pageDetails(int $id)
    {
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select *, check_objekt(id_obj,$2),non_empty(' . $this->context->getLangLang2('') . ') from bild where id=$1',
            [$id, $this->context->getUserId()]
        );
        if (!pg_num_rows($res))
            return;
        $r = pg_fetch_array($res);
        $url = '/' . $this->context->org_folder . '/de/';
        if ($r['intern'] == 't')
            $url .= 'intern/gru/get_image.php?i=';
        else
            $url .= 'image/';
        $factor = 500 / max($r['x'], $r['y']);
        if ($factor > 1)
            $factor = 1;

        if ($_GET['js_select'] ?? false)
            $image_link = '<a href="#" title="' . $this->t("take over", "übernehmen") .
                '" onClick="add(\'' . $url . $r['name'] . "','" . addslashes($r['non_empty']) . "')\">";
        else
            $image_link = '<a href="?'.$this->qs.'" title="'.$this->t('back to list','Zurück zur Liste').'">';
        echo $image_link.'<img src="' . $url . $r['name'] . '" style="float=right;height:' . round($factor * $r['y']) . 'px; width:' . round($factor * $r['x']) . 'px;"></a>';
        echo '<table ' . $this->context->TE->getCSSClass('table') . '>';
        echo "
        <tr><td>DE:</td><td>$r[de]</td></tr>
        <tr><td>EN:</td><td>$r[en]</td></tr>
        <tr><td>Internal only</td><td>" . ($r['intern'] == 't' ? 'Yes' : 'No') . "</td></tr>
        <tr><td>Size:</td><td>$r[x]x$r[y] px</td></tr>
        <tr><td>Thumbnail:</td><td><a href=\"$url" . "t$r[name]\" target=\"_blank\">$r[tx]x$r[ty] px</a></td></tr>
        <tr><td>Original:</td><td><a href=\"$url" . "o$r[name]\" target=\"_blank\">$r[ox]x$r[oy] px</a></td></tr>
        </table>
        ";
        if ($r['check_objekt'] == 't')
            echo $this->context->TE->getActionLink(
                "?aktion=del&id=" . $r['id'] . "&" . $this->qs,
                $this->t("delete", "löschen"),
                " onClick=\"return confirm('" . $this->t("Are you sure?", "Wirklich löschen?") . "')\"",
                'del'
            ) . " &nbsp; " .
                $this->context->TE->getActionLink(
                    "?aktion=edit&id=" . $r['id'] . "&" . $this->qs,
                    $this->t("edit", "bearbeiten"),
                    "",
                    'edit'
                ) . " &nbsp; ";
        if ($_GET['js_select'] ?? false)
            echo $this->context->TE->getActionLink(
                "#",
                $this->t("take over", "übernehmen"),
                " onClick=\"add('" . $url . $r['name'] . "','" . addslashes($r['non_empty']) . "')\"",
                'ok'
            ) . " &nbsp; ";

        echo $this->context->TE->getActionLink(
            "?" . $this->qs,
            $this->t("back to list", "zurück zur Liste"),
            "",
            'arrow-left'
        );

        $this->context->printFooter();

    }

    public function page()
    {
        if ($_GET['tinyupload'] ?? false)
            $this->pageTiny();



        $this->context->printHeader();
        echo $this->getHead();

        if (($_GET['aktion'] ?? '') == 'del') {
            $img = new \BayCMS\Base\BayCMSImage($this->context);
            try {
                $img->load($_GET['id']);
                $img->erase();
                $this->context->TE->printMessage($this->t('Image deleted', 'Bild gelöscht'));
                unset($_GET['id']);
            } catch (\Exception $e) {
                $this->context->TE->printMessage($this->t('Could not delete Image', 'Konnte Bild nicht löschen') . ': ' . $e->getMessage());
            }
        }
        if (($_GET['aktion'] ?? '') == 'save') {
            $error = $this->form->setValues($_POST);
            if ($error) {
                $_GET['aktion'] = 'edit';
            } else {
                $_GET['id'] = $this->save();
                $_GET['aktion'] = '';
            }
        }

        if (($_GET['aktion'] ?? '') == 'edit') {
            if ($_GET['id'] ?? false)
                $this->form->load($_GET['id']);
            echo $this->form->getForm();
            $this->context->printFooter();
        }







        if ($_GET['id'] ?? false)
            $this->pageDetails($_GET['id']);


        $sform = new \BayCMS\Fieldset\Form($this->context);
        $sform->addField(new \BayCMS\Field\TextInput($this->context, 'such', placeholder: $this->t('search', 'suchen')));
        $sform->setValues($_GET);
        echo $sform->getSearchForm();


        echo $this->context->TE->getActionLink('?aktion=edit&' . $this->qs, $this->t('New Image', 'Neues Bild'), '', 'new') . ' ';

        $offset = intval($_GET['offset'] ?? 0);

        $such_query = "";
        $such = pg_escape_string($this->context->getDbConn(), $_GET['such'] ?? '');
        if ($such)
            $such_query .= " and (b.de ilike '%$such%' or b.en ilike '%$such%' or o.de ilike '%$such%' or o.en ilike '%$such%')";

        $res = pg_query(
            $this->context->getDbConn(),
            'select b.* from bild b, objekt_verwaltung' . $this->context->getOrgId() . ' o
        where o.id=b.id_obj ' . $such_query . ' order by b.id desc limit 51 offset ' . $offset
        );
        if ($offset)
            echo $this->context->TE->getActionLink('?' . $this->qs . '&offset=' . ($offset - 50), $this->t('back', 'zurück'), '', 'arrow-left');
        if (pg_num_rows($res) > 50)
            echo $this->context->TE->getActionLink('?' . $this->qs . '&offset=' . ($offset + 50), $this->t('forward', 'weiter'), '', 'arrow-right');
        echo '<div style="clear:both;"/>';

        for ($i = 0; $i < min(pg_num_rows($res), 50); $i++) {
            $r = pg_fetch_array($res, $i);
            $url = '/' . $this->context->org_folder . '/de/';
            if ($r['intern'] == 't')
                $url .= 'intern/gru/get_image.php?i=';
            else
                $url .= 'image/';
            if ($r['tx'])
                $url .= 't';
            $url .= $r['name'];
            echo '<a href="?id=' . $r['id'] . '&' . $this->qs . '"><div style=" vertical-align: middle; text-align: center; padding:5px; margin: 2px; float:left; height:120px; width: 120px';
            if ($r['intern'] == 't')
                echo '; background-color:#ddd';
            echo '"><img src="' . $url . '" style="max-width:100%; max-height:100%;"></div></a>' . "\n";
        }
        echo '<div style="clear:both;"/>';
        if ($offset)
            echo $this->context->TE->getActionLink('?' . $this->qs . '&offset=' . ($offset - 50), $this->t('back', 'zurück'), '', 'arrow-left');
        if (pg_num_rows($res) > 50)
            echo $this->context->TE->getActionLink('?' . $this->qs . '&offset=' . ($offset + 50), $this->t('forward', 'weiter'), '', 'arrow-right');


        $this->context->printFooter();
    }
}