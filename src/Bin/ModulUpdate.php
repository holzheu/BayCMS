#!/usr/bin/php
<?php

$options = ['-f' => 0, '-m' => 0, '-s' => null];

function die_usage()
{
    die("Usage: ModulUpdate.php [-f] [-m modul] [-s server] rootfolder
	
	-f: full update (overwrite all existing files)
    -m: modul
    -s: server
");
}

if (count($argv) < 2)
    die_usage();

for ($i = 1; $i < count($argv); $i++) {
    if (preg_match('/-[fms]/', $argv[$i])) {
        if ($argv[$i] != '-f') {
            $options[$argv[$i]] = $argv[++$i];
        } else {
            $options[$argv[$i]] = 1;
        }
    } else
        $root = $argv[$i];
}

require_once 'BayCMS/BayCMS.php';

$context = new \BayCMS\Base\BayCMSContext($root, ['id' => 5001, 'power' => 10000, 'id_benutzer' => 5002]);
$context->initTemplate();
$context->setSystemUser();
$context->commandline = true;

$modul = new \BayCMS\Page\Admin\Modul($context);

if ($options['-m']) {
    $r = [['uname' => $options['-m']]];
} else {
    $res = pg_query($context->getDbConn(), 'select uname,kurz from modul order by 1');
    $r = pg_fetch_all($res);
}

foreach ($r as $mod) {
    echo "\n\e[1;37;44mUpdating modul $mod[uname]:\e[0m\n";
    try {
        $modul->onlineUpdate($mod['uname'], true, $options['-f'], $options['-s']);
    } catch (\Exception $e) {
        $context->TE->printMessage("Failed!", 'danger', $e->getMessage());
    }
}


