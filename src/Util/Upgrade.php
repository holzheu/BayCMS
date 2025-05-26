<?php

namespace BayCMS\Util;

class Upgrade extends \BayCMS\Base\BayCMSBase
{
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        $this->context = $context;
        if ($context->getPower() <= 1000) {
            throw new \BayCMS\Exception\accessDenied("Upgrade class is restricted to super users.");
        }
        $this->init();
    }

    private function init()
    {
        $f = [
            'sysconfigEnabledTemplates' => 'Sets a new sysconfig value dependent on templates used by installation',
            'templateChangeRow1Columns' => 'Create all necessary columns and rename to te_xxxx',
            'upgradeLogo' => 'Upgrade organization logos to new database layout',
            'disableSyncWithSource' => 'Disable PW-Hash Synchronization'
        ];
        $res = pg_query($this->context->getRwDbConn(), 'select function from baycms_upgrades');

        if (!$res) {
            pg_query(
                $this->context->getRwDbConn(),
                'create table baycms_upgrades (
id serial not null primary key,
function text not null unique,
description text,
executed timestamp,
user_id int
);'
            );
            $m = [];
            preg_match('/user=([a-z0-9_]+)/', $this->context->DB_EXTERN, $m);
            pg_query(
                $this->context->getRwDbConn(),
                "grant select on baycms_upgrades to " . $m[1]
            );
            $res = pg_query($this->context->getRwDbConn(), 'select function from baycms_upgrades');
        }
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$function] = pg_fetch_row($res, $i);
            unset($f[$function]);
        }
        pg_prepare($this->context->getRwDbConn(), 'insert', 'insert into baycms_upgrades(function,description) values ($1,$2)');
        foreach ($f as $function => $desc) {
            pg_execute($this->context->getRwDbConn(), 'insert', [$function, $desc]);
        }

    }

    private function sysconfigEnabledTemplates()
    {
        $out = "Set sysconfig EnabledTemplates\n\n";
        $res = pg_query($this->context->getRwDbConn(), 'select * from sysconfig where key=\'ENABLED_TEMPLATES\'');
        if (pg_num_rows($res))
            throw new \BayCMS\Exception\invalidData("Sysconfig variable is already present. Please change the value directly in the database");

        $res = pg_query($this->context->getDbConn(), 'select distinct style from lehrstuhl');
        $t = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$t[]] = pg_fetch_row($res, $i);
        }
        pg_query_params(
            $this->context->getRwDbConn(),
            'insert into sysconfig(key,value) values($1,$2)',
            ['ENABLED_TEMPLATES', implode(',', $t)]
        );
        return $out;
    }

    private function disableSyncWithSource(){
        $out = "Disable sync_with_source\n\n";
        pg_query($this->context->getRwDbConn(),
        'alter table benutzer alter column sync_with_source set default false');
        pg_query($this->context->getRwDbConn(),
        "update benutzer set sync_with_source=false,pw_md5='xx',pw='xx' where id_pw_source is not null"); 
        return $out;       

    }

    private function templateChangeRow1Columns()
    {
        $out = "Upgrade Template Row1\n\n";
        $res = pg_query($this->context->getDbConn(), 'select te_logolink from lehrstuhl');
        if ($res)
            throw new \BayCMS\Exception\invalidData("Template columns already present.");

        pg_query(
            $this->context->getRwDbConn(),
            "
update bild set de='og_preview_img',id_obj=o.id from objekt o where o.og_img=bild.id;
alter table lehrstuhl add column if not exists template_ubt2_logolink text;
alter table lehrstuhl add column if not exists template_ubt2_nobilang boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_nosearch boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_nointern boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_bayceermember boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_supbayceer boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_orggce boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_membergi boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_terminanzahl int not null default 3;
alter table lehrstuhl add column if not exists template_ubt2_toplink_oben boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_fgbio boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_fggeo boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_fak2 boolean not null default false;
alter table lehrstuhl add column if not exists template_ubt2_link_fak text;
alter table lehrstuhl add column if not exists template_ubt2_link_facebook text;
alter table lehrstuhl add column if not exists template_ubt2_link_instagram text;
alter table lehrstuhl add column if not exists template_ubt2_link_twitter text;
alter table lehrstuhl add column if not exists template_ubt2_link_bluesky text;
alter table lehrstuhl add column if not exists template_ubt2_link_youtube text;
alter table lehrstuhl add column if not exists template_ubt2_link_blog text;
alter table lehrstuhl add column if not exists template_ubt2_link_contact text;

alter table lehrstuhl rename template_ubt2_logolink to te_logolink;
alter table lehrstuhl rename template_ubt2_nobilang to te_nobilang;
alter table lehrstuhl rename template_ubt2_nosearch to te_nosearch;
alter table lehrstuhl rename template_ubt2_nointern to te_nointern;
alter table lehrstuhl rename template_ubt2_bayceermember to te_bayceermember;
alter table lehrstuhl rename template_ubt2_supbayceer to te_supbayceer;
alter table lehrstuhl rename template_ubt2_orggce to te_orggce;
alter table lehrstuhl rename template_ubt2_membergi to te_membergi;
alter table lehrstuhl rename template_ubt2_terminanzahl to te_terminanzahl;
alter table lehrstuhl rename template_ubt2_toplink_oben to te_toplink_oben;
alter table lehrstuhl rename template_ubt2_fgbio to te_fgbio;
alter table lehrstuhl rename template_ubt2_fggeo to te_fggeo;
alter table lehrstuhl rename template_ubt2_fak2 to te_fak2;
alter table lehrstuhl rename template_ubt2_link_fak to te_link_fak;
alter table lehrstuhl rename template_ubt2_link_facebook to te_link_facebook;
alter table lehrstuhl rename template_ubt2_link_instagram to te_link_instagram;
alter table lehrstuhl rename template_ubt2_link_twitter to te_link_twitter;
alter table lehrstuhl rename template_ubt2_link_bluesky to te_link_bluesky;
alter table lehrstuhl rename template_ubt2_link_youtube to te_link_youtube;
alter table lehrstuhl rename template_ubt2_link_blog to te_link_blog;
alter table lehrstuhl rename template_ubt2_link_contact to te_link_contact;

alter table lehrstuhl add column if not exists title_de text;
alter table lehrstuhl add column if not exists title_en text;
update lehrstuhl set title_de=de, title_en=en;"
        );
    }
    private function upgradeLogo()
    {
        $out = "Upgrade Logo\n\n";
        $res = pg_query($this->context->getDbConn(), 'select logo from lehrstuhl limit 1');
        if (!$res)
            throw new \BayCMS\Exception\invalidData("No logo column.");

        pg_prepare(
            $this->context->getDbConn(),
            'select_bild',
            'select de from bild where id_obj=$1 and de=\'org_logo\''
        );
        pg_prepare(
            $this->context->getRwDbConn(),
            'update_ls',
            'update lehrstuhl set logo=\'0\' where id=$1'
        );
        $res = pg_query(
            $this->context->getDbConn(),
            "select id,logo from lehrstuhl"
        );
        $img = new \BayCMS\Base\BayCMSImage($this->context);
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            $v = array();
            $res2 = pg_execute($this->context->getDbConn(), 'select_bild', [$r['id']]);
            for ($j = 0; $j < pg_num_rows($res2); $j++) {
                $r2 = pg_fetch_row($res2, $j);
                $v[$r2[0]] = 1;
            }
            if ($r['logo'] && !($v['org_logo'] ?? '')) {
                //Save logo
                $source = $this->context->BayCMSRoot . '/file/logo' . $r['id'] . '.' . $r['logo'];
                if (is_readable($source)) {
                    $img->set([
                        'id_obj' => $r['id'],
                        'de' => 'org_logo',
                        'source' => $source,
                        'name'=>'logo5001.'.$r['logo']
                    ]);
                    $img->setId(null);
                    $img->save();
                    $out .= "save $r[id].$r[logo]\n";
                }
                pg_execute($this->context->getRwDbConn(), 'update_ls', [$r['id']]);

            }


        }
        pg_query(
            $this->context->getRwDbConn(),
            'alter table lehrstuhl drop column logo;'
        );

        return $out;
    }

    public function run(string $function)
    {
        try {
            $out = $this->$function();
        } catch (\Exception $e) {
            $this->context->TE->printMessage($e->getMessage(), 'danger');
            return '';
        }
        pg_query_params(
            $this->context->getRwDbConn(),
            'update baycms_upgrades set executed=now(),user_id=$1 where function=$2',
            [$this->context->getUserId(), $function]
        );
        return $out;
    }
}