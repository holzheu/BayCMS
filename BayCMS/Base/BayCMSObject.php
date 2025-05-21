<?php

namespace BayCMS\Base;
use Exception;

class BayCMSObject extends BayCMSRow
{
    protected string $uname;
    protected ?int $id_art = null;
    protected ?string $de = '';
    protected ?string $en = '';
    protected ?int $id_parent = null;
    protected ?string $stichwort = '';
    protected ?string $sichtbar = 't';
    protected ?string $child_allowed = 't';
    private ?string $og_description_de = '';
    private ?string $og_description_en = '';
    private ?int $og_img = null;
    private ?string $og_title_de = '';
    private ?string $og_title_en = '';
    private ?float $dtime = null;
    private ?float $utime = null;
    private ?float $ctime = null;
    private array $references = [];
    private bool $with_references = false;


    public function __get($name)
    {
        return $this->$name;
    }
    public function get(): array
    {
        $ret = [
            'uname' => $this->uname,
            'id' => $this->id,
            'id_art' => $this->id_art,
            'de' => $this->de,
            'en' => $this->en,
            'stichwort' => $this->stichwort,
            'id_parent' => $this->id_parent,
            'sichtbar' => $this->sichtbar,
            'child_allowed' => $this->child_allowed,
            'og_description_de' => $this->og_description_de,
            'og_description_en' => $this->og_description_en,
            'og_img' => $this->og_img,
            'og_title_de' => $this->og_title_de,
            'og_title_en' => $this->og_title_en,
            'ctime' => $this->ctime,
            'dtime' => $this->dtime,
            'utime' => $this->utime
        ];
        if ($this->with_references)
            $ret['references'] = $this->references;
        return $ret;

    }

    public function set(
        array $values = [],
        int $id_parent = -1,
        string $uname = null,
        int $id_art = -1,
        string $de = null,
        string $en = null,
        string $stichwort = null,
        bool $child_allowed = null,
        string $og_description_de = null,
        string $og_description_en = null,
        string $og_title_de = null,
        string $og_title_en = null,
        int $og_img = -1
    ) {
        $allowed_keys = [
            'id_parent',
            'uname',
            'id_art',
            'de',
            'en',
            'stichwort',
            'child_allowed',
            'og_description_de',
            'og_description_en',
            'og_title_de',
            'og_title_en',
            'og_img'
        ];
        foreach ($values as $k => $v) {
            if (!in_array($k, $allowed_keys))
                continue;
            $this->{$k} = $v;
        }
        if ($id_parent != -1)
            $this->id_parent = $id_parent;
        if ($id_art != -1)
            $this->id_art = $id_art;
        if ($og_img != -1)
            $this->og_img = $og_img;
        if ($uname !== null)
            $this->uname = $uname;
        if ($de !== null)
            $this->de = $de;
        if ($en !== null)
            $this->en = $en;
        if ($stichwort !== null)
            $this->stichwort = $stichwort;
        if ($child_allowed !== null)
            $this->child_allowed = $child_allowed;
        if ($og_description_de !== null)
            $this->og_description_de = $og_description_de;
        if ($og_description_en !== null)
            $this->og_description_en = $og_description_en;
        if ($og_title_de !== null)
            $this->og_title_de = $og_title_de;
        if ($og_title_en !== null)
            $this->og_title_en = $og_title_en;
    }

    public function loadReference()
    {
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_auf,ordnung from verweis where id_von=$1',
            [$this->id]
        );
        $this->references = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $this->references[] = pg_fetch_row($res, $i);
        }
    }

    public function saveReference()
    {
        $this->checkAccess();
        if (count($this->references)) {
            pg_query($this->context->getRwDbConn(), 'begin');
            pg_prepare(
                $this->context->getRwDbConn(),
                'new_ref',
                'insert into verweis(id_von,id_auf,ordnung) values ($1,$2,$3)'
            );
            pg_prepare(
                $this->context->getRwDbConn(),
                'del_ref',
                'delete from verweis where id_von=$1 and id_auf=$2 and ordnung=$3'
            );
            foreach ($this->references as $k => $v) {
                if (!isset($v[2]))
                    continue;
                pg_execute(
                    $this->context->getRWDbConn(),
                    $v[2] . "_ref",
                    [$this->id, $v[0], $v[1]]
                );
                unset($this->references[$k][2]);
            }
            pg_query($this->context->getRwDbConn(), 'commit');
        }

    }


    public function addReference(array $id_ref, int $order_num = 1)
    {
        $this->with_references = true;
        $hash = [];
        foreach ($id_ref as $k => $v) {
            $hash[$v] = $k;
        }
        foreach ($this->references as $k => $v) {
            if (isset($hash[$v[0]]) && $v[1] == $order_num)
                unset($id_ref[$hash[$v[0]]]);
        }
        foreach ($id_ref as $k => $v) {
            $this->references[] = [$v, $order_num, 'new'];
        }
    }

    public function deleteReference(array $id_ref, int $order_num = 1)
    {
        $this->with_references = true;
        $hash = [];
        foreach ($id_ref as $k => $v) {
            $hash[$v] = $k;
        }
        foreach ($this->references as $k => $v) {
            if (isset($hash[$v[0]]) && $v[1] == $order_num)
                $this->references[$k][2] = 'del';
        }
    }

    public function setReference(array $id_ref, int $order_num = 1)
    {
        $this->with_references = true;
        $hash = [];
        foreach ($id_ref as $k => $v) {
            $hash[$v] = $k;
        }
        foreach ($this->references as $k => $v) {
            if (isset($hash[$v[0]]) && $v[1] == $order_num)
                unset($id_ref[$hash[$v[0]]]);
            else
                $this->references[$k][2] = 'del';
        }
        $this->addReference($id_ref, $order_num);
    }


    public function load(int $id = null)
    {
        if ($id === null && $this->id === null) {
            throw new \BayCMS\Exception\missingId("Cound not load object without id");
        }
        if ($id !== null)
            $this->id = $id;

        //check if object is assigned to organsisation


        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select ao.uname,o.id_art,o.de,o.en,o.id_obj,o.stichwort,o.sichtbar,o.child_allowed,
            o.og_description_de,o.og_description_en,o.og_img,o.og_title_de,o.og_title_en,
            extract(EPOCH from ctime), extract(EPOCH from utime), extract(EPOCH from geloescht)
        from objekt o, art_objekt ao where ao.id=o.id_art and o.id=$1',
            [$this->id]
        );
        if (!pg_num_rows($res)) {
            throw new \BayCMS\Exception\notFound('Object with id=' . $this->id . ' does not exist');
        }
        [
            $this->uname,
            $this->id_art,
            $this->de,
            $this->en,
            $this->id_parent,
            $this->stichwort,
            $this->sichtbar,
            $this->child_allowed,
            $this->og_description_de,
            $this->og_description_en,
            $this->og_img,
            $this->og_title_de,
            $this->og_title_en,
            $this->ctime,
            $this->utime,
            $this->dtime
        ] =
            pg_fetch_row($res, 0);





    }

    public function checkReadAccess()
    {
        if ($this->id === null)
            return;

        $res = pg_query(
            $this->context->getRwDbConn(),
            "select id from objekt" . ($this->context->getMinPower() ? "_admin" : "") .
            $this->context->getOrgId() . " where id=" . $this->id
        );
        if (!pg_num_rows($res)) {
            $res = pg_query(
                $this->context->getRwDbConn(),
                "select a.link,a.httphost from lehrstuhl a, objekt_ls b,objekt c
            where b.id_obj=" . $this->id . " and b.id_lehr=a.id and b.id_obj=c.id and c.sichtbar
            and b.sichtbar and c.geloescht is null and a.link>'' order by a.priority desc"
            );
            if (pg_num_rows($res)) {
                $r = pg_fetch_array($res, 0);
                if (!$r['httphost'])
                    $r['httphost'] = $_SERVER['HTTP_HOST'];
                header("Location: http" . ($_SERVER['HTTP_HOST'] ? 's' : '') . "://$r[httphost]/$r[link]/" .
                    $this->context->lang . "/" . $this->context->kategorie . "/" . $this->context->file . "?$_SERVER[QUERY_STRING]");
                exit();
            } else {
                $this->context->set('min_power', 10);
            }
        }
    }

    public function getHTMLHeader($lang = 'de')
    {
        $lang2 = ($lang == 'de' ? 'en' : 'de');
        $out = '';
        $title = $this->{$lang};
        if (!$title)
            $title = $this->{$lang2};
        if ($title)
            $out .= "<meta name=\"description\" lang=\"$lang\" content=\"" . htmlspecialchars($title) . "\" />\n";
        if ($this->stichwort)
            $out .= "<meta name=\"keywords\" lang=\"$lang\" content=\"" . htmlspecialchars($this->stichwort) . "\" />\n";

        $desc = $this->{"og_description_$lang"};
        if (!$desc)
            $desc = $this->{"og_description_$lang2"};
        if ($desc) {
            $og_title = $this->{"og_title_$lang"};
            if (!$og_title)
                $$og_title = $this->{"og_title_$lang2"};
            if (!$og_title)
                $$og_title = $title;
            $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

            $out .= '<meta property="og:title" content="' . htmlspecialchars($og_title) . '" />
            <meta property="og:type" content="article" />
            <meta property="og:url" content="' . $actual_link . '" />
            <meta property="og:description" content="' . htmlspecialchars($desc) . '" />
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="' . htmlspecialchars($og_title) . '">
            <meta name="twitter:description" content="' . htmlspecialchars($desc) . '">' . "\n";
            if ($this->og_img) {
                $res = pg_query(
                    $this->context->getRwDbConn(),
                    "select name from bild where id=" . $this->og_img
                );
                list($og_img) = pg_fetch_row($res, 0);
                $out .= '<meta property="og:image" content="' .
                    "/" . $this->context->org_folder . "/$lang/image/$og_img" . '" />
<meta property="twitter:image" content="' .
                    "/" . $this->context->org_folder . "/$lang/image/$og_img" . '" />' . "\n";

            }

        }
        return $out;
    }

    public function checkWriteAccess(): bool
    {
        try {
            $this->checkAccess();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
    public function checkAccess()
    {
        if ($this->id === null)
            return;

        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select check_objekt($1,$2)',
            [$this->id, $this->context->getUserId()]
        );
        [$rw] = pg_fetch_row($res, 0);
        if ($rw != 't') {
            throw new \BayCMS\Exception\accessDenied('You have no write access to object ' . $this->id . '');
        }
    }

    public function pageCheck(int $id, bool $write = false)
    {
        try {
            $this->load($id);
        } catch (Exception $e) {
            $p = new \BayCMS\Page\ErrorPage($this->context, 404, $e->getMessage());
            $p->page();
        }
        if (!$write)
            return;
        try {
            $this->checkAccess();
        } catch (Exception $e) {
            $p = new \BayCMS\Page\ErrorPage($this->context, 401, $e->getMessage());
            $p->page();
        }
        return;

    }

    public function numOtherOrgs()
    {
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_obj from objekt_ls where
        id_obj=$1 and id_lehr!=$2',
            [$this->id, $this->context->getOrgId()]
        );
        return pg_num_rows($res);
    }

    public function erase($everywhere = false)
    {
        $this->checkAccess();
        if (!$everywhere && $this->numOtherOrgs()) {
            throw new \BayCMS\Exception\accessDenied('Cound not erase object as object is still in use in other organisations');
        }
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'delete from objekt where id=$1',
            [$this->id]
        );
        if (!$res) {
            throw new \BayCMS\Exception\databaseError(pg_last_error($this->context->getRwDbConn()));
        }
        $this->id = null;
    }

    public function unDelete()
    {
        $this->checkAccess();
        pg_query_params(
            $this->context->getRwDbConn(),
            'update objekt set geloescht=null,id_ubenutzer=$2 where id=$1',
            [$this->id, $this->context->getUserId()]
        );
    }

    public function delete(bool $everywhere = null)
    {
        if ($everywhere === null && isset($_GET['obj' . $this->id . 'rm']))
            $everywhere = $_GET['obj' . $this->id . 'rm'] == 't' ? true : false;


        $this->checkAccess();
        if (!$this->numOtherOrgs() || $everywhere === true) {
            pg_query_params(
                $this->context->getRwDbConn(),
                'update objekt set geloescht=now(),id_ubenutzer=$2 where id=$1',
                [$this->id, $this->context->getUserId()]
            );
            return;
        }
        if ($everywhere === false) {
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from objekt_ls where id_obj=$1 and id_lehr=$2',
                [$this->id, $this->context->getOrgId()]
            );
            return;
        }

        $content = 'Dieses Objekt ist aktuell folgenden Einheiten zugeordnet.<ul>';
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select non_empty(' . $this->context->getLangLang2('l.') . ') from lehrstuhl l, objekt_ls ol
        where l.id=ol.id_lehr and ol.id_obj=$1 order by 1',
            [$this->id]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$ls] = pg_fetch_row($res, $i);
            $content .= "<li>$ls</li>";
        }
        $content .= "</ul>
        ";
        $content .= '<button onclick="location.href=\'?' . $_SERVER['QUERY_STRING'] . '&obj' . $this->id . 'rm=t\'" type="button">
         Überall löschen</button>  ';
        $content .= '<button onclick="location.href=\'?' . $_SERVER['QUERY_STRING'] . '&obj' . $this->id . 'rm=f\'" type="button">
         Nur Zuordnung löschen</button>  ';

        $p = new \BayCMS\Page\Notice($this->context, $content);
        $p->page();



    }

    /**
     * Saves a object to the database
     * @throws \Exception
     * @return int
     */
    public function save($start_transaction = true): int
    {
        if ($this->id_art === null) {
            $res = pg_query_params(
                $this->context->getRWDbConn(),
                'select id from art_objekt where uname=$1',
                [$this->uname]
            );
            if (!pg_num_rows($res)) {
                throw new \BayCMS\Exception\notFound('Objecttype ' . $this->uname . ' is not defined');
            }
            [$this->id_art] = pg_fetch_row($res, 0);
        }

        if ($start_transaction)
            pg_query($this->context->getRwDbConn(), 'begin');
        if ($this->id === null) {
            $res = pg_query_params(
                $this->context->getRwDbConn(),
                'select create_objekt($1,$2,$3,$4,$5,$6,$7,$8)',
                [
                    $this->context->getUserId(),
                    $this->context->getOrgId(),
                    $this->id_art,
                    $this->stichwort,
                    null,
                    $this->de,
                    $this->en,
                    $this->id_parent
                ]
            );
            if (!$res) {
                $e = pg_last_error($this->context->getRwDbConn());
                if ($start_transaction)
                    pg_query($this->context->getRwDbConn(), 'rollback');
                throw new \BayCMS\Exception\databaseError($e);
            }
            [$this->id] = pg_fetch_row($res, 0);
        }

        $this->checkAccess();
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'update objekt set utime=now(),id_art=$1,de=$2,
            en=$3,stichwort=$4,id_obj=$5,id_ubenutzer=$6, sichtbar=$7, child_allowed = $8, 
            og_description_de=$9, og_description_en=$10, og_img=$11, og_title_de=$12, og_title_en=$13
                where id=$14',
            [
                $this->id_art,
                $this->de,
                $this->en,
                $this->stichwort,
                $this->id_parent,
                $this->context->getUserId(),
                $this->sichtbar,
                $this->child_allowed,
                $this->og_description_de,
                $this->og_description_en,
                $this->og_img,
                $this->og_title_de,
                $this->og_title_en,
                $this->id
            ]
        );

        if ($start_transaction)
            pg_query($this->context->getRwDbConn(), 'commit');
        return $this->id;
    }


    public function setAccess(array $id_user)
    {
        $this->checkAccess();
        if ($this->id === null) {
            throw new \BayCMS\Exception\missingId("You have to save the object first.");
        }
        $hash = [];
        foreach ($id_user as $k => $v) {
            $hash[$v] = $k;
        }

        pg_query($this->context->getRwDbConn(), 'begin');
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_benutzer from zugriff where id_obj=$1',
            [$this->id]
        );

        $del = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);
            if (isset($hash[$r[0]]))
                unset($id_user[$hash[$r[0]]]);
            else
                $del[] = $r[0];
        }

        if (count($del)) {
            pg_prepare($this->context->getRwDbConn(), 'delete_access', 'delete from zugriff where id_obj=$1 and id_benutzer=$2');
            foreach ($del as $v) {
                pg_execute($this->context->getRwDbConn(), 'delete_access', [$this->id, $v]);
            }
        }

        if (count($id_user)) {
            pg_prepare($this->context->getRwDbConn(), 'check_access', 'select check_objekt($1, $2, $3)');
            pg_prepare($this->context->getRwDbConn(), 'insert_access', 'insert into zugriff(id_obj, id_benutzer) values ($1, $2)');
            foreach ($id_user as $v) {
                $res = pg_execute(
                    $this->context->getRwDbConn(),
                    'check_access',
                    [$this->id, $v, $this->context->getOrgId()]
                );
                if (pg_fetch_array($res, 0)[0] == 'f')
                    pg_execute($this->context->getRwDbConn(), 'insert_access', [$this->id, $v]);
            }
        }
        pg_query($this->context->getRwDbConn(), 'commit');


    }


    public function setAssign(array $id_org)
    {
        $this->checkAccess();
        if ($this->id === null) {
            throw new \BayCMS\Exception\missingId("You have to save the object first.");
        }
        $hash = [];
        foreach ($id_org as $k => $v) {
            $hash[$v] = $k;
        }

        pg_query($this->context->getRwDbConn(), 'begin');
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_lehr from objekt_ls where id_obj=$1',
            [$this->id]
        );

        $del = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);
            if (isset($hash[$r[0]]))
                unset($id_org[$hash[$r[0]]]);
            else
                $del[] = $r[0];
        }


        if (count($del)) {
            pg_prepare($this->context->getRwDbConn(), 'delete_assign', 'delete from objekt_ls where id_obj=$1 and id_lehr=$2');
            foreach ($del as $v) {
                pg_execute($this->context->getRwDbConn(), 'delete_assign', [$this->id, $v]);
            }
        }

        if (count($id_org)) {
            pg_prepare($this->context->getRwDbConn(), 'insert_assign', 'select set_objekt_ls($1,$2,$3)');
            foreach ($id_org as $v) {
                pg_execute($this->context->getRwDbConn(), 'insert_assign', [$this->id, $v, $this->context->getOrgId()]);
            }
        }
        pg_query($this->context->getRwDbConn(), 'commit');
    }

}