<?php

namespace BayCMS\Page\Admin;

use BayCMS\Page\Page;
use PhpOffice\PhpSpreadsheet\Writer\Ods\Content;
use Rector\DeadCode\NodeAnalyzer\IsClassMethodUsedAnalyzer;

class Modul extends Page
{
    private ?int $id;
    private array $row = [];
    private array $json;
    private string $tarfile = '';
    private \BayCMS\Fieldset\Domain $domain;

    private string $server = BayCMSUpdateServer;

    public function __construct(\BayCMS\Base\BayCMSContext $context, ?int $id = null)
    {
        parent::__construct($context);
        $this->id = $id;
        $this->load();

        $res = pg_query($this->context->getRwDbConn(), "select value from sysconfig where key='UPDATE_SERVER'");
        if (pg_num_rows($res))
            [$this->server] = pg_fetch_row($res, 0);

        $this->domain = new \BayCMS\Fieldset\Domain(
            $context,
            'modul',
            'Module',
            uname: 'modul',
            write_access_query: ($context->getPower() > 1000 ? 'true' : 'false'),
            from: 'modul t, objekt o',
            where: 't.id=o.id'
        );
        $this->domain->setListProperties(
            step: -1,
            new_button: $this->context->getPower() > 1000,
            actions: []
        );
        $this->domain->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'name',
            'Name',
            non_empty: 1
        ), list_field: 1, search_field: 1);
        $this->domain->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'uname',
            'UID',
            non_empty: 1
        ), list_field: 1);
        $this->domain->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'kurz',
            'Version',
            non_empty: 1
        ), list_field: 1);

        $this->domain->addField(new \BayCMS\Field\Hidden(
            $this->context,
            'id_lehr',
            type: 'int',
            default_value: $context->getOrgId()
        ));
        $this->domain->addField(new \BayCMS\Field\Textarea(
            $this->context,
            'beschreibung',
            'Beschreibung',
            non_empty: 1
        ));
        $this->domain->addField(new \BayCMS\Field\Textarea(
            $this->context,
            'create_sql',
            'Create SQL',
            not_in_table: 1
        ));
        $this->domain->addField(new \BayCMS\Field\Textarea(
            $this->context,
            'change_sql',
            'Change SQL',
            not_in_table: 1
        ));
        $this->domain->addField(new \BayCMS\Field\Textarea(
            $this->context,
            'delete_sql',
            'Delete SQL',
            not_in_table: 1
        ));
    }

    private function load()
    {
        if ($this->id === null)
            return;
        $res = pg_query($this->context->getDbConn(), 'select * from modul where id=' . $this->id);
        $this->row = pg_fetch_assoc($res, 0);
    }

    public function mktar_v1()
    {
        $SITE_ENCODING = 'UTF-8';
        $DOC_PFAD = $this->context->BayCMSRoot;
        $id_mod = $this->id;
        $TAR_BIN = "/bin/tar";

        $file = tempnam("/tmp", "baycms.modultar.");
        $path = $file . ".modul";

        mkdir($path, 0700);
        //-----Grunddaten----------------------
        $result = pg_query($this->context->getDbConn(), "select * from modul where id=$id_mod");
        $row = pg_fetch_array($result, 0);
        $tar = "/$path/$row[uname]$row[kurz].tar";
        $fname = "$row[uname]$row[kurz].tar";

        $fid = fopen("$path/modul", "w");
        fputs($fid, "$row[id]|||$row[id]|||$row[name]|||$row[status]|||$row[kurz]|||$row[beschreibung]|||" .
            "$row[uname]|||$DOC_PFAD|||$row[create_sql]|||TAR_V1|||$SITE_ENCODING" .
            "|||$row[change_sql]|||$row[delete_sql]");
        fclose($fid);
        exec("$TAR_BIN -cf $tar $path/modul");

        //-----Abhängigkeiten----------------------------
        $fid = fopen("$path/modul_dep", "w");
        $result = pg_query($this->context->getDbConn(), "select a.uname,b.min_version from modul a, modul_dep b  where b.id_mod=$id_mod and b.id_needs=a.id");
        $num = pg_num_rows($result);
        for ($i = 0; $i < $num; $i++) {
            $row = pg_fetch_array($result, $i);
            fputs($fid, "$row[uname]|||$row[min_version]\n");
        }
        fclose($fid);
        exec("$TAR_BIN -rf $tar $path/modul_dep");

        //----------------------------------------
        $fid = fopen("$path/modul_art_objekt", "w");

        $result = pg_query($this->context->getDbConn(), "select * from art_objekt where id_mod=$id_mod");
        $num = pg_num_rows($result);
        for ($i = 0; $i < $num; $i++) {
            $row = pg_fetch_array($result, $i);
            if ($row['view_file'])
                $view_file[$row['view_file']] = $row['id'];
            if ($row['edit_file'])
                $edit_file[$row['edit_file']] = $row['id'];
            fputs($fid, "$row[id]|||$row[uname]|||$row[id_mod]|||$row[view_file]|||$row[edit_file]|||$row[de]|||$row[en]|||$row[min_power]\n----------\n");
        }
        fclose($fid);
        exec("$TAR_BIN -rf $tar $path/modul_art_objekt");
        //----------------------------------------
        $fid = fopen("$path/modul_file", "w");

        //Lese Modul-Dateien
        $trans = get_html_translation_table(HTML_ENTITIES);
        $trans = array_flip($trans);

        $result = pg_query($this->context->getDbConn(), "select a.*,b.utime,extract(EPOCH from b.utime)
			from file a, objekt b where b.id_obj=$id_mod and b.id=a.id");
        $num = pg_num_rows($result);
        for ($i = 0; $i < $num; $i++) {
            $row = pg_fetch_array($result, $i);
            if (filemtime("$DOC_PFAD/$row[name]") > ($row['extract'] ?? 0))
                $row['utime'] = date("Y-m-d H:i:s", filemtime("$DOC_PFAD/$row[name]"));
            $file_id = $row['id'];
            $row['de'] = strtr($row['de'], $trans);
            $row['en'] = strtr($row['en'], $trans);
            $row['beschreibung'] = strtr($row['beschreibung'], $trans);
            exec("$TAR_BIN -rhf $tar $DOC_PFAD/$row[name]");
            fputs($fid, "$file_id|||$id_mod|||$row[id_obj]|||$row[name]|||$row[id_kat]|||$row[de]|||$row[en]|||$row[beschreibung]|||$row[index_file]|||" .
                ($view_file[$file_id] ?? '') . "|||" . ($edit_file[$file_id] ?? '') . "|||$row[utime]");
            fputs($fid, "\n----------\n");
        }
        fclose($fid);
        //----------------------------------------
        exec("$TAR_BIN -rf $tar $path/modul_file");


        header('Content-Description: $fname');
        header("Content-type: application/octet-stream");
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        //header('Pragma: public');
        //header('Accept-Ranges: bytes');
        header("Content-Length: " . filesize($tar));
        header("Content-disposition: inline; filename=$fname"); // prompt to save to disk
        ob_clean();
        flush();
        readfile($tar);


        exec("rm -R $path");
        unlink($tar);
        exit();
    }

    public function mktar()
    {
        if ($this->id === null)
            throw new \BayCMS\Exception\missingId('id is null');

        $tar = '/bin/tar';
        if (!is_executable($tar))
            throw new \BayCMS\Exception\invalidData('/bin/tar is not executable');
        $json = [];


        $r_mod = $this->row;
        $json['modul'] = $r_mod;
        $json['art_objekt'] = [];
        $json['modul_dep'] = [];
        $json['files'] = [];

        $res = pg_query_params(
            $this->context->getDbConn(),
            "select a.uname,b.min_version from modul a, modul_dep b  where b.id_mod=\$1 and b.id_needs=a.id",
            [$this->id]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_assoc($res, $i);
            $json['modul_dep'][$r['uname']] = $r['min_version'];
        }

        $res = pg_query_params(
            $this->context->getDbConn(),
            "select * from art_objekt where id_mod=\$1",
            [$this->id]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_assoc($res, $i);
            $json['art_objekt'][$r['uname']] = $r;
        }
        $res = pg_query_params(
            $this->context->getDbConn(),
            "select a.*,b.utime
			from file a, objekt b where b.id_obj=\$1 and b.id=a.id",
            [$this->id]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_assoc($res, $i);
            $r['filetime'] = filemtime($this->context->BayCMSRoot . "/$r[name]");
            $json['files'][$r['name']] = $r;
        }

        $file = tempnam(sys_get_temp_dir(), 'modtar');
        $folder = $file . ".modul";
        mkdir($folder);

        $fp = fopen($folder . '/modul.json', 'w');
        fputs($fp, json_encode($json));
        fclose($fp);
        chdir($folder);
        exec("$tar -cf $file modul.json");

        chdir($this->context->BayCMSRoot);
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_assoc($res, $i);
            exec("$tar -rhf $file $r[name]");
        }

        $fname = "$r_mod[uname]$r_mod[kurz].tar";

        header("Content-Description: $fname");
        header("Content-type: application/octet-stream");
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        //header('Pragma: public');
        //header('Accept-Ranges: bytes');
        header("Content-Length: " . filesize($file));
        header("Content-disposition: inline; filename=$fname"); // prompt to save to disk
        ob_clean();
        flush();
        readfile($file);
        exec("rm -R $folder");
        unlink($file);
        exit();
    }

    private function getDBExtern()
    {
        if (!preg_match('/user=(\\w+)/', $this->context->DB_EXTERN, $m))
            throw new \BayCMS\Exception\missingData("No db_extern user found");
        return $m[1];
    }

    public function parseChangeSQL(string $sql)
    {
        if (!$sql)
            return [];
        $db_extern = $this->getDBExtern();
        $comment = false;
        $i = 0;
        $pc = '';
        $res = [];
        $chars = str_split($sql);
        foreach ($chars as $c) {
            switch ($c) {
                case '/':
                    if ($pc == '*') {
                        $comment = false;
                    }
                    break;
                case '*':
                    if ($pc == '/') {
                        $comment = true;
                        $i++;
                    }
                    break;
            }
            $pc = $c;
            if (!isset($res[$comment][$i]))
                $res[$comment][$i] = '';
            $res[$comment][$i] .= $c;
        }
        $version = [];
        foreach ($res[1] as $i => $s) {
            if (preg_match("/change.*([0-9]+)\\.([0-9]+)/", $s, $m)) {
                $version[$m[1] . '.' . $m[2]] = $i;
            }
        }
        foreach ($version as $v => $i) {
            if (!isset($res[0][$i])) {
                unset($version[$v]);
                continue;
            }
            $sql = $res[0][$i];
            $sql = preg_replace('|^/|', '', $sql);
            $sql = preg_replace('|/$|', '', $sql);
            $sql = str_replace('$DB$_extern', $db_extern, $sql);
            $version[$v] = $sql;
        }
        return $version;
    }

    private function checkDependency($echo = false)
    {
        $this->json['modul_dep_check'] = [];
        $this->context->prepare(
            'check_mod',
            'select id, get_order_number(kurz) as v_inst_num, get_order_number($1) as v_json_num, kurz as v_inst, $1 as v_json
        from modul where uname=$2',
            false
        );
        foreach ($this->json['modul_dep'] as $mod => $min_version) {
            $res = pg_execute($this->context->getDbConn(), 'check_mod', [$min_version, $mod]);
            if (!pg_num_rows($res))
                throw new \BayCMS\Exception\missingData("Modul $mod not found. Please install the modul first.");
            $r = pg_fetch_assoc($res, 0);
            $this->json['modul_dep_check'][$mod] = $r;
            if ($echo)
                $this->context->TE->printMessage(
                    "Modul $mod required: $r[v_json] - installed: $r[v_inst]",
                    $r['v_inst_num'] < $r['v_json_num'] ? 'warning' : 'notice',
                    inline: true
                );
        }
    }

    private function checkObjectTypes()
    {
        if ($this->id === null)
            return;
        $this->context->prepare(
            'check_object_type',
            'select id from art_objekt where uname=$1',
            false
        );
        foreach ($this->json['art_objekt'] as $uname => $r_json) {
            $res = pg_execute($this->context->getDbConn(), 'check_object_type', [$uname]);
            $this->json['art_objekt'][$uname]['update'] = pg_num_rows($res);
        }
    }

    private function checkFiles()
    {
        if ($this->id === null)
            return;
        $res = pg_query_params(
            $this->context->getDbConn(),
            "select a.*,b.utime
			from file a, objekt b where b.id_obj=\$1 and b.id=a.id",
            [$this->id]
        );
        $this->json['del_file'] = [];
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_assoc($res, $i);
            if (!isset($this->json['files'][$r['name']])) {
                $this->json['del_file'][] = $r;
            } else {
                $r['filetime'] = filemtime($this->context->BayCMSRoot . "/$r[name]");
                $this->json['files'][$r['name']]['r'] = $r;
            }
        }
    }



    public function save(bool $echo = false, bool $all_files = false)
    {
        if ($this->context->getPower() <= 1000)
            return;
        $this->readJson();
        $this->checkDependency($echo);
        $this->checkObjectTypes();
        $this->checkFiles();

        $create = false;
        if ($echo)
            $this->context->TE->printMessage("Modul-Eintrag", 'notice');
        $r = $this->json['modul'];
        if ($this->id === null) {
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $obj->set(uname: 'modul', de: $r['name']);
            $this->id = $obj->save();
            pg_query_params(
                $this->context->getRwDbConn(),
                'insert into modul (id, id_lehr, name, kurz, beschreibung, uname, create_sql, delete_sql, change_sql)
            values ($1, $2, $3, $4, $5, $6, $7, $8, $9)',
                [$this->id, $this->context->getOrgId(), $r['name'], $r['kurz'], $r['beschreibung'], $r['uname'], $r['create_sql'], $r['delete_sql'], $r['change_sql']]
            );
            $this->row = $r;
            $create = true;
            pg_query($this->context->getRwDbConn(), 'commit');
        } else {
            if ($this->row['uname'] != $r['uname']) {
                throw new \BayCMS\Exception\invalidData('Wrong tar-file for update!');
            }
            pg_query_params(
                $this->context->getRwDbConn(),
                'update modul set id_lehr=$2, name=$3, kurz=$4, beschreibung=$5, uname=$6, create_sql=$7, delete_sql=$8, change_sql=$9
            where id=$1',
                [$this->id, $this->context->getOrgId(), $r['name'], $r['kurz'], $r['beschreibung'], $r['uname'], $r['create_sql'], $r['delete_sql'], $r['change_sql']]
            );
        }




        //save files        
        if ($echo)
            $this->context->TE->printMessage("Dateien", 'notice');
        $f_map = []; //Map id_source -> id_target
        foreach ($this->json['files'] as $f => $r) {
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            if (isset($r['r'])) {
                $f_map[$r['id']] = $r['r']['id'];
                if ($r['filetime'] < $r['r']['filetime'] && !$all_files)
                    continue;
                $file->load($r['r']['id']);
            }

            $source = tempnam($this->context->BayCMSRoot . "/tmp", 'extar');

            exec("/bin/tar -xOf " . $this->tarfile . " $f > $source");
            preg_match('|(.*)/([^/]+)$|', $f, $matches);
            $name = $matches[2];
            $path = $matches[1];
            $file->set(
                id_parent: $this->id,
                de: $r['de'],
                en: $r['en'],
                description: $r['beschreibung'],
                add_id_obj: false,
                source: $source,
                path: $path,
                name: $name
            );
            try {
                $f_id = $file->save();
                if ($r['index_file'] == 't')
                    pg_query_params($this->context->getRwDbConn(), 'update file set index_file=true where id=$1', [$f_id]);
                $f_map[$r['id']] = $f_id;
                if ($echo)
                    $this->context->TE->printMessage("Updated/saved $f", inline: true);
            } catch (\Exception $e) {
                $this->context->TE->printMessage("update/save from $f failed: " . $e->getMessage(), 'danger', inline: true);
            }

        }
        foreach ($this->json['del_file'] as $r) {
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            $file->load($r['id']);
            $f = $file->get();
            try {
                pg_query_params($this->context->getRwDbConn(),'delete from index_files where id_file=$1',[$r['id']]);
                $file->erase(true);
                if ($echo)
                    $this->context->TE->printMessage("deleted $f[name]", inline: true);

            } catch (\Exception $e) {
                $this->context->TE->printMessage("Failed to delete $f[name]:", 'danger', $e->getMessage(), inline: true);
            }


        }


        //Mod-Dep.
        if ($echo)
            $this->context->TE->printMessage("Abhängigkeiten", 'notice');

        pg_query_params($this->context->getRwDbConn(), 'delete from modul_dep where id_mod=$1', [$this->id]);
        $this->context->prepare(
            'insert_dep',
            'insert into modul_dep(id_mod, id_needs, min_version) values ($1, $2, $3)'
        );
        foreach ($this->json['modul_dep_check'] as $r) {
            pg_execute($this->context->getRwDbConn(), 'insert_dep', [$this->id, $r['id'], $r['v_json']]);
        }

        //Change-SQL
        if ($echo)
            $this->context->TE->printMessage("SQL", 'notice');
        if ($create) {
            try {
                $res = pg_query(
                    $this->context->getRwDbConn(),
                    str_replace('$DB$_extern', $this->getDBExtern(), $this->row['create_sql'])
                );
                if ($res) {
                    if ($echo)
                        $this->context->TE->printMessage('Create SQL successfull', inline: true);
                } else
                    $this->context->TE->printMessage('Create SQL failed', 'danger', pg_last_error($this->context->getRwDbConn()), true);
            } catch (\Exception $e) {
                $this->context->TE->printMessage('Create SQL failed', 'danger', $e->getMessage(), true);
            }
        } else {
            $change_sql = $this->parseChangeSQL($this->json['modul']['change_sql'] ?? '');
            $v_inst = explode('.', $this->row['kurz']);
            $major = intval($v_inst[0]);
            $minor = intval($v_inst[1]);
            foreach ($change_sql as $v => $sql) {
                $v = explode('.', $v);
                if ($major > intval($v[0]) || ($major == intval($v[0]) && $minor >= intval($v[1])))
                    continue;

                try {
                    $res = pg_query($this->context->getRwDbConn(), $sql);
                    if ($res) {
                        if ($echo)
                            $this->context->TE->printMessage("Update SQL $v[0].$v[1] successfull", inline: true);
                    } else
                        $this->context->TE->printMessage("Update SQL $v[0].$v[1] failed", 'danger', pg_last_error($this->context->getRwDbConn()), true);
                } catch (\Exception $e) {
                    $this->context->TE->printMessage("Update SQL $v[0].$v[1] failed", 'danger', $e->getMessage(), true);
                }
            }
        }


        //object types
        if ($echo)
            $this->context->TE->printMessage("Objektarten", 'notice');

        foreach ($this->json['art_objekt'] as $r) {
            //key 'update' is set by checkObjectTypes
            if ($r['update']) {
                //update
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'update art_objekt set view_file=$1, edit_file=$2, de=$3, en=$4, min_power=$5
                where uname=$6',
                    [$f_map[$r['view_file']] ?? null, $f_map[$r['edit_file']] ?? null, $r['de'], $r['en'], $r['min_power'], $r['uname']]
                );
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'update objekt set de=$1, en=$2 from art_objekt ao where objekt.id=ao.id and ao.uname=$3',
                    [$r['de'], $r['en'], $r['uname']]
                );
                if ($echo)
                    $this->context->TE->printMessage("Updated $r[uname]", inline: true);

            } else {
                //insert
                $res = pg_query($this->context->getRwDbConn(), "select max(id) as id from art_objekt");
                [$id] = pg_fetch_row($res, 0);
                $id++;
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into art_objekt(id,uname,id_mod,view_file,edit_file,de,en,min_power) 
                values ($1,$2,$3,$4,$5,$6,$7,$8)',
                    [$id, $r['uname'], $this->id, $f_map[$r['view_file']] ?? null, $f_map[$r['edit_file']] ?? null, $r['de'], $r['en'], $r['min_power']]
                );
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into objekt (id,id_obj,id_art,de,en,stichwort) values ($1, $2, $3, $4, $5, $6)',
                    [$id, $this->id, 5, $r['de'], $r['en'], $r['uname']]
                );
                if ($echo)
                    $this->context->TE->printMessage("Updated $r[uname]", inline: true);

            }
        }
        $this->load();

    }


    public function readJson()
    {
        $tar = '/bin/tar';
        if (!is_executable($tar))
            throw new \BayCMS\Exception\invalidData('/bin/tar is not executable');
        $file = $this->tarfile;

        if (!is_readable($file))
            throw new \BayCMS\Exception\accessDenied("$file is not readable");

        $fp = popen("$tar -tf $file", "r");
        $tar_content = [];
        while ($temp = fgets($fp, 2000)) {
            $temp = chop($temp);
            $tar_content[] = $temp;
        }
        pclose($fp);

        if (!in_array('modul.json', $tar_content))
            throw new \BayCMS\Exception\missingData('modul.json not found in tar file.');

        $fp = popen("$tar -xOf $file modul.json", "r");
        $json = '';
        while ($temp = fgets($fp, 2000)) {
            $json .= $temp;
        }
        pclose($fp);
        $this->json = json_decode($json, true);
    }



    public function detail()
    {
        if ($this->id === null)
            return;

        $aktion = $_GET['aktion'] ?? '';
        if (($_GET['id_mod'] ?? '') || ($_GET['id_art'] ?? '') || ($_GET['id_file'] ?? '') || ($_GET['id_config'] ?? ''))
            unset($_GET['aktion']);

        if (($_GET['aktion'] ?? '') == 'del')
            $this->del();

        $edit = $this->domain->getEdit();
        if ($edit['error'])
            $this->context->TE->printMessage($edit['error'], 'danger');
        if ($edit['message'])
            $this->context->TE->printMessage($edit['message']);
        if ($edit['edit']) {
            echo $edit['html'];
            $this->context->printFooter();
        }
        if ($aktion == 'save' && !isset($_GET['file']))
            $aktion = '';

        echo "<h3>" . $this->row['name'] . ' (' . $this->row['uname'] . ', ' . $this->row['kurz'] . ')</h3>
        <p>' . $this->row['beschreibung'] . '</p>';

        echo ($this->context->getPower() > 1000 ?
            $this->context->TE->getActionLink('?aktion=edit&id=' . $this->id, 'ändern', '', 'edit') . ' ' .
            $this->context->TE->getActionLink('?aktion=tar&id=' . $this->id, 'Make Tar', '', 'compressed') . ' ' .
            $this->context->TE->getActionLink('?aktion=tar_v1&id=' . $this->id, 'Make Tar (V1)', '', 'compressed') : '') . '<br/><br/>';





        $tab = new \BayCMS\Util\TabNavigation(
            $this->context,
            ['file', 'config', 'art', 'dep', 'sql', 'change'],
            ['Dateien', 'Config', 'Objektarten', 'Abhängigkeiten', 'Create SQL', 'Change SQL'],
            qs: 'id=' . $this->id
        );
        if ($this->context->getPower() > 1000) {
            $tab->addTab('upgrade', 'Upgrade');
            $tab->addTab('usage', 'Usage');
        }
        echo $tab->getNavigation();

        $t = $_GET['tab'] ?? 'file';
        if ($t == 'dep')
            $this->tabDep($aktion);
        elseif ($t == 'config')
            $this->tabConfig($aktion);
        elseif ($t == 'art')
            $this->tabArt($aktion);
        elseif ($t == 'file')
            $this->tabFile($aktion);
        elseif ($t == 'upgrade') {
            $this->tabUpgrade();
        } elseif ($t == 'usage') {
            $this->tabUsage();
        } elseif ($t == 'change') {
            if ($this->context->getPower() > 1000) {
                $f = new \BayCMS\Fieldset\Form(
                    $this->context,
                    submit: 'ausführen',
                    qs: "tab=change&id=" . $this->id,
                    action: '?sql=run'
                );
                $f->addField(new \BayCMS\Field\Textarea($this->context, 'SQL'));
                if (isset($_GET['sql'])) {
                    $res = pg_query($this->context->getRwDbConn(), $_POST['sql']);
                    if (!$res) {
                        $this->context->TE->printMessage(pg_last_error($this->context->getRwDbConn()), 'danger');
                    }
                    $f->setValues($_POST);
                }
                echo $f->getForm();
            }
            $change = $this->parseChangeSQL($this->row['change_sql'] ?? '');
            echo "<table " . $this->context->TE->getCSSClass('table') . ">";
            foreach ($change as $v => $sql) {
                echo "<tr><td><b>$v</b></td><td><pre>$sql</pre></td></tr>";
            }
            echo "</table>";
        } else {
            echo '<pre>' . str_replace('$DB$_extern', $this->getDBExtern(), $this->row['create_sql']) . '</pre>';
        }
    }

    private function tabUsage()
    {
        if ($this->context->getPower() <= 1000)
            return;
        $query = "select distinct l.id, non_empty(l.de, l.en), l.link
        from lehrstuhl l, index_files i, file f, objekt o 
        where l.id=i.id_lehr and i.id_file=f.id and f.id=o.id and o.id_obj=" . $this->id . " order by 2";
        $res = pg_query($this->context->getDbConn(), $query);
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            echo "<a href=\"/$r[link]\">$r[non_empty]</a><br/>";
        }
    }

    private function tabUpgrade()
    {
        if ($this->context->getPower() <= 1000)
            return;

        $f = new \BayCMS\Fieldset\Form(
            $this->context,
            submit: 'upgrade',
            qs: "tab=upgrade&id=" . $this->id,
            action: '?upgrade=run'
        );
        $f->addField(new \BayCMS\Field\Upload($this->context, 'Tar'));
        $f->addField(new \BayCMS\Field\Checkbox($this->context, 'all_files', 'Update all files'));
        if ($_GET['upgrade'] ?? '') {
            $f->setValues($_POST);
            $source = $f->getField('tar')->getFileLocation();
            if ($source) {
                $this->tarfile = $source;
                try {
                    $this->save(true, $_POST['all_files'] ?? false);
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
            }
        }
        echo $f->getForm('Upgrade from TAR');




    }
    private function tabArt($aktion)
    {
        if ($this->context->getPower() > 1000) {

            $aktion = $_GET['art'] ?? $aktion;
            $id = $_GET['id_art'] ?? '';
            $f2 = new \BayCMS\Fieldset\Form(
                $this->context,
                action: '?tab=art&art=save&id=' . $this->id,
                id_name: 'id_art',
                qs: 'tab=art&id=' . $this->id,
                table: 'art_objekt'
            );
            $f2->addField(new \BayCMS\Field\BilangInput(
                $this->context,
                '',
                'Name',
                non_empty: 1
            ));
            $f2->addField(new \BayCMS\Field\TextInput(
                $this->context,
                'uname',
                'Eindeutiger Name',
                non_empty: 1
            ));
            $f2->addField(new \BayCMS\Field\Select(
                $this->context,
                'min_power',
                'Berechtigung',
                null: 1,
                non_empty: 1,
                db_query: 'select power as id, non_empty(de,en) from power order by 1'
            ));
            $f2->addField(new \BayCMS\Field\Select(
                $this->context,
                'view_file',
                'View File',
                null: 1,
                db_query: "select f.id, f.name||' ('||non_empty(f.de,f.en)||')' as description
             from file f, objekt o where f.id=o.id and o.id_obj=" . $this->id . " order by 2"

            ));
            $f2->addField(new \BayCMS\Field\Select(
                $this->context,
                'edit_file',
                'Edit File',
                null: 1,
                db_query: "select f.id, f.name||' ('||non_empty(f.de,f.en)||')' as description
             from file f, objekt o where f.id=o.id and o.id_obj=" . $this->id . " order by 2"
            ));


            if ($id) {
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select * from art_objekt where id=$1',
                    [$id]
                );

                $r = pg_fetch_array($res, 0);
                $f2->setValues($r);
                $f2->setId($id);
            }

            if ($aktion == 'save') {
                if ($f2->setValues($_POST))
                    $aktion = 'edit';
            }

            if ($aktion == 'save') {
                if ($id)
                    $f2->save();
                else {
                    $res = pg_query(
                        $this->context->getDbConn(),
                        'select max(id) from art_objekt'
                    );
                    [$id] = pg_fetch_row($res, 0);
                    $id++;
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'insert into art_objekt(id,uname,id_mod) 
                values ($1,$2,$3)',
                        [$id, $_POST['uname'], $this->id]
                    );
                    $f2->setId($id);
                    $f2->save();
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'insert into objekt (id,id_obj,id_art,de,en,stichwort) values ($1, $2, $3, $4, $5, $6)',
                        [$id, $this->id, 5, $_POST['de'], $_POST['en'], $_POST['uname']]
                    );
                }

            }

            if ($aktion == 'del') {
                try {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'delete from art_objekt where id=$1',
                        [$_GET['id_art']]
                    );
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'delete from objekt where id=$1',
                        [$_GET['id_art']]
                    );
                    $this->context->TE->printMessage($this->t('Object type deleted', 'Objektart gelöscht'));
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
            }

            if ($aktion == 'edit')
                echo $f2->getForm();
        }
        $l = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'art_objekt t, power p',
            'p.power=t.min_power and t.id_mod=' . $this->id,
            id_query: 't.id',
            action_sep: '</td><td>',
            qs: 'tab=art&id=' . $this->id,
            id_name: 'id_art',
            step: -1,
            write_access_query: $this->context->getPower() > 1000 ? 'true' : 'false',
            actions: ['edit', 'del']
        );
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            '',
            'Name',
            sql: 'non_empty(t.de,t.en)'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'min_power',
            'Berechtigung',
            sql: 'non_empty(p.de,p.en)'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'view_file'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'edit_file'
        ));
        if ($this->context->getPower() > 1000)
            echo $this->context->TE->getActionLink('?tab=art&art=edit&id=' . $this->id, 'Neue Objektart', '', 'new', ['class' => ' btn-xs']) . "<br/>";
        echo $l->getTable();

    }

    private function tabConfig($aktion)
    {
        if (!$aktion && isset($_GET['config']))
            $aktion = $_GET['config'];
        if ($aktion) {
            $f = new \BayCMS\Fieldset\Form(
                $this->context,
                action: "?tab=config&id_config=$_GET[id_config]&config=save&id=" . $this->id
            );
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select d.*, d.value!=l.value as reset, case when l.value is null then d.value else l.value end as wert
            from modul_default_config d left outer join modul_ls_config l on d.id=l.id_modconfig and l.id_lehr=' . $this->context->getOrgId() . '
            where d.id=$1',
                [$_GET['id_config']]
            );
            $r = pg_fetch_array($res, 0);

            $f->addField(new \BayCMS\Field\Textarea($this->context, 'Wert'));
            if ($r['reset'] == 't')
                $f->addField(new \BayCMS\Field\Checkbox($this->context, 'default', 'Reset to DEFAULT'));

            $f->setValues($r);

            if ($aktion == 'save') {

                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from modul_ls_config where id_lehr=$1 and id_modconfig=$2',
                    [$this->context->getOrgId(), $_GET['id_config']]
                );
                if (!($_POST['default'] ?? '')) {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'insert into modul_ls_config(id_lehr,id_modconfig,value) values ($1, $2, $3)',
                        [$this->context->getOrgId(), $_GET['id_config'], $_POST['wert']]
                    );
                }

            } else {
                echo $f->getForm("Config-Wert &quot;$r[uname]&quot; verändern");
            }

        }

        $l = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'modul_default_config t left outer join modul_ls_config l on t.id=l.id_modconfig and l.id_lehr=' . $this->context->getOrgId(),
            't.mod=\'' . $this->row['uname'] . '\'',
            qs: 'tab=config&id=' . $this->id,
            write_access_query: 'true',
            actions: ['edit'],
            id_name: 'id_config',
            id_query: 't.id',
            step: -1
        );
        $l->addField(new \BayCMS\Field\TextInput($this->context, 'uname', 'Wert'));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Beschreibung',
            sql: 'non_empty(t.de,t.en)'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Inhalt',
            sql: 'case when t.value=l.value or l.value is null then \'DEFAULT\' else l.value end'
        ));
        echo $l->getTable();
    }

    private function del($objects = true)
    {
        if ($this->context->getPower() <= 1000)
            return;
        if ($this->id === null)
            return;
        $res=pg_query_params($this->context->getDbConn(),
        'select id_mod from modul_dep where id_needs=$1',
        [$this->id]);
        if(pg_num_rows($res)){
            $this->context->TE->printMessage('Es gibt noch Abhängigkeiten','danger' );
            return;
        }

        try {
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from index_files where id_super in (select i.id from index_files i where id_file in (select f.id from file f, objekt o where f.id=o.id and o.id_obj=$1));',
                [$this->id]
            );
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from index_files where id_file in (select f.id from file f, objekt o where f.id=o.id and o.id_obj=$1)',
                [$this->id]
            );
            pg_query_params($this->context->getRwDbConn(), 'update art_objekt set view_file=null, edit_file=null where id_mod=$1', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from objekt where id_obj=$1', [$this->id]);
            if ($this->row['delete_sql'])
                pg_query($this->context->getRwDbConn(), $this->row['delete_sql']);
            if ($objects) {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from objekt where id_art in (select id from art_objekt where id_mod=$1)',
                    [$this->id]
                );
            }
            pg_query_params($this->context->getRwDbConn(), 'delete from auto_add where id_art in (select id from art_objekt where id_mod=$1)', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from admin_objekt where id_art in (select id from art_objekt where id_mod=$1)', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from no_create_objekt where id_art in (select id from art_objekt where id_mod=$1)', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from objekt where id in (select id from art_objekt where id_mod=$1)', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from art_objekt where id_mod=$1', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from modul_dep where id_mod=$1', [$this->id]);
            pg_query_params($this->context->getRwDbConn(), 'delete from objekt where id=$1', [$this->id]);

            $this->context->TE->printMessage('Modul deleted');
            $this->context->printFooter();
        } catch (\Exception $e) {
            $this->context->TE->printMessage('Delete failed: ' . $e->getMessage());

        }
    }
    private function tabFile($aktion)
    {
        if ($this->context->getPower() > 1000) {

            $aktion = $_GET['file'] ?? $aktion;
            $id = $_GET['id_file'] ?? '';
            $f2 = new \BayCMS\Fieldset\Form(
                $this->context,
                action: '?tab=file&file=save&id=' . $this->id,
                id_name: 'id_file',
                qs: 'tab=file&id=' . $this->id
            );
            $f2->addField(new \BayCMS\Field\BilangInput(
                $this->context,
                '',
                'Name',
                non_empty: 1
            ));
            $f2->addField(new \BayCMS\Field\Upload(
                $this->context,
                'file',
                $this->t('File', 'Datei'),
                non_empty: !$id
            ));
            $lang = $this->context->lang;
            $lang2 = $this->context->lang2;
            $f2->addField(new \BayCMS\Field\Select(
                $this->context,
                'id_kat',
                $this->t('Category', 'Kategorie'),
                db_query: "select id,non_empty($lang,$lang2) as description
            from kategorie",
                null: 1,
                non_empty: 1
            ));
            $f2->addField(new \BayCMS\Field\Textarea(
                $this->context,
                'beschreibung',
                $this->t('Description', 'Beschreibung')
            ));
            $f2->addField(new \BayCMS\Field\Checkbox(
                $this->context,
                'index_file',
                'Index Datei'
            ));
            if ($id) {
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select *,not (name ilike \'%/\'||id||\'/%\') as no_add_id_obj from file where id=$1',
                    [$id]
                );

                $r = pg_fetch_array($res, 0);
                $f2->setValues($r);
                $f2->setId($id);
            }

            if ($aktion == 'save') {
                if ($f2->setValues($_POST))
                    $aktion = 'edit';
            }

            if ($aktion == 'save') {
                $file = new \BayCMS\Base\BayCMSFile($this->context);
                if ($id)
                    $file->load($id);
                $source = $f2->getField('file')->getFileLocation();
                if ($source)
                    $file->set(['source' => $source]);
                $name = $f2->getField('file')->getFileName();
                if ($name)
                    $file->set(['name' => $name]);

                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select link from kategorie where id=$1',
                    [$_POST['id_kat']]
                );
                [$link] = pg_fetch_row($res, 0);
                $file->set([
                    'de' => $_POST['de'],
                    'en' => $_POST['en'],
                    'path' => $link . '/' . $this->row['uname'],
                    'description' => $_POST['beschreibung'],
                    'id_parent' => $this->id,
                    'add_id_obj' => 0
                ]);
                try {
                    $id = $file->save();
                    $this->context->TE->printMessage($this->t('File saved', 'Datei gespeichert'));
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
                if ($id)
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'update file set index_file=$1 where id=$2',
                        [$_POST['index_file'] ?? 0, $id]
                    );

            }

            if ($aktion == 'del') {
                $file = new \BayCMS\Base\BayCMSFile($this->context);
                $file->load($_GET['id_file']);
                try {
                    $id = $file->erase();
                    $this->context->TE->printMessage($this->t('File deleted', 'Datei gelöscht'));
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
            }

            if ($aktion == 'edit')
                echo $f2->getForm();
        }

        if ($_GET['index_files'] ?? '') {
            $res = pg_query(
                $this->context->getDbConn(),
                "select o.id_obj from modul m, index_files i, file f, objekt o 
             where i.id_lehr=" . $this->context->getOrgId() . " and i.id_file=f.id and f.id=o.id and o.id_obj=" . $this->id
            );
            if (pg_num_rows($res)) {
                $this->context->TE->printMessage("Modul wird bereits verwendet. Nutzen Sie die Indexverwaltung, um Links zu verändern oder zu löschen.", 'warning');
                unset($_GET['index_files']);
            }
        }

        if ($_GET['index_files'] ?? '') {
            $res = pg_query(
                $this->context->getDbConn(),
                "select b.id, b.name, b.id_kat, a.link, b.de, b.en 
            from kategorie a, file b, objekt c where c.id_obj=" . $this->id . " and c.id=b.id and b.index_file and b.id_kat=a.id and a.id>100"
            );

            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_array($res, $i);
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into index_files (id_file,id_super,id_lehr,de,en) values ($1, $2, $3, $4, $5)',
                    [$r['id'], $r['id_kat'], $this->context->getOrgId(), $r['de'], $r['en']]
                );
            }
            $this->context->TE->printMessage("Default Links eingefügt. Bitte nutzten Sie die Indexverwaltung, um die Linknamen zu ändern.");
        }


        if ($_GET['id_file'] ?? '') {
            $res = pg_query_params(
                $this->context->getDbConn(),
                "select name,non_empty(" . $this->context->getLangLang2('') . ") as titel,beschreibung from file where id=\$1",
                [$_GET['id_file']]
            );
            $r = pg_fetch_array($res, 0);
            echo "<h4>" . $r['name'] . " " . $r['titel'] . "</h4>\n";
            if ($r['beschreibung'])
                echo $r['beschreibung'];
            if (preg_match('/(php|inc)$/', $r['name'])) {
                echo "<hr>\n";
                show_source($this->context->BayCMSRoot . "/" . $r['name']);
                echo "<hr>\n";
            }
        }
        $l = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'file t, objekt o',
            't.id=o.id and o.id_obj=' . $this->id,
            id_query: 't.id',
            action_sep: '</td><td>',
            qs: 'tab=file&id=' . $this->id,
            id_name: 'id_file',
            step: -1,
            write_access_query: $this->context->getPower() > 1000 ? 'true' : 'false',
            actions: ['view', 'edit', 'del']
        );
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'name',
            'Name'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Beschreibung'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Index Datei',
            sql: "case when t.index_file then 'Ja' else '' end"
        ));


        echo $this->context->TE->getActionLink('?index_files=1&tab=file&id=' . $this->id, 'Links in Navigation aufnehmen', '', 'ok-sign') . ' ';
        if ($this->context->getPower() > 1000)
            echo $this->context->TE->getActionLink('?tab=file&file=edit&id=' . $this->id, 'Neue Datei', '', 'new', ['class' => ' btn-xs']);
        echo "<br/>";
        echo $l->getTable();
    }

    private function tabDep($aktion)
    {
        //Abhängigkeiten
        if ($this->context->getPower() > 1000) {
            $f2 = new \BayCMS\Fieldset\Form(
                $this->context,
                action: '?modul_dep=save&tab=dep&id=' . $this->id
            );
            $f2->addField(new \BayCMS\Field\SelectJS(
                $this->context,
                'id_needs',
                $_SERVER['PHP_SELF'],
                'Modul',
                db_query: 'select id, name as description from modul where id=$1',
                non_empty: 1
            ));
            $f2->addField(new \BayCMS\Field\TextInput(
                $this->context,
                'min_version',
                'Version',
                non_empty: 1
            ));

            if (($_GET['id_mod'] ?? '') && $aktion == 'edit') {
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select * from modul_dep where id_mod=$1 and id_needs=$2',
                    [$this->id, $_GET['id_mod']]
                );
                $r = pg_fetch_array($res, 0);
                $f2->setValues($r);
            }
            if (($_GET['id_mod'] ?? '') && $aktion == 'del') {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from modul_dep where id_mod=$1 and id_needs=$2',
                    [$this->id, $_GET['id_mod']]
                );
            }


            $aktion = $_GET['modul_dep'] ?? '';
            if ($aktion == 'save') {
                if ($f2->setValues($_POST))
                    $aktion = '';
            }
            if ($aktion == 'save') {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from modul_dep where id_mod=$1 and id_needs=$2',
                    [$this->id, $_POST['id_needs']]
                );
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into modul_dep(id_mod, id_needs, min_version) values ($1, $2, $3)',
                    [$this->id, $_POST['id_needs'], $_POST['min_version']]
                );
            }

            echo $f2->getForm();

        }
        $l = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'modul t, modul_dep d',
            't.id=d.id_needs and d.id_mod=' . $this->id,
            id_query: 't.id',
            action_sep: '</td><td>',
            qs: 'tab=dep&id=' . $this->id,
            id_name: 'id_mod',
            write_access_query: $this->context->getPower() > 1000 ? 'true' : 'false',
            actions: ['edit', 'del']
        );
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'name',
            'Modul'
        ));
        $l->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'min_version',
            'Version',
            sql: 'd.min_version'
        ));
        echo $l->getTable();
    }

    public function onlineUpdate(string $uname, bool $echo = false, bool $all_files = false, ?string $server = null)
    {
        $this->tarfile = tempnam($this->context->BayCMSRoot . '/tmp/', 'modul.update');
        copy(($server === null ? $this->server : $server) . '/de/top/gru/server.php?mod=' . $uname, $this->tarfile);
        $res = pg_query_params($this->context->getDbConn(), 'select id from modul where uname=$1', [$uname]);
        if (pg_num_rows($res))
            [$this->id] = pg_fetch_row($res, 0);
        $this->load();
        try {
            $this->save($echo, $all_files);
        } catch (\Exception $e) {
            $this->context->TE->printMessage('Error in onlineUpdate:', 'danger', $e->getMessage());
        }
        unlink($this->tarfile);
        $this->tarfile = '';
    }

    public function page()
    {
        if (($_GET['aktion'] ?? '') == 'tar') {
            $this->mktar();
        }
        if (($_GET['aktion'] ?? '') == 'tar_v1') {
            $this->mktar_v1();
        }

        if (isset($_GET['json_query'])) {
            echo $this->domain->getJSON();
            exit();
        }
        $this->context->printHeader();

        if ($this->context->getPower() > 1000) {
            if ($_GET['install'] ?? '') {
                //Install/Update Modul from Server
                $this->onlineUpdate($_GET['install'], true);
            }
        }

        if ($this->id !== null) {
            $this->detail();
        } else {
            echo $this->domain->getHead();
            $this->domain->pageEdit();
        }

        $this->context->printFooter();

    }
}
