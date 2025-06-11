<?php

namespace BayCMS\Page;

class Server extends Page
{
    public function page()
    {
        $enabled = 0;
        $res = pg_query($this->context->getDbConn(), "select value from sysconfig where key='BAYCMS_SERVER'");
        if (pg_num_rows($res))
            [$enabled] = pg_fetch_row($res, 0);
        if (!$enabled)
            $this->error(403, 'Access denied. To enable server please set BAYCMS_SERVER in sysconfig table');

        if (isset($_GET['mod']))
            $_GET['modul'] = $_GET['mod']; //new tar version!
        if ($_GET['modul'] ?? '') {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select id from modul where uname=$1',
                [$_GET['modul']]
            );
            if (!pg_num_rows($res))
                $this->error(401, 'Not found. Modul "' . $_GET['modul'] . ' does not exist on server.');
            [$id] = pg_fetch_row($res, 0);
            $m = new \BayCMS\Page\Admin\Modul($this->context, $id);
            if (isset($_GET['mod']))
                $m->mktar(); //new version
            $m->mktar_v1(); //old version
        }

        $res = pg_query($this->context->getDbConn(), "select uname,kurz from modul order by name");

        $json=[];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            if ($_GET['aktion'] ?? '')
                echo "$r[uname];$r[kurz]\n";        //Old TXT-List
            else $json[$r['uname']]=$r['kurz'];
        }
        if ($_GET['aktion'] ?? '')
            exit();


        //New JSON-List:
        echo json_encode($json);
        exit();
    }
}