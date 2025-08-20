<?php

namespace BayCMS\Page\Admin;

use Exception;

class Org extends \BayCMS\Page\Page
{



    public function delete(int $id, bool $delete = false): string
    {
        if ($this->context->getPower() <= 1000)
            throw new \BayCMS\Exception\accessDenied("You need to log in as super user");

        $res = pg_query_params(
            $this->context->getDbConn(),
            'select * from lehrstuhl where id=$1',
            [$id]
        );
        if (!pg_num_rows($res))
            throw new \BayCMS\Exception\notFound("$id not found");

        $r = pg_fetch_array($res, 0);
        $ret = "<h3>$r[de]/$r[en]</h3><h4>Verzeichnis:</h4>";
        if ($r['link'] && is_dir($this->context->HTTP_PATH . '/' . $r['link']))
            $ret .= "<a href=\"/$r[link]\">$r[link]</a>";
        else
            $ret .= "Kein Link oder Link ($r[link]) nicht aktiv";

        $ret .= "<h4>Index</h4>";
        $res = pg_query_params($this->context->getDbConn(), 'select non_empty(de,en) from index_files where id_lehr=$1', [$id]);
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r2 = pg_fetch_array($res, $i);
            $ret .= "$r2[non_empty]<br/>";
        }
        if ($delete) {
            $res = pg_query_params($this->context->getRwDbConn(), 'delete from index_files where id_lehr=$1', [$id]);
            if (!$res) {
                $ret .= $this->context->TE->getMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                return $ret;
            }
        }


        $ret .= "<h4>User</h4>";
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select b.*, il.id_lehr from benutzer b, objekt o left outer join (select * from in_ls where id_lehr!=$1) il on il.id_benutzer=o.id where b.id=o.id and o.id_obj=$1',
            [$id]
        );
        if ($delete) {
            pg_prepare($this->context->getRwDbConn(), 'delete', 'delete from objekt where id=$1');
            pg_prepare($this->context->getRwDbConn(), 'move', 'update objekt set id_obj=$2 where id=$1');
            pg_prepare($this->context->getRwDbConn(), 'delete_access', 'delete from in_ls where id_lehr=$2 and id_benutzer=$1');
            pg_prepare($this->context->getRwDbConn(), 'delete_zuordnung', 'delete from objekt_ls where id_lehr=$2 and id_obj=$1');
        }

        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r2 = pg_fetch_array($res, $i);
            $ret .= "$r2[login] - $r2[id_lehr]";
            if ($delete && $r2['id'] != $r['id']) {
                if ($r2['id_lehr']) {
                    $res2 = pg_execute($this->context->getRwDbConn(), 'move', [$r2['id'], $r2['id_lehr']]);
                    $res2 = pg_execute($this->context->getRwDbConn(), 'delete_access', [$r2['id'], $r['id']]);

                    $res2 = pg_execute($this->context->getRwDbConn(), 'delete_zuordnung', [$r2['id'], $r['id']]);
                    if (!$res2) {
                        $ret .= $this->context->TE->getMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                        return $ret;
                    }
                    $ret .= " ... <span style=\"color:#0a0;\">moved to $r2[id_lehr]</span>";

                } else {
                    $res2 = pg_execute($this->context->getRwDbConn(), 'delete', [$r2['id']]);
                    if (!$res2) {
                        $ret .= $this->context->TE->getMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                        return $ret;
                    }
                    $ret .= " ... <span style=\"color:#f00;\">deleted</span>";

                }
            }
            $ret .= "<br/>";

        }


        $ret .= "<h4>Dateien</h4>";
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select f.*, ol.id_lehr from file f, objekt o left outer join (select * from objekt_ls where id_lehr!=$1) ol on ol.id_obj=o.id where f.id=o.id and o.id_obj=$1',
            [$id]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r2 = pg_fetch_array($res, $i);
            $ret .= "$r2[name] - $r2[id_lehr]";

            if ($delete && $r2['id'] != $r['id']) {
                if ($r2['id_lehr']) {
                    $res2 = pg_execute($this->context->getRwDbConn(), 'move', [$r2['id'], $r2['id_lehr']]);
                    $res2 = pg_execute($this->context->getRwDbConn(), 'delete_zuordnung', [$r2['id'], $r['id']]);
                    if (!$res2) {
                        $ret .= $this->context->TE->getMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                        return $ret;
                    }
                    $ret .= " ... <span style=\"color:#0a0;\">moved to $r2[id_lehr]</span>";

                } else {
                    $res2 = pg_execute($this->context->getRwDbConn(), 'delete', [$r2['id']]);
                    if (!$res2) {
                        $ret .= $this->context->TE->getMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                        return $ret;
                    }
                    $ret .= " ... <span style=\"color:#f00;\">deleted</span>";

                }
            }
            $ret .= "<br/>";

        }

        $ret .= "<h4>Objekte</h4>";
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select ao.uname,o.*, ol.id_lehr from art_objekt ao, objekt o, objekt_ls ol1 left outer join 
            (select * from objekt_ls where id_lehr!=$1) ol on ol.id_obj=ol1.id_obj where ao.id=o.id_art and ol1.id_obj=o.id and ol1.id_lehr=$1',
            [$id]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r2 = pg_fetch_array($res, $i);
            $ret .= "<b>$r2[uname]</b>: $r2[de]/$r[en] - $r2[id_lehr]";
            if ($delete && $r2['id'] != $r['id']) {
                if (!$r2['id_lehr']) {
                    $res2=pg_execute($this->context->getRwDbConn(), 'delete', [$r2['id']]);
                    if (!$res2) {
                        $ret .= $this->context->TE->getMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                        return $ret;
                    }
                    $ret .= " ... <span style=\"color:#f00;\">deleted</span>";
                }
            }
            $ret .= "<br/>";
        }

        if ($delete) {
            pg_query($this->context->getRwDbConn(), "drop view if exists objekt$r[id]");
            pg_query($this->context->getRwDbConn(), "drop view if exists objekt_verwaltung$r[id]");
            pg_query($this->context->getRwDbConn(), "drop view if exists objekt_admin$r[id]");
            pg_query($this->context->getRwDbConn(), "drop view if exists objekt_intern$r[id]");
            pg_execute($this->context->getRwDbConn(), 'delete', [$r['id']]);
            $ret .= "<h3>DELETE COMPLETE</h3>";
        } else {
            $ret .= $this->context->TE->getActionLink(
                "?id=$r[id]&delete=1",
                $this->t("delete", "löschen"),
                " onClick=\"return confirm('" . $this->t("Are you sure?", "Wirklich löschen?") . "')\"",
                'del'
            );
        }
        $ret .= "<hr/><br/><br/>";
        return $ret;


    }
    public function page()
    {
        $this->context->printHeader();

        if ($_GET['id'] ?? false) {
            try {
                echo $this->delete($_GET['id'], $_GET['delete'] ?? false);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

        $l = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            "lehrstuhl t, objekt o",
            "t.id=o.id",
            id_query: 't.id',
            jquery_row_click: true
        );
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Name',
            sql: 'non_empty(t.de,t.en)'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'link'
        ));
        echo $l->getTable();

        $this->context->printFooter();
    }
}