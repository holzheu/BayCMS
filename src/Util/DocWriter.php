<?php

namespace BayCMS\Util;

class DocWriter
{

    private \BayCMS\Base\BayCMSContext $context;
    private \BayCMS\Util\PDFWriter|\BayCMS\Util\XMLDocWriter $writer;
    public function __construct(\BayCMS\Base\BayCMSContext $context, array $values, $template)
    {
        $this->context = $context;
        if (is_array($template))
            $t = $template[0];
        else
            $t = $template;
        $matches = [];
        preg_match('/\\.([a-z]+$)/i', $t, $matches);
        $type = $matches[1];
        if (strtolower($type) == 'pdf') {
            $this->writer = new \BayCMS\Util\PDFWriter($values, $template);
        } else {
            $this->writer = new \BayCMS\Util\XMLDocWriter($values, $template);
        }
    }

    public function write($json = false)
    {
        return $this->writer->write($json);
    }

    public function send($tmp_name, $name, $delete = false)
    {
        $matches = [];
        preg_match('/\\.([a-z]+$)/i', $name, $matches);
        $type = $matches[1];
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select mime from mime_types where endung=lower($1) or endung=$1',
            [$type]
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
        header("Content-Length: " . filesize($tmp_name));
        header("Content-disposition: inline; filename=$name"); // prompt to save to disk
        ob_clean();
        flush();
        readfile($tmp_name);
        if ($delete)
            unlink($tmp_name);
        exit;


    }
}