<?php
namespace BayCMS\Page;

use Exception;

class BinPage extends Page
{
    private ?string $file;
    private ?string $file_name;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        ?string $file = null,
        ?string $file_name = null
    ) {
        $this->file = $file;
        $this->file_name = $file_name;
        parent::__construct($context);
    }

    public function page()
    {
        if (is_null($this->file)) {
            if (isset($_GET['id_obj']) && !isset($_GET['id']))
                $_GET['id'] = $_GET['id_obj'];
            if (isset($_GET['id'])) {
                $file = new \BayCMS\Base\BayCMSFile($this->context);
                try {
                    $file->load($_GET['id']);
                } catch (Exception $e) {
                    $this->error(401, $e->getMessage());
                }

                $this->file_name = $file->name;
                $this->file = $this->context->BayCMSRoot . '/' . $file->full_path . '/' . $file->name;
            } else {
                $this->error(401, 'No file to download');
            }
        }
        $path = tempnam($this->context->BayCMSRoot, 'TESTFOLDER');
        $path = preg_replace("|[^/]+$|", "", $path);
        $path2 = $this->context->BayCMSRoot . '/';

        if (!is_readable($this->file))
            $this->error(404, 'File with path ' . $this->file . ' not found');

        if (!preg_match("&^($path|$path2)&", $this->file))
            $this->error(401, 'File is not in BayCMS dir');

        $access = false;
        if (preg_match("&^($path|$path2)image/&", $this->file)) {

            if (!preg_match("&^($path|$path2)image/intern/&", $this->file))
                $access = true;


            if (!$access && ($this->context->getPower()) > 0) {
                $id = preg_replace('&.+/[ot]?([0-9]+)\\.[jpegnif]+$&', "\\1", $this->file);
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select b.id from bild b, objekt_verwaltung' . $this->context->getOrgId() . ' o
                where b.id_obj=o.id and b.id=$1',
                    [$id]
                );
                $access = pg_num_rows($res);
            }
        }


        if (!$access)
            $access = preg_match("&^($path|$path2)tmp/" . session_id() . "&", $this->file);

        if (!$access) {
            $file = preg_replace("&($path|$path2)&", '', $this->file);
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select check_objekt(id,$2) from file where name=$1',
                [$file, $this->context->getUserId()]
            );


            if (!pg_num_rows($res))
                $this->error(401, 'This is not a regular BayCMS file');
            [$access] = pg_fetch_row($res, 0);
            if ($access != 't')
                $access = false;
            if (!$access) {
                //Download allowed when... see query...
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select f.id from file f, verweis v where v.id_von=f.id and v.id_auf=$2 and v.ordnung<=$3 and f.name=$1',
                    [$file, $this->context->getOrgId(), $this->context->getPower() ?? 0]
                );
                $access = pg_num_rows($res);
            }
        }
        if (!$access)
            $this->error(401, 'You do not have the rights to download the file');


        //All fine :-)

        $tmp_array = explode(".", $this->file_name);
        $endung = $tmp_array[count($tmp_array) - 1];
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select mime from mime_types where endung=lower($1) or endung=$1',
            [$endung]
        );
        if (pg_num_rows($res))
            list($mime) = pg_fetch_row($res, 0);
        else
            $mime = "application/octet-stream";
        header("Content-type: $mime");
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        //header('Pragma: public');
        //header('Accept-Ranges: bytes');
        header("Content-Length: " . filesize($this->file));
        header("Content-disposition: inline; filename=" . $this->file_name); // prompt to save to disk
        ob_clean();
        flush();
        readfile($this->file);
        exit;
    }
}