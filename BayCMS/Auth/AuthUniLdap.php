<?php

namespace BayCMS\Auth;

class AuthUniLdap extends Auth
{
    public function createUser($user, $pw): bool|array
    {
        if(! $user) return false;
        if(! $pw) return false;
        
        $ds = ldap_connect("ldaps://btr0x65.rz-ad.uni-bayreuth.de:636");
        if (!$ds)
            return false;
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        $user_power = 0;
        $bind = 'student';
        if (preg_match('/^bt/i', $user))
            $bind = 'mitarbeiter';
        $res = ldap_bind($ds, "cn=$user,ou=users,ou=rz-ad,o=uni-bayreuth", $pw);
        if (!$res)
            return false;

        $auth_unildap_config = [];
        $res = pg_query(
            $this->context->getDbConn(),
            "select mdc.uname, non_empty(mlc.value,mdc.value) as value from modul_default_config mdc left outer join 
            (select * from modul_ls_config where id_lehr=" . $this->context->getOrgId() . ") mlc on mdc.id=mlc.id_modconfig where mdc.mod='auth_unildap'"
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);
            $auth_unildap_config[$r[0]] = $r[1];
        }
        $power_hash = array(10, 100, 500);
        $user_power = $power_hash[$auth_unildap_config['power' . ($bind == "student" ? '_stud' : '')]];

        if (!$user_power)
            return false;


        [$id_pw_source] = pg_fetch_row(pg_query(
            $this->context->getDbConn(),
            "select id from pw_source where name='Uni-LDAP'"
        ));
        if (!$id_pw_source)
            throw new \BayCMS\Exception\missingData('No pw_source entry for "Uni-LDAP"');

        $res = pg_query_params(
            $this->context->getDbConn(),
            'select id from benutzer where login=$1 and not gruppe',
            [$user]
        );
        if (pg_num_rows($res))
            [$id_benutzer] = pg_fetch_row($res, 0);
        else {
            $this->context->setSystemUser();
            $sr = ldap_search($ds, "ou=users,ou=rz-ad,o=uni-bayreuth", "cn=$user");
            $info = ldap_get_entries($ds, $sr);
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $email = $info[0]['mail'][0];
            $nname = $info[0]['sn'][0];
            $vname = $info[0]['givenname'][0];
            $name = $vname . ' ' . $nname;
            $obj->set(['uname' => "benutzer", 'id_parent' => $this->context->getOrgId(), 'de' => $name, 'en' => $name]);
            $id_benutzer = $obj->save();
            $this->context->setSystemUser(false);
            pg_query_params(
                $this->context->getRwDbConn(),
                'insert into benutzer(id,login,kommentar,email,pw,id_pw_source,gruppe) 
                values($1,$2,$3,$4,$5,$6,false)',
                [$id_benutzer, $user, $name, $email, 'xx' , $id_pw_source]
            );
        }
        //id_benutzer bekannt

        if (
            !pg_num_rows(pg_query_params(
                $this->context->getDbConn(),
                'select * from in_ls where id_lehr=$1 and id_benutzer=$2',
                [$this->context->getOrgId(), $id_benutzer]
            ))
        )
            pg_query_params(
                $this->context->getRwDbConn(),
                'insert into in_ls(id_benutzer,id_lehr,power) values ($2,$1,$3)',
                [$this->context->getOrgId(), $id_benutzer, $user_power]
            );

        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select b.*,il.power,false as update_lastlogin from benutzer b,in_ls il where 
                b.id=il.id_benutzer and b.id=$2 and il.id_lehr=$1',
            [$this->context->getOrgId(), $id_benutzer]
        );
        //$r gesetzt und $auth_fallback erfolgreich
        $r = pg_fetch_array($res, 0);
        return $r;


    }

    public function createAccess($user, $pw): array|bool
    {
        return $this->createUser($user, $pw);
    }
}