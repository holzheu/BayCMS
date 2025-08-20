<?php
namespace BayCMS\Base;

class BayCMSContext
{
    private $conn_ro = null;
    private $conn_rw = null;

    //Set in localconfig.inc
    private string $BayCMSRoot;
    private string $DB_EXTERN;
    private string $DB_OWNER;
    private string $DB_NAME;
    private string $HTTP_PATH;


    private string $SID;
    private \BayCMS\Base\BasicTemplate $TE;
    private string $lang = 'de';
    private string $lang2 = 'en';
    private string $org_folder;
    private string $kategorie = 'top';
    private string $modul = '';
    private string $php_file = '';
    private string $file = '';
    private string $title = '';
    private int $title_status = 0;
    private string $ADDITIONAL_HTML_HEAD = '';
    private string $additional_body_attributes = '';
    private string $last_modified = '';
    private string $object_title = '';
    private int $object_id = 0;
    private string $H_kat_query = '';
    private string $H_kat_query_extern = '';
    private string $H_kat_query_intern = '';
    private array $H_LoginLogout = [];


    private array $row1;

    private bool $no_frame = false;
    private bool $frameable = false;
    private bool $AUTH_OK = false;
    private bool $IP_AUTH_OK = false;
    private int $min_power = 0;
    private int $kat_min_power = 0;
    private int $kat_id = 1000;
    private string $header_kat = '';
    private string $page_prefix = '';
    private string $page_postfix = '';
    private array $tr = [];

    private bool $header_printed = false;
    public bool $commandline = false;

    public function __construct(string $BayCMSRoot, array $row1 = [])
    {
        require $BayCMSRoot . "/inc/localconfig.inc";
        $this->BayCMSRoot = $DOC_PFAD;
        $this->DB_EXTERN = $DB_EXTERN;
        $this->DB_OWNER = $DB_OWNER;
        $this->DB_NAME = $DB_NAME;
        $this->HTTP_PATH = $HTTP_PFAD;
        $this->row1 = $row1;

        $this->conn_ro = pg_connect($DB_EXTERN);
        pg_set_client_encoding($this->conn_ro, 'UTF-8');

        session_start();
        $this->SID = session_id();

        if (!isset($this->row1['id'])) {
            if (!isset($_SERVER['HTTP_HOST'])) {
                throw new \Exception('No way to create context');
            }
            // Find organisation ID
            if ($_SERVER['PHP_SELF'] != "/index.php") {
                $res = pg_query_params(
                    $this->conn_ro,
                    "select * from lehrstuhl where \$1 like link||'%' order by length(link) desc limit 1",
                    [substr($_SERVER['PHP_SELF'], 1)]
                );
            } else {
                [$dummy, $ls_link] = explode(".", $_SERVER['HTTP_HOST'], 3);
                $ls_link = strtolower($ls_link);
                $res = pg_query_params(
                    $this->conn_ro,
                    "select * from lehrstuhl where link=\$1 or (httphost_selector>'' and \$2 ilike httphost_selector)",
                    [$ls_link, $_SERVER['HTTP_HOST']]
                );
            }
            if (!pg_num_rows($res)) {
                $res = pg_query_params(
                    $this->conn_ro,
                    "select * from lehrstuhl where link=\$1",
                    [$LS_DEFAULT]
                );
            }

            if (pg_num_rows($res)) {
                $this->row1 = pg_fetch_array($res, 0);
                $this->org_folder = $this->row1['link'];
            } else {
                throw new \Exception('Unknown organisation');
            }
            $this->row1['power'] = 0;
            $res = pg_query($this->conn_ro, "select * from bild where id_obj=" . $this->row1['id'] . " and
            (de='org_logo' or de='org_favicon') ");
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_array($res, $i);
                $this->row1[$r['de']] = $r['name'];
            }

            // Parsen des Pfads: /$ls_link/$lang/$kategorie/$file
            $subpath = preg_replace(
                '&^/' . $this->org_folder . '/(.*?\.php).*$&',
                '\1',
                $_SERVER['PHP_SELF']
            );
            if (preg_match('&[^/]+/[^/]+/[^/]+&', $subpath)) {
                [$this->lang, $this->kategorie, $this->file] = explode(
                    "/",
                    $subpath,
                    3
                );
                if (strstr($this->file, '/'))
                    [$this->modul, $this->php_file] = explode("/", $this->file, 2);
            }
            $this->setLang();
        }
    }

    public function __get(string $name)
    {
        return $this->$name;
    }

    public static function autoCreate($dir)
    {
        $try = ['../../', '', '../', '../../../'];
        foreach ($try as $d) {
            if (is_readable($dir . '/' . $d . 'inc/localconfig.inc')) {
                $context = new BayCMSContext($dir . '/' . $d);
                $context->init();
                return $context;
            }
        }
        throw new \BayCMS\Exception\notFound('Did not find localconfig.inc');
    }

    public function t(string $en, ?string $de = null, bool $save = false): string
    {
        if ($save && !is_null($de))
            $this->tr[$en] = $de;
        if ($this->get('lang') == 'en')
            return $en;
        if (!is_null($de))
            return $de;
        return $this->tr[$en] ?? $en;
    }

    public function _404($msg = '')
    {
        $_SESSION['last_error_msg'] = $msg;
        header("Location: /" . $this->getOrgLinkLang() . "/top/gru/404.php");
        exit();
    }

    public function _401($msg = '')
    {
        $_SESSION['last_error_msg'] = $msg;
        header("Location: /" . $this->getOrgLinkLang() . "/top/gru/401.php");
        exit();
    }
    public function checkMinPower()
    {
        $res = pg_query(
            $this->conn_ro,
            "select case when a.min_power>k.min_power then a.min_power else k.min_power end,
          non_empty(non_empty(a." . $this->lang . ",a." . $this->lang2 . "),
          non_empty(k." . $this->lang . ",k." . $this->lang2 . ")) as kat,
          k.id 
          from kategorie k left outer
          join (select * from kat_aliases where id_lehr=" . $this->row1['id'] . ") a on k.id=a.id_kat 
          where k.link='" . $this->kategorie . "'"
        );
        if (pg_num_rows($res))
            [$this->kat_min_power, $this->header_kat, $this->kat_id] =
                pg_fetch_row($res, 0);

        if ($this->kat_min_power > $this->min_power)
            $this->min_power = $this->kat_min_power;
        if ($this->header_kat && $this->kategorie != "top")
            $this->header_kat = ": " . $this->header_kat;
        else
            $this->header_kat = '';
        if ($this->title_status == 1) {
            $this->title .= $this->header_kat;
            $this->title_status = 2;
        }
    }

    public function getPrePostHtml()
    {

        $res = pg_query($this->conn_ro, "select 
        non_empty(" . $this->getLangLang2("prefix_") . ") as prefix,
        non_empty(" . $this->getLangLang2('postfix_') . ") as postfix
        from pre_post_html where
        id_lehr=" . $this->row1['id'] . " and
        '" . pg_escape_string(
            $this->conn_ro,
            $this->kategorie . "/" . $this->modul . "/" . $this->php_file . ($_SERVER['QUERY_STRING'] ? "?$_SERVER[QUERY_STRING]" : "")
        ) . "' ilike replace(path,'_','\\_')||'%'
                order by path limit 1");

        if (pg_num_rows($res)) {
            [$this->page_prefix, $this->page_postfix] = pg_fetch_row($res, 0);
        }

    }

    public function setLang($lang = null)
    {

        if ($lang !== null) {
            $this->lang = $lang;
        } elseif (isset($_GET['lang'])) {
            $this->lang = $_GET['lang'];
        }
        if (!isset($this->lang))
            $this->lang = $this->row1['lang'] ?? 'de';
        if (!in_array($this->lang, ['de', 'en']))
            $this->lang = 'de';
        $this->lang2 = $this->lang == 'de' ? 'en' : 'de';
        $this->title = $this->getRow1String('title_');
        $this->title_status = 1;

    }

    public function get($key, $key2 = null, $default = null): mixed
    {

        if ($key2 !== null)
            return $this->$key[$key2] ?? $default;
        return $this->$key ?? $default;
    }

    public function set($key, $value)
    {
        $this->$key = $value;
    }

    public function getDbConn(): mixed
    {
        if ($this->conn_ro === false) {
            throw new \Exception('No database connection');
        }
        return $this->conn_ro;
    }
    public function getRwDbConn(): mixed
    {
        if ($this->conn_rw === null) {
            $this->conn_rw = pg_connect($this->DB_OWNER);
            pg_set_client_encoding($this->conn_rw, 'UTF-8');
        }
        if ($this->conn_rw === false) {
            throw new \Exception('No database connection');
        }
        return $this->conn_rw;
    }

    public function prepare(string $stmtname, string $query, bool $rw = true)
    {
        $res = pg_query_params(
            $rw ? $this->getRwDbConn() : $this->getDbConn(),
            'select name from pg_prepared_statements where name=$1',
            [$stmtname]
        );
        if (pg_num_rows($res))
            return true;
        return pg_prepare(
            $rw ? $this->getRwDbConn() : $this->getDbConn(),
            $stmtname,
            $query
        );
    }

    public function registerGlobal()
    {
        //trigger_error('Use of registerGlobal should be avoided for new scripts.');
        $GLOBALS['row1'] = $this->row1;
        $GLOBALS['lang'] = $this->lang;
        $GLOBALS['lang2'] = $this->lang2;
        $GLOBALS['ls_link'] = $this->org_folder;
        $GLOBALS['conn1'] = $this->conn_ro;
        $GLOBALS['SID'] = $this->SID;
        $GLOBALS['min_power'] = $this->min_power;
        $GLOBALS['kategorie'] = $this->kategorie;
        $GLOBALS['DOC_PFAD'] = $this->BayCMSRoot;
        $GLOBALS['SITE_ENCODING'] = 'utf8';


    }


    public function getIndexQueries(): array
    {
        $row1 = $this->row1;
        $lang = $this->lang;
        $lang2 = $this->lang2;
        $ls_link = $this->org_folder;
        if ($this->IP_AUTH_OK ?? false)
            $H_kat_query = "and
  (((
  (k.min_power<=$row1[ip_power] and (a.min_power is null or a.min_power<=$row1[ip_power]))
  or
  (k.min_power=0 and a.min_power>0 and a.min_power<=$row1[ip_power])))
  or ((k.min_power>$row1[ip_power] or a.min_power>$row1[ip_power])
  and hat_zugang($row1[id],$row1[id_benutzer],k.id))) ";
        elseif (($this->min_power ?? 0) && ($this->AUTH_OK ?? false))
            $H_kat_query = "and
  (((
  (k.min_power<=$row1[power] and k.min_power>0 and (a.min_power is null or a.min_power<=$row1[power]))
  or
  (k.min_power=0 and a.min_power>0 and a.min_power<=$row1[power])))
  or
  ((k.min_power>$row1[power] or a.min_power>$row1[power]) and hat_zugang($row1[id],$row1[id_benutzer],k.id))) ";
        else
            $H_kat_query = "and (k.min_power=0 and (a.min_power is null or a.min_power=0))";

        $H_kat_query = "select distinct on(k_ord) * from (select case when a.ordnung is null then k.ordnung else a.ordnung end as k_ord,
            non_empty(i.$lang,i.$lang2) as index_text,i.ordnung as index_ordnung,
            case when i.target_blank then ' target=\"_blank\"' end as target, i.id as id_index, k.id,
            k.link,non_empty(non_empty(a.$lang,a.$lang2),k.$lang) as text,f.name||i.qs as name,
            non_empty(non_empty(i.url_$lang,i.url_$lang2),'/$ls_link/$lang/'||f.name||i.qs) as url,
            a.exclude_from_top_navi
            from
            index_files i left outer join file f on f.id=i.id_file,kategorie k left outer join
            (select * from kat_aliases where id_lehr=$row1[id]) a on k.id=a.id_kat where i.id_lehr=$row1[id] and i.id_super=k.id and k.id!=1000 $H_kat_query ) a
            order by k_ord, index_ordnung desc, index_text";

        if ($this->IP_AUTH_OK ?? false)
            $H_kat_query_extern = $H_kat_query;
        else
            $H_kat_query_extern = "select distinct on(k_ord) * from (select case when a.ordnung is null then k.ordnung else a.ordnung end as k_ord,
                  non_empty(i.$lang,i.$lang2) as index_text,i.ordnung as index_ordnung,
                  case when i.target_blank then ' target=\"_blank\"' end as target, i.id as id_index, k.id,
                  k.link,non_empty(non_empty(a.$lang,a.$lang2),k.$lang) as text,f.name||i.qs as name,
                  non_empty(non_empty(i.url_$lang,i.url_$lang2),'/$ls_link/$lang/'||f.name||i.qs) as url,
                  a.exclude_from_top_navi
                  from
                  index_files i left outer join file f on f.id=i.id_file,kategorie k left outer join
                  (select * from kat_aliases where id_lehr=$row1[id]) a on k.id=a.id_kat where i.id_lehr=$row1[id] and i.id_super=k.id and k.id!=1000
                   and (k.min_power=0 and (a.min_power is null or a.min_power=0))) a
                  order by k_ord, index_ordnung desc, index_text";
        if ($this->AUTH_OK ?? false)
            $H_kat_query_intern = "select distinct on(k_ord) * from (select case when a.ordnung is null then k.ordnung else a.ordnung end as k_ord,
                  non_empty(i.$lang,i.$lang2) as index_text,i.ordnung as index_ordnung,
                  case when i.target_blank then ' target=\"_blank\"' end as target, i.id as id_index, k.id,
                  k.link,non_empty(non_empty(a.$lang,a.$lang2),k.$lang) as text,f.name||i.qs as name,
                  non_empty(non_empty(i.url_$lang,i.url_$lang2),'/$ls_link/$lang/'||f.name||i.qs) as url,
                  a.exclude_from_top_navi
                  from
                  index_files i left outer join file f on f.id=i.id_file,kategorie k left outer join
                  (select * from kat_aliases where id_lehr=$row1[id]) a on k.id=a.id_kat where i.id_lehr=$row1[id] and i.id_super=k.id and k.id!=1000
                   and
          (((
          (k.min_power<=$row1[power] and k.min_power>0 and (a.min_power is null or a.min_power<=$row1[power]))
          or
          (k.min_power=0 and a.min_power>0 and a.min_power<=$row1[power])))
          or ((k.min_power>$row1[power] or a.min_power>$row1[power])
          and hat_zugang($row1[id],$row1[id_benutzer],k.id)))) a
                  order by k_ord, index_ordnung desc, index_text";
        else
            $H_kat_query_intern = '';

        $this->H_kat_query = $H_kat_query;
        $this->H_kat_query_intern = $H_kat_query_intern;
        $this->H_kat_query_extern = $H_kat_query_extern;

        return [$H_kat_query, $H_kat_query_extern, $H_kat_query_intern];
    }

    public function setSystemUser($set = true)
    {
        if ($set) {
            $this->row1['id_user_orig'] = $this->row1['id_benutzer'] ?? null;
            $this->row1['id_benutzer'] = 5002;

        } else if ($this->row1['id_benutzer'] == 5002) {
            $this->row1['id_benutzer'] = $this->row1['id_user_orig'] ?? null;
        }

    }

    public function hasModule($module)
    {
        $res = pg_query_params(
            $this->getDbConn(),
            'select id from modul where uname=$1',
            [$module]
        );
        return pg_num_rows($res);
    }

    public function getModConfig($module, $unames)
    {
        if (!is_array($unames))
            $unames = [$unames];

        $v = [
            $this->row1['id'],
            $module
        ];
        $nr = 3;
        $query = '';
        foreach ($unames as $uname) {
            $query .= ' or mdc.uname ilike $' . $nr;
            $v[] = $uname;
            $nr++;
        }

        $res = pg_query_params(
            $this->getDbConn(),
            'select mdc.uname, case when mlc.value is null then mdc.value else mlc.value end as value
        from modul_default_config mdc left outer join 
        (select * from modul_ls_config where id_lehr=$1) mlc on mdc.id=mlc.id_modconfig
        where mdc.mod=$2 and (false ' . $query . ')',
            $v
        );
        $config = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);
            $config[$r[0]] = $r[1];
        }
        return $config;
    }


    public function checkObject(int $id)
    {
        $obj = new \BayCMS\Base\BayCMSObject($this);
        try {
            $obj->load($id);
        } catch (\Exception $e) {
            $this->_404($e->getMessage());
        }

        $obj->checkReadAccess(); //Will redirect, if object does not belong to unit.


        $this->ADDITIONAL_HTML_HEAD .= $obj->getHTMLHeader();
        $values = $obj->get();
        $title = $values[$this->lang ?? 'en'];
        if (!$title)
            $title = $values[$this->lang2 ?? 'de'];
        if(! $title) $title='';

        $dt = new \DateTime();
        $dt->setTimestamp($values['utime']);
        $this->last_modified = $dt->format("d.m.Y H:i");
        if ($this->title_status == 2) {
            $this->title .= ": " . $title;
            $this->title_status = 3;
        }
        $this->object_title = $title;
        $this->object_id = $id;
        return ': ' . $title;
    }

    public function checkHost()
    {
        if ($this->getMinPower() && isset($_SERVER['HTTPS'])) {
            $protokoll = "https";
            $httphost = $this->row1['httpshost'];
        } else {
            $protokoll = "http";
            $httphost = $this->row1['httphost'];
        }
        if ($httphost && $httphost != $_SERVER['HTTP_HOST'] && !isset($header_no_domain_redirect)) {
            header("Location: $protokoll://$httphost" . $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?$_SERVER[QUERY_STRING]" : ""));
            exit();
        }
    }

    public function getLoginLogoutLinks()
    {
        if ($this->get('AUTH_OK'))
            $H_LoginLogout = [
                'url' => "/" . $this->getOrgLinkLang() . "/intern/gru/index.php?aktion=logout",
                'text' => 'LOGOUT'
            ];
        else {
            $H_LoginLogout = [
                'url' => ($_SERVER['HTTP_HOST'] == "localhost" || (isset($NOSSL) && $NOSSL) ? "" :
                    "https://" . ($this->get('row1', 'httpshost') ? $this->get('row1', 'httpshost') :
                        "www.bayceer.uni-bayreuth.de")),
                'text' => 'LOGIN'
            ];

            $H_LoginLogout['url'] .= ($this->row1['loginlogout'] == 't' ?
                "/" . $this->getOrgLinkLang() . "/top/gru/login.php" :
                "/" . $this->getOrgLinkLang() . "/intern/gru/index.php?force_login=1");
        }
        $this->H_LoginLogout = $H_LoginLogout;
        return $H_LoginLogout;
    }


    public function init()
    {
        $this->checkMinPower();
        $this->checkHost();
        $auth = new \BayCMS\Base\BayCMSAuthenticator($this);
        $auth->authenticate();
        $this->no_frame = $_GET['no_frame'] ?? 0;
        if (in_array($_GET['js_select']??'', array('n', '1', 'tiny'))) {
            $this->no_frame = 1;
            $this->frameable = 1;
        }
    }


    public function initTemplate()
    {
        $class = '\\BayCMS\\Template\\';
        foreach (explode('.', $this->row1['style']??'') as $t) {
            $class .= ucfirst($t);
        }
        if (class_exists($class)) {
            $this->TE = new $class($this);
        } else {
            $this->TE = new \BayCMS\Template\Bootstrap($this);
        }
        $GLOBALS['TE'] = $this->TE;//for backward compatibility 
    }

    public function printHeader()
    {
        if ($this->header_printed)
            return;
        $this->getIndexQueries();
        $this->getLoginLogoutLinks();
        $this->getPrePostHtml();
        if ($this->getMinPower() < 500 && isset($_GET['id_obj']) && $_GET['id_obj'] > 5000) {
            $this->checkObject($_GET['id_obj']);
        }
        $this->initTemplate();
        $this->TE->printHeader();
        if (!$this->no_frame)
            echo $this->TE->htmlPostprocess($this->get('page_prefix', null, ''));
        $this->header_printed = true;

    }


    public function printFooter()
    {
        if (!$this->no_frame)
            echo $this->TE->htmlPostprocess($this->get('page_postfix', null, ''));
        $this->TE->printFooter();
        exit();
    }



    public function getRow1String($prefix)
    {
        $res = $this->get('row1', $prefix . $this->lang);
        if (!$res)
            $res = $this->get('row1', $prefix . $this->lang2);
        if (!$res)
            return '';
        return $res;
    }

    public function getLangLang2($prefix)
    {
        return $prefix . $this->lang . ',' . $prefix . $this->lang2;
    }

    public function getOrgLinkLang()
    {
        return $this->org_folder . '/' . $this->lang;
    }

    public function getOrgLogo()
    {
        if (!$this->get('row1', 'org_logo'))
            return '';
        return '/' . $this->org_folder . '/de/image/' . $this->get('row1', 'org_logo');
    }

    public function getOrgFavicon()
    {
        if (!$this->get('row1', 'org_favicon'))
            return '';
        return '/' . $this->org_folder . '/de/image/' . $this->get('row1', 'org_favicon');
    }

    public function getOrgId()
    {
        return $this->row1['id'];
    }

    public function getUserId()
    {
        return $this->row1['id_benutzer'] ?? 0;
    }

    public function getPower()
    {
        return max($this->row1['power'] ?? 0, $this->row1['ip_power'] ?? 0);
    }

    public function getMinPower()
    {
        return $this->min_power ?? 0;
    }

}

