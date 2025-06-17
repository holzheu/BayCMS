<?php

namespace BayCMS\Base;

class BayCMSAuthenticator
{
    protected BayCMSContext $context;

    private $AUTH_OK = false;
    private $IP_AUTH_OK = false;

    public function __construct(BayCMSContext $context)
    {
        $this->context = $context;
    }

    public function authenticated()
    {
        return $this->AUTH_OK;
    }

    /**
     * Redirects to login page
     * @param mixed $login
     * @param mixed $logout
     * @return never
     */
    public function authPage($login = '', $logout = false)
    {
        $ls_link = $this->context->org_folder;
        if ($this->context->row1['loginlogout'] == "t")
            header("Location: /$ls_link/" . $this->context->lang . "/top/gru/login.php?login=$login&url=" .
                rawurlencode("$_SERVER[PHP_SELF]?" .
                    str_replace("aktion=", "aktion_disabled=", $_SERVER['QUERY_STRING'])) .
                '&logout=' . $logout);
        else {
            Header("HTTP/1.0 401 Unauthorized");
            Header("WWW-Authenticate: Basic realm=\"$ls_link\"");
        }
        echo "<head>
  <meta http-equiv=\"refresh\" content=\"0; URL=http://$_SERVER[HTTP_HOST]/$ls_link/\">
  </head>
  Access denied\n";
        exit();

    }

    private function cleanUpSession($s_id, $id_org = 0)
    {

        if ($id_org)
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from session where id_session=$1 and id_lehr=$2',
                [$s_id, $id_org]
            );
        else
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from session where id_session=$1',
                [$s_id]
            );

        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_session from session where id_session=$1',
            [$s_id]
        );
        if (!pg_num_rows($res)) {
            foreach (glob($this->context->BayCMSRoot . '/tmp/' . $s_id . '*') as $f) {
                unlink($f);
            }
        }


    }

    public function logout()
    {

        $this->cleanUpSession(session_id());
        $this->AUTH_OK = false;
        unset($_SESSION['no_ipauth']);
        $this->authPage(logout: true);

    }

    public function authenticateBySession()
    {
        //Session from requested organiszation
        $row1 = $this->context->row1;
        $res = pg_query_params(
            $this->context->getDbConn(),
            "select b.*,s.*,s.timeout<(now()+interval '30 minutes') as update_session
  		from session s, benutzer b where s.id_session=\$1 and
  		s.id_benutzer=b.id and s.id_lehr=\$2 and (s.power>=\$3
  		or hat_zugang(\$2,b.id,\$4))",
            [
                session_id(),
                $row1['id'],
                $this->context->getMinPower(),
                $this->context->kat_id
            ]
        );
        if (pg_num_rows($res)) {
            $r = pg_fetch_array($res, 0);
            if ($r['update_session'] == 't') {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'update session set timeout=now()+interval \'1 hour\' where id_session=$1 and id_lehr=$2',
                    [session_id(), $row1['id']]
                );
            }
            $this->AUTH_OK = true;
            $_SESSION['no_ipauth'] = 1;
            $row1['id_benutzer'] = $r['id'];
            $row1['power'] = $r['power'];
            $row1['kommentar'] = $r['kommentar'];
            $row1['user_email'] = $r['email'];
            $row1['login'] = $r['login'];
            $this->context->set('row1', $row1);
            $this->context->set('AUTH_OK', true);
            return;
        }

        //No valid session found - try sessions from other organisations
        $res = pg_query_params(
            $this->context->getDbConn(),
            "select b.*
		from session s, benutzer b where s.id_session=\$1 and
		s.id_benutzer=b.id",
            [session_id()]
        );
        if (!pg_num_rows($res))
            return; //No session


        $r = pg_fetch_array($res, 0);
        $res = pg_query($this->context->getDbConn(), "select b.*,
            il.last_login is null or il.last_login<(now()-interval '0.5 day') as update_lastlogin,
			il.id_benutzer as id_eff 
            from
			(select *,
            get_max_login_power(id,$row1[id]) as power,
            hat_zugang($row1[id],id," . $this->context->kat_id . ")
			from benutzer where id=$r[id]) b, in_ls il
			where il.power=b.power and 
            (il.id_lehr=$row1[id] and in_gruppe(b.id,il.id_benutzer)) or il.power>=10000");
        if (pg_num_rows($res))
            $r = pg_fetch_array($res, 0);
        else
            $r = ['hat_zugang' => 'f', 'power' => 0];

        if (!$r['power'])
            return;
        if ($r['power'] < $this->context->getMinPower() && $r['hat_zugang'] != "t")
            return;


        //Zugang wird gewährt
        pg_query($this->context->getRwDbConn(), "begin");
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_session,id_lehr from session where timeout<now()
					or (id_session=$1 and id_lehr=$2)',
            [session_id(), $row1['id']]
        );

        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$s_id, $id_org] = pg_fetch_row($res, $i);
            $this->cleanUpSession($s_id, $id_org);
        }
        pg_query_params(
            $this->context->getRwDbConn(),
            'insert into session(id_session,id_benutzer,id_lehr,power,login,pw,timeout)
						values ($1,$2,$3,$4,$5,$6,now()+interval \'1 hour\')',
            [session_id(), $r['id'], $row1['id'], $r['power'], $r['login'], $r['pw']]
        );
        pg_query($this->context->getRwDbConn(), "commit");
        $_SESSION['no_ipauth'] = 1;
        if ($r['update_lastlogin'] != "f") {
            pg_query($this->context->getRwDbConn(), "update in_ls set last_login=now() where
						(id_benutzer=" . $r['id'] . " or id_benutzer=" . $r['id_eff'] . ") and id_lehr=" . $row1['id']);
        }
        $this->AUTH_OK = true;
        $row1['id_benutzer'] = $r['id'];
        $row1['power'] = $r['power'];
        $row1['kommentar'] = $r['kommentar'];
        $row1['user_email'] = $r['email'];
        $row1['login'] = $r['login'];
        $this->context->set('row1', $row1);
        $this->context->set('AUTH_OK', true);
        return;


    }

    public function authenticateByIP()
    {
        $row1 = $this->context->row1;
        if (($row1['ip_auth'] ?? 'f') != 't')
            return;
        if ($_SESSION['no_ipauth'] ?? false)
            return;
        if (isset($_SERVER['PHP_AUTH_USER']) || isset($_POST['PHP_AUTH_USER']) || isset($_POST['php_auth_user']))
            return;

        if ($this->context->kat_id)
            $H_query = "hat_zugang($row1[id],b.id," . $this->context->kat_id . ")";
        else
            $H_query = "false as hat_zugang";
        $res = pg_query(
            $this->context->getDbConn(),
            "select b.*,$H_query,get_max_login_power(b.id,$row1[id]) as power from benutzer b,
      		benutzer_ip bi where b.id=bi.id_benutzer and '$_SERVER[REMOTE_ADDR]'<<=bi.ip
      		and (get_max_login_power(b.id,$row1[id])>0 or b.id in 
            (select id_benutzer from hat_zugang where id_lehr=$row1[id])) 
            order by power desc"
        );
        if (pg_num_rows($res)) {
            $r = pg_fetch_array($res, 0);
            $row1['id_benutzer'] = $r['id'];
            $row1['ip_power'] = $r['power'];
            $row1['power'] = 0;
            $row1['kommentar'] = $r['kommentar'];
            $row1['user_email'] = $r['email'];
            $row1['hat_zugang'] = $r['hat_zugang'];
            $this->context->set('row1', $row1);
            $this->IP_AUTH_OK = true;
            $this->context->set('IP_AUTH_OK', true);
        }


    }

    public function authenticateByUserPW($PHP_AUTH_USER, $PHP_AUTH_PW)
    {
        $ok = 0;
        $min_power = $this->context->getMinPower() ?? 0;

        $this->AUTH_OK = false;
        $PHP_AUTH_USER = strtolower(trim($PHP_AUTH_USER));
        $row1 = $this->context->row1;

        if (!$PHP_AUTH_USER)
            return;
        if (!$PHP_AUTH_PW)
            return;

        $res = pg_query_params(
            $this->context->getDbConn(),
            'select b.*,
            case when length(b.pw)>0 then pg_crypt($1,substr(b.pw,1,2)) end as pg_crypt,
            pw.bind_url,
            md5($1||b.salt) 
		    from benutzer b
				left outer join pw_source pw on pw.id=b.id_pw_source 
				where b.login=$2 and not b.gruppe',
            [$PHP_AUTH_PW, $PHP_AUTH_USER]
        );
        if ($ok = pg_num_rows($res))
            $r = pg_fetch_array($res, 0);//Benutzer bekannt
        if ($ok && !$r['pw_md5'] && $r['pw'] && $r['pw'] == $r['pg_crypt']) {
            pg_query_params(
                $this->context->getRwDbConn(),
                'update benutzer set pw_md5=$1,pw=\'\' where id=$2',
                [$r['md5'], $r['id']]
            );
            $r['pw_md5'] = $r['md5'];
        }
        //Password source
        if ($ok && $r['pw_md5'] != $r['md5']) {//PW falsch
            $ok = 0;
            //setzt $ok auf 1 falls erfolgreich
            if ($r['bind_url']) {
                if (preg_match('|^http://([^/]+)(/.*$)|', $r['bind_url'], $match)) {

                    if ($fp = fsockopen($match[1], 80)) {
                        fputs($fp, "GET $match[2]  HTTP/1.1\r\n");
                        fputs($fp, "Host: $match[1]\r\n");
                        fputs($fp, "Authorization: Basic " . base64_encode("$PHP_AUTH_USER:$PHP_AUTH_PW") . "\r\n");
                        fputs($fp, "Connection: close\r\n\r\n");
                        if (strstr(fgets($fp, 128), "AUTHORIZATION OK"))
                            $ok = 1;
                    }
                    fclose($fp);
                }

                // ldap://someserver.org/ou=People,dc=someserver,dc=org
                if ($PHP_AUTH_PW && preg_match('|^ldap://([^/]+)/(.*)$|', $r['bind_url'], $match)) {
                    if ($ds = ldap_connect($match[1], 389)) {
                        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                        if (ldap_bind($ds, "uid=$PHP_AUTH_USER,$match[2]", $PHP_AUTH_PW))
                            $ok = 1;
                    }

                }
                // ldaps://someserver.org/ou=People,dc=someserver,dc=org
                if ($PHP_AUTH_PW && preg_match('|(^ldaps://[^/]+)/(.*)$|', $r['bind_url'], $match)) {
                    if ($ds = ldap_connect($match[1])) {
                        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                        if (ldap_bind($ds, "cn=$PHP_AUTH_USER,$match[2]", $PHP_AUTH_PW))
                            $ok = 1;
                    }

                }

            }
            if ($ok && $r['sync_with_source'] == "t") {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'update benutzer set pw_md5=$1 where id=$2',
                    [$r['md5'], $r['id']]
                );
            }

        }
        //Login/PW-Kombination nicht korrekt
        if (!$ok) {
            $auth_fallback_ok = 0;
            $res = pg_query_params(
                $this->context->getDbConn(),
                "select class from auth_fallback 
					where id_lehr=\$1 and typ='benutzer'",
                [$this->context->getOrgId()]
            );
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                list($class) = pg_fetch_row($res, $i);
                if (!$class)
                    continue;
                //TODO: Remove this hack!!
                if (strstr($class, 'BayDOC\\')) {
                    require_once 'BayDOC/BayDOC.php';
                }
                try {
                    $auth_fallback = new $class($this->context);
                } catch (\Exception $e) {
                    trigger_error($e->getMessage());
                    continue;
                }
                $r = $auth_fallback->createUser($PHP_AUTH_USER, $PHP_AUTH_PW);
                if ($r !== false) {
                    $ok = 1;
                    break;
                }
            }
        }

        if (!$ok) {
            //password is wrong, fallback failed
            sleep(3);
            return;
        }

        //Authentification OK -> check access
        $res = pg_query(
            $this->context->getDbConn(),
            "select b.*,
            il.last_login is null or il.last_login<(now()-interval '0.5 day') as update_lastlogin,
			il.id_benutzer as id_eff from
			(select *,get_max_login_power(id,$row1[id]) as power,hat_zugang($row1[id],id," . $this->context->kat_id . ")
			from benutzer where id=$r[id]) b,in_ls il
			where il.power=b.power and (il.id_lehr=$row1[id] and in_gruppe(b.id,il.id_benutzer)) 
            or (il.power>=10000 and (il.bis is null or il.bis<=now()::date))"
        );
        if (pg_num_rows($res))
            $r = pg_fetch_array($res, 0);
        else
            $r = ['power' => 0, 'hat_zugang' => 'f'];

        $access = ($r['power'] >= $min_power || $r['hat_zugang'] == 't');

        if (!$access) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                "select class from auth_fallback 
                        where id_lehr=\$1 and typ='zugang'",
                [$this->context->getOrgId()]
            );
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                list($class) = pg_fetch_row($res, $i);
                if (!$class)
                    continue;
                $auth_fallback = new $class($this->context);
                $r = $auth_fallback->createAccess($PHP_AUTH_USER, $PHP_AUTH_PW);
                if ($r !== false) {
                    $access = true;
                    break;
                }
            }
        }

        if (!$access) {
            return; //No access
        }

        //Delete old tmp files...
        $files = glob($this->context->BayCMSRoot . '/tmp/*');
        $threshold = strtotime('-2 hours');
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($threshold >= filemtime($file)) {
                    unlink($file);
                }
            }
        }

        pg_query($this->context->getRwDbConn(), "begin");
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_session,id_lehr from session where timeout<now() or (id_session=$1 and id_lehr=$2)',
            [session_id(), $row1['id']]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$s_id, $id_org] = pg_fetch_row($res, $i);
            $this->cleanUpSession($s_id, $id_org);
        }
        pg_query_params(
            $this->context->getRwDbConn(),
            'insert into session(id_session,id_benutzer,id_lehr,power,login,pw,timeout) 
						values ($1,$2,$3,$4,$5,$6,now()+interval \'1 hour\')',
            [session_id(), $r['id'], $row1['id'], $r['power'], $r['login'], $r['pw']]
        );
        pg_query($this->context->getRwDbConn(), "commit");
        $_SESSION['no_ipauth'] = 1;
        if ($r['update_lastlogin'] != "f") {
            pg_query(
                $this->context->getRwDbConn(),
                "update in_ls set last_login=now() where 
							(id_benutzer=" . $r['id'] . " or id_benutzer=" . $r['id_eff'] . ") 
                            and id_lehr=" . $row1['id']
            );
        }
        $this->AUTH_OK = true;
        $row1['id_benutzer'] = $r['id'];
        $row1['power'] = $r['power'];
        $row1['kommentar'] = $r['kommentar'];
        $row1['user_email'] = $r['email'];
        $this->context->set('row1', $row1);
        $this->context->set('AUTH_OK', true);


    }


    public function authenticate()
    {
        if (($_GET['aktion'] ?? false) == "logout") {
            unset($_GET['aktion']);
            $this->logout();
        }
        $this->authenticateBySession();
        $this->authenticateByIP();
        if (isset($_SERVER['PHP_AUTH_USER']))
            $this->authenticateByUserPW($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if (isset($_POST['PHP_AUTH_USER']))
            $this->authenticateByUserPW($_POST['PHP_AUTH_USER'], $_POST['PHP_AUTH_PW']);
        if (isset($_POST['php_auth_user']))
            $this->authenticateByUserPW($_POST['php_auth_user'], $_POST['php_auth_pw']);
        if ($this->context->getMinPower() > $this->context->getPower())
            $this->authPage($_POST['php_auth_user'] ?? '');

    }
}