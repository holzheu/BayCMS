<?php

namespace BayCMS\Page\Admin;

class Index extends \BayCMS\Page\Page
{
    public function page()
    {
        if (isset($_GET['q'])) {
            $q = $_GET['q'];
            if (is_numeric($q)) {
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select de,en from file where id=$1',
                    [$q]
                );
                if (!pg_num_rows($res)) {
                    $res = pg_query_params(
                        $this->context->getDbConn(),
                        'select de,en from html_seiten where id=$1',
                        [$q]
                    );
                }
                if (pg_num_rows($res)) {
                    $r = pg_fetch_assoc($res, 0);
                    echo json_encode($r);
                }
            }
            exit();
        }

        $names = [
            'Index',
            $this->t('HTML-Pages', 'HTML-Seiten'),
            $this->t('Files', 'Dateien'),
            $this->t('Modul Files', 'Modul-Dateien'),
            $this->t('Objects', 'Objekte'),
            $this->t('Starting Page', 'Startseite'),
            $this->t('Imprint', 'Impressum')
        ];
        $urls = ['', 'html', 'file', 'mod', 'obj', 'start', 'imprint'];

        if ($_GET['js_select'] ?? false) {
            if ($_GET['js_select'] == 'tiny')
                $active = [0, 1, 1, 0, 1, 0, 0];
            elseif ($_GET['js_select'] == '1') {
                if ($_GET['target'] == 'iid_obj')
                    $active = [0, 1, 0, 0, 0, 0, 0];
                else
                    $active = [0, 0, 1, 1, 0, 0, 0];
            }
        } else
            $active = [1, 1, 1, 0, 0, 1, 1];

        $u = [];
        $n = [];
        for ($i = 0; $i < count($active); $i++) {
            if ($active[$i]) {
                $u[] = $_SERVER['SCRIPT_NAME'] . ($urls[$i] ? '/' . $urls[$i] : '');
                $n[] = $names[$i];

            }
        }
        $qs=[];
        if($_GET['js_select']??false) $qs[]='js_select='.$_GET['js_select'];
        if($_GET['target']??false) $qs[]='target='.$_GET['target'];
        if($_GET['id_kat']??false) $qs[]='id_kat='.$_GET['id_kat'];
        $qs=implode('&',$qs);
        $nav = new \BayCMS\Util\TabNavigation(
            context: $this->context,
            names: $n,
            urls: $u,
            qs: $qs
        );
        $pre_content = $nav->getNavigation();
        if ($_SERVER['PATH_INFO'] ?? false) {
            $path = explode('/', $_SERVER['PATH_INFO']);
            switch ($path[1]) {
                case 'html':
                case 'start':
                case 'imprint':
                    $p = new \BayCMS\Page\Admin\HtmlPages($this->context,$qs);
                    break;
                case 'file':
                    $p = new \BayCMS\Page\Admin\IndexFiles($this->context,$qs);
                    break;
                case 'mod':
                    $p = new \BayCMS\Page\Admin\ModulFiles($this->context,$qs);
                    break;
                case 'obj':
                    $p = new \BayCMS\Page\Admin\ObjectList($this->context,$qs);
                    break;
            }
            if ($path[1] == 'start')
                $p->pageStart($pre_content);
            if ($path[1] == 'imprint')
                $p->pageImprint($pre_content);
        } else
            $p = new \BayCMS\Page\Admin\NavIndex($this->context);
        $p->page($pre_content);


    }
}