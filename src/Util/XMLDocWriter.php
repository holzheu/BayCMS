<?php

namespace BayCMS\Util;

class XMLDocWriter
{

    protected array $values;
    private string $template;
    public function __construct(array $values, $template)
    {
        $this->values = $values;
        $this->template = $template;
    }

    function sendJsonMessage($id, $message = '', $progress = 0)
    {
        $d = array('message' => $message, 'progress' => $progress);

        echo "id: $id" . PHP_EOL;
        echo "data: " . json_encode($d) . PHP_EOL;
        echo PHP_EOL;

        ob_flush();
        flush();
    }

    public function write($json = false)
    {
        $matches = [];
        preg_match('|/([^/]+)$|i', $this->template, $matches);
        $f_name =$matches[1];
        preg_match('/\\.([a-z]+$)/i', $f_name, $matches);
        $type =$matches[1];
        switch ($type) {
            case 'docx':
                $body_tag = "w:body";
                $doc_path = "word/document.xml";
                break;
            case 'odt':
                $body_tag = "office:body";
                $doc_path = "content.xml";
                break;
            case 'odp':
                $body_tag = "office:body";
                $doc_path = "content.xml";
                break;
            case 'pptx':
                $body_tag = "p:sld";
                $doc_path = "pptx/ppt/slides/slide1.xml";//Will only work for one slide pptx
                break;
            default:
                throw new \BayCMS\Exception\unsupportedFiletype('unsupported template file type ' . $type);
        }
        $zip = new \clsTbsZip();
        $tmp = tempnam(sys_get_temp_dir(), "xmldoc");
        copy( $this->template, $tmp);
        $zip->Open($tmp);
        $t_xml = $zip->FileRead($doc_path);
        $zip->Close();
        $p = strpos($t_xml, "<$body_tag");
        if ($p === false)
            exit("Tag <$body_tag> not found in document 1.");
        $p = strpos($t_xml, '>', $p);
        $t_xml = substr($t_xml, $p + 1);
        $p = strpos($t_xml, "</" . $body_tag . ">");
        if ($p === false)
            exit("Tag </$body_tag> not found in document 1.");
        $t_xml = substr($t_xml, 0, $p);
        $t_xml_orig = $t_xml;

        $content = '';

        preg_match_all('/\$[^\$]+?}/', $t_xml, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $matches_new[$i] = preg_replace('/(<[^<]+?>)/', '', $matches[0][$i]);
            $t_xml = str_replace(
                $matches[0][$i],
                $matches_new[$i],
                $t_xml
            );
        }


        if ($type == 'docx') {
            $t_xml = preg_replace('/<w:t>([^\\$<]*?\\$\\{[^\\}]+?\\}[^<]*?)<\/w:t>/', '<w:t xml:space="preserve">$1</w:t>', $t_xml);
        }


        for ($i = 0; $i < count($this->values); $i++) {
            $search = array();
            $replace = array();
            foreach ($this->values[$i] as $key => $value) {
                if(is_array($value)) continue;
                $search[] = '${' . $key . '}';
                $replace[] = htmlspecialchars($value);
            }

            $content .= str_replace($search, $replace, $t_xml);
            if ($json)
                $this->sendJsonMessage(
                    'XML',
                    'processing ' . ($i + 1) . ' of ' . count($this->values) . ')',
                    ($i + 1) / count($this->values) * 90
                );
        }

        $zip->Open($tmp);
        $content = str_replace($t_xml_orig, $content, $zip->FileRead($doc_path));
        //	header("Content-type: text/xml");
        //	echo $content;
        //	exit();
        $zip->FileReplace($doc_path, $content, TBSZIP_STRING);
        $zip->Flush(TBSZIP_FILE, $tmp . ".2");
        $zip->Close();

        // Save the merge into a third file
        rename($tmp . ".2", $tmp);
        if ($json) {
            $this->sendJsonMessage('XML', 'finished document', 100);
            $this->sendJsonMessage('CLOSE');
        }
        return [
            'tmp_name'=>$tmp, 
            'type'=>$type, 
            'name'=>$f_name
        ];
    }
}