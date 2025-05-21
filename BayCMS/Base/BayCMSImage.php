<?php
namespace BayCMS\Base;

class BayCMSImage extends BayCMSRow
{
    private int $id_obj;
    private string $name = '';
    private string $type;

    private bool $thumbnail = true;
    private ?string $source = '';
    private ?int $height = null;
    private ?int $theight = null;
    private ?string $de = '';
    private ?string $en = '';
    private bool $internal = false;
    private ?float $crop = 0;
    private ?float $tcrop = 0;
    private ?int $x = null;
    private ?int $y = null;
    private ?int $ox = null;
    private ?int $oy = null;
    private ?int $tx = null;
    private ?int $ty = null;

    private array $ids_created_from_zip = [];


    public function load(int|null $id = null)
    {
        if ($id === null && $this->id === null) {
            throw new \BayCMS\Exception\missingId("Cound not load image without id");
        }
        if ($id !== null)
            $this->id = $id;

        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id_obj,name,
            case when thumbnail then 1 else 0 end,
            height,theight,de,en,
            case when intern then 1 else 0 end,
            crop,tcrop,
            x,y,ox,oy,tx,ty from bild where id=$1',
            [$this->id]
        );
        if (!pg_num_rows($res)) {
            throw new \BayCMS\Exception\notFound('Image with id=' . $this->id . ' does not exist');
        }
        [
            $this->id_obj,
            $this->name,
            $this->thumbnail,
            $this->height,
            $this->theight,
            $this->de,
            $this->en,
            $this->internal,
            $this->crop,
            $this->tcrop,
            $this->x,
            $this->y,
            $this->ox,
            $this->oy,
            $this->tx,
            $this->ty
        ] =
            pg_fetch_row($res, 0);
        $this->source = null;

    }

    public function get(): array
    {
        return [
            'id' => $this->id,
            'id_obj' => $this->id_obj,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail,
            'source' => $this->source,
            'height' => $this->height,
            'theight' => $this->theight,
            'de' => $this->de,
            'en' => $this->en,
            'internal' => $this->internal,
            'crop' => $this->crop,
            'tcrop' => $this->tcrop,
            'x' => $this->x,
            'y' => $this->y,
            'ox' => $this->ox,
            'oy' => $this->oy,
            'tx' => $this->tx,
            'ty' => $this->ty
        ];
    }

    public function save(): int
    {

        if ($this->source) {
            if (!is_readable($this->source)) {
                throw new \BayCMS\Exception\fileNotReadable('Source ' . $this->source . ' is not readable');
            }
            $new_source = tempnam($this->context->BayCMSRoot . "/tmp", 'upload');
            if (move_uploaded_file($this->source, $new_source))
                $this->source = $new_source;
            if (is_writable($this->source))
                chmod($this->source, 0644);
        }

        $obj = new BayCMSObject($this->context);
        $obj->load($this->id_obj);
        $obj->checkAccess();
        $this->name = strtolower($this->name);

        if (preg_match("/\.gif$/", $this->name))
            $this->type = "gif";
        elseif (preg_match("/\.jpe?g$/", $this->name))
            $this->type = "jpg";
        elseif (preg_match("/\.png$/", $this->name))
            $this->type = "png";
        elseif (preg_match("/\.svg$/", $this->name))
            $this->type = "svg";
        elseif (preg_match("/\.zip$/", $this->name))
            $this->type = "zip";
        else {
            throw new \BayCMS\Exception\unsupportedFiletype("Unsupported file type: " . $this->name);
        }

        if ($this->id === null) {
            if ($this->type == 'zip') {
                if (count($this->ids_created_from_zip))
                    return 0; //zip in zip!!
                $v_main = $this->get(); //get current Image settings 
                $tempfile = tempnam(sys_get_temp_dir(), '');
                // tempnam creates file on disk
                if (file_exists($tempfile)) {
                    unlink($tempfile);
                }
                mkdir($tempfile);
                exec("/usr/bin/unzip '" . $this->source . "' -d $tempfile");
                foreach (glob("$tempfile/*") as $filename) {
                    if (mb_detect_encoding($filename, 'UTF-8, ISO-8859-1') != 'UTF-8') {
                        $f_neu = mb_convert_encoding($filename, 'UTF-8', 'ISO-8859-1');
                        rename($filename, $f_neu);
                        $filename = $f_neu;
                    }
                    $name = str_replace("$tempfile/", "", $filename);
                    $v = $v_main;
                    $v['source'] = $filename;
                    $v['name'] = $name;
                    $name = preg_replace('/\\.[a-z_A-Z]+$/', '', $name);
                    $name = str_replace("_", " ", $name);
                    if ($v['de'])
                        $v['de'] . " " . $name;
                    if ($v['en'])
                        $v['en'] . " " . $name;
                    if (!$v['de'])
                        $v['de'] = $name;
                    $this->set($v);
                    try {
                        $this->id = null;
                        $this->ids_created_from_zip[] = $this->save();
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                return $this->id;

            }

            $res = pg_query($this->context->getRwDbConn(), "select nextval('bild_id')");
            [$this->id] = pg_fetch_row($res, 0);
            $this->name = $this->id . "." . $this->type;
            $v = [
                $this->id,
                $this->id_obj,
                $this->name,
                $this->thumbnail ? 1 : 0,
                $this->source,
                $this->height,
                $this->theight,
                $this->de,
                $this->en,
                $this->internal ? 1 : 0,
                $this->crop,
                $this->tcrop
            ];
            pg_query_params(
                $this->context->getRwDbConn(),
                'insert into bild(id,id_obj,name,thumbnail,
                quelle,height,theight,de,en,
               intern,crop,tcrop) values
               ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12)',
                $v
            );
            $this->cleanUp();
            return $this->id;
        }


        $query = 'update bild set height=$2, theight=$3, de=$4, en=$5, 
        thumbnail=$6, intern=$7, crop=$8, tcrop=$9';
        $v = [
            $this->id,
            $this->height,
            $this->theight,
            $this->de,
            $this->en,
            $this->thumbnail ? 1 : 0,
            $this->internal ? 1 : 0,
            $this->crop,
            $this->tcrop
        ];
        if ($this->source) {
            $query .= ',name=$10, quelle=$11';
            $v[] = $this->id . '.' . $this->type;
            $v[] = $this->source;
        }
        $query .= ' where id=$1';
        pg_query_params($this->context->getRwDbConn(), $query, $v);
        $this->cleanUp();
        return $this->id;
    }

    public function cleanUp()
    {
        if (
            $this->source &&
            is_writable($this->source) && isset($_SERVER['REMOTE_ADDR']) &&
            preg_match('|' . sys_get_temp_dir() . $_SERVER['REMOTE_ADDR'] . '|', $this->source)
        )
            unlink($this->source);
        $this->source = null;
        $this->load();
    }

    public function set(
        array $v = [],
        string $name = null,
        string $source = null,
        int $id_obj = -1,
        int $height = -1,
        int $theight = -1,
        string $de = null,
        string $en = null,
        bool $internal = null,
        float $crop = -1,
        float $tcrop = -1

    ) {
        if (isset($v['name']))
            $this->name = $v['name'];
        if ($name !== null)
            $this->name = $name;
        if (isset($v['source']))
            $this->source = $v['source'];
        if ($source !== null)
            $this->source = $source;
        if (isset($v['id_obj']))
            $this->id_obj = $v['id_obj'];
        if ($id_obj != -1)
            $this->id_obj = $id_obj;
        if (isset($v['height']))
            $this->height = $v['height'];
        if ($height != -1)
            $this->height = $height;
        if (isset($v['theight']))
            $this->theight = $v['theight'];
        if ($theight != -1)
            $this->theight = $theight;
        if (isset($v['de']))
            $this->de = $v['de'];
        if ($de !== null)
            $this->de = $de;
        if (isset($v['en']))
            $this->en = $v['en'];
        if ($en !== null)
            $this->en = $en;
        if (isset($v['internal']))
            $this->internal = $v['internal'];
        if ($internal !== null)
            $this->internal = $internal;
        if (isset($v['crop']))
            $this->crop = $v['crop'];
        if ($crop != -1)
            $this->crop = $crop;
        if (isset($v['tcrop']))
            $this->tcrop = $v['tcrop'];
        if ($tcrop != -1)
            $this->tcrop = $tcrop;
        if ($this->theight > 0)
            $this->thumbnail = true;
    }

    public function erase()
    {
        if (!$this->id)
            return;
        $obj = new BayCMSObject($this->context);
        $obj->load($this->id_obj);
        $obj->checkAccess();
        pg_query_params(
            $this->context->getRwDbConn(),
            'delete from bild where id=$1',
            [$this->id]
        );
        $this->id = null;
    }

    public function getIdsCreatedFromZip()
    {
        return $this->ids_created_from_zip;
    }
}