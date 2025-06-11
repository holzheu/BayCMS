<?php

namespace BayCMS\Base;
use Exception;

class BayCMSFile extends BayCMSObject
{

    public static function replaceLocalDe(string $s)
    {
        $trans = [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ß' => 'ss',
            ' ' => '_',
            '%' => '',
            '?' => '_',
            '&' => '',
            '=' => '',
            ';' => '',
            '"' => '',
            "'" => '',
            '/' => '',
            '\\' => ''
        ];
        return strtr($s, $trans);
    }


    private ?string $source = '';
    private string $descripton = '';
    private string $path; //relativ to $BayEOSRoot 
    private string $full_path;
    private string $name;
    private int $extract = 0;
    private int $add_id_obj = 0;

    public function __get($name)
    {
        try {
            return $this->$name;
        } catch (Exception $e) {
            return parent::__get($name);
        }
    }

    public function get(): array
    {
        $res = parent::get();
        $res['name'] = $this->name;
        $res['description'] = $this->descripton;
        $res['path'] = $this->path;
        $res['full_path'] = $this->full_path;
        $res['add_id_obj'] = $this->add_id_obj;
        $res['source'] = $this->source;
        return $res;
    }

    public function load(?int $id = null)
    {
        parent::load($id);
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select name,beschreibung from file where id=$1',
            [$this->id]
        );
        if (!pg_num_rows($res))
            throw new \BayCMS\Exception\notFound("File with id=" . $this->id . " does not exist\n");
        [$this->name, $this->descripton] = pg_fetch_row($res, 0);
        if (!preg_match('|^(.*)/([^/]+)$|', $this->name, $matches))
            preg_match('|(.*)/([^/]+/)$|', $this->name, $matches);
        $this->name = $matches[2];
        $this->full_path = $matches[1];
        if (preg_match('|(/?[0-9]*)/(' . $id . ')$|', $this->full_path, $matches)) {
            $this->add_id_obj = 1;
            $this->path = preg_replace('|' . $matches[1] . '/' . $matches[2] . '|', '', $this->full_path);
        } else {
            $this->path = $this->full_path;
            $this->add_id_obj = 0;
        }
    }

    public function save($start_transaction = true): int
    {
        $this->name = $this->replaceLocalDe($this->name);
        if ($this->extract && preg_match('/(.*)\.zip$/', $this->name, $match)) {
            $this->name = $match[1] . '/';
        } else
            $this->extract = 0;
        preg_replace("|^/|", '', $this->path);
        if (!preg_match("|/$|", $this->path))
            $this->path .= "/";
        if (!preg_match('|^[a-zA-Z0-9]|', $this->path) || preg_match('|\\.\\./|', $this->path)) {
            throw new \BayCMS\Exception\invalidPath("Invalid path '" . $this->path . "'");
        }

        if ($this->source && !is_readable($this->source)) {
            throw new \BayCMS\Exception\fileNotReadable('Source ' . $this->source . ' is not readable');
        }

        if ($this->source) {
            $new_source = tempnam($this->context->BayCMSRoot . "/tmp", 'upload');
            if (!move_uploaded_file($this->source, $new_source)) {
                if (is_writable($this->source))
                    rename($this->source, $new_source);
                else
                    copy($this->source, $new_source);
            }
            $this->source = $new_source;
            if (is_writable($this->source))
                chmod($this->source, 0644);
        }

        if ($this->id === null) {
            if (!$this->source || !is_readable($this->source)) {
                throw new \BayCMS\Exception\missingData('Cannot save a file without a source');
            }
            $res = pg_query_params(
                $this->context->getRwDbConn(),
                'select file_save($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12)',
                [
                    $this->context->getUserId(),
                    $this->context->getOrgId(),
                    $this->source,
                    $this->name,
                    $this->path,
                    $this->de,
                    $this->en,
                    $this->descripton,
                    $this->id_parent,
                    $this->add_id_obj,
                    0,
                    $this->extract
                ]
            );
            [$this->id] = pg_fetch_row($res, 0);
            $this->cleanUp();
            return $this->id;
        }


        $mtime = 'now()';
        if (!$this->source) {
            $this->source = null;
            $mtime = 'mtime';
        }
        pg_query_params(
            $this->context->getRwDbConn(),
            'select file_update($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,' . $mtime . ')
         from file where id=$3',
            [
                $this->context->getUserId(),
                $this->context->getOrgId(),
                $this->id,
                $this->source,
                $this->name,
                $this->path,
                $this->de,
                $this->en,
                $this->descripton,
                $this->id_parent,
                $this->add_id_obj,
                0,
                $this->extract
            ]
        );
        $this->cleanUp();
        return $this->id;

    }


    private function cleanUp()
    {
        pg_query_params(
            $this->context->getRwDbConn(),
            'update file set size=c_file_size($2||name) where id=$1',
            [$this->id, $this->context->BayCMSRoot . '/']
        );
        if (
            is_writable($this->source) && ($_SERVER['REMOTE_ADDR'] ?? false) &&
            preg_match('|/tmp/|', $this->source)
        )
            unlink($this->source);

        $this->load($this->id);
    }

    public function set(
        array $values = [],
        int $id_parent = -1,
        ?string $uname = null,
        int $id_art = -1,
        ?string $de = null,
        ?string $en = null,
        ?string $stichwort = null,
        ?bool $child_allowed = null,
        ?string $og_description_de = null,
        ?string $og_description_en = null,
        ?string $og_title_de = null,
        ?string $og_title_en = null,
        int $og_img = -1,
        ?string $source = null,
        ?string $name = null,
        ?string $path = null,
        ?string $description = null,
        ?bool $extract = null,
        ?bool $add_id_obj = null
    ) {
        parent::set($values, $id_parent, $uname, $id_art, $de, $en, $stichwort, $child_allowed, $og_description_de, $og_description_en, $og_title_de, $og_title_en, $og_img);
        if (isset($values['source']))
            $this->source = $values['source'];
        if ($source !== null)
            $this->source = $source;
        if (isset($values['name']))
            $this->name = $values['name'];
        if ($name !== null)
            $this->name = $name;
        if (isset($values['path']))
            $this->path = $values['path'];
        if ($path !== null)
            $this->path = $path;
        if (isset($values['description']))
            $this->descripton = $values['description'];
        if ($description !== null)
            $this->descripton = $description;
        if (isset($values['extract']))
            $this->extract = $values['extract'];
        if ($extract !== null)
            $this->extract = $extract;
        if (isset($values['add_id_obj']))
            $this->add_id_obj = $values['add_id_obj'];
        if ($add_id_obj !== null)
            $this->add_id_obj = $add_id_obj;
    }

}