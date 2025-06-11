<?php

namespace BayCMS\Page\Admin;

class ModulUpdate extends \BayCMS\Page\Page
{
    private function checkUpdate(string $inst, string $target)
    {
        if (!$target)
            return false;
        $i = explode('.', $inst);
        $t = explode('.', $target);

        if (intval($i[0]) < intval($t[0]))
            return true;
        if (intval($i[0]) > intval($t[0]))
            return false;

        if (intval($i[1]) < intval($t[1]))
            return true;
        if (intval($i[1]) > intval($t[1]))
            return false;
        if (intval($i[2]) < intval($t[2]))
            return true;
        if (intval($i[2]) > intval($t[2]))
            return false;

        return false;
    }

    public function page()
    {
        if ($this->context->getPower() <= 1000)
            $this->error(403, 'Access denied. This is a superuser page');
        $this->context->printHeader();

        $res=pg_query($this->context->getRwDbConn(),"select value from sysconfig where key='UPDATE_SERVER'");
        if(pg_num_rows($res))[$server]=pg_fetch_row($res,0);
        else $server=BayCMSUpdateServer;
        $fp = fopen( $server. '/de/top/gru/server.php', 'r');
        $json = '';
        while (!feof($fp))
            $json = fread($fp, 2048);

        $hash = json_decode($json, true);


        if (($_GET['aktion'] ?? '') == 'update') {
            $res = pg_query($this->context->getDbConn(), "select * from modul order by uname");
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_array($res, $i);
                if(! $this->checkUpdate($r['kurz'],$hash[$r['uname']]??'')){
                    $this->context->TE->printMessage("$r[uname] is up to date");
                    continue;
                }
                $this->context->TE->printMessage("Updating $r[uname]");
                $m = new \BayCMS\Page\Admin\Modul($this->context, $r['id']);
                $m->onlineUpdate($r['uname']);
            }
        }


        echo '<h1>Modul Upgrade/Install</h1>
        <h3>Moduls Installed</h3>
        <table ' . $this->context->TE->getCSSClass('table') . '>
        <tr><th>Modul</th><th>Installed</th><th>Available</th><th>&nbsp;</th></tr>';
        $res = pg_query($this->context->getDbConn(), "select * from modul order by uname");
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            echo "<tr><td>$r[uname]</td><td>$r[kurz]</td><td>" . ($hash[$r['uname']] ?? '') . "</td>
            <td><a href=\"modul_reg_liste.php?install=$r[uname]\">Update</a></td></tr>\n";
            if (isset($hash[$r['uname']]))
                unset($hash[$r['uname']]);
        }
        echo '</table>';
        echo $this->context->TE->getActionLink("?aktion=update", 'Update all', '', 'refresh') . "<br/><br/>";

        echo '
<h3>Moduls available</h3>
<table ' . $this->context->TE->getCSSClass('table') . '>
<tr><th>Modul</th><th>Version</th><th>&nbsp;</th></tr>';
        foreach ($hash as $key => $value) {
            echo "<tr><td>$key</td><td>$value</td>
	<td><a href=\"modul_reg_liste.php?install=$key\">Install</a></td></tr>\n";

        }
        echo '</table>';


        $this->context->printFooter();
    }

}