<?php

namespace BayCMS\Util;


class PDFWriter extends XMLDocWriter
{

    private string $tmpdir;
    private array $templates;

    private string $background;

    /**
     * Summary of __construct
     * 
     * 
     * @param array $values Array of Arrays [[page1_data_array], [page2_data_array]...]
     * @param mixed $templates String or Array (when more than one template page is required)
     * @param string $background Path to a background PDF
     */
    public function __construct(array $values, mixed $templates, string $background='')
    {
        $this->background=$background;
        $this->tmpdir=sys_get_temp_dir();
        $this->values = $values;
        if (!is_array($templates)) {
            $this->templates = [];
            for ($i = 0; $i < count($values); $i++) {
                $this->templates[] = $templates;
            }
        } else
            $this->templates = $templates;
   }


    private function createXFDF($file, $info)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">' . "\n" .
            '<fields>' . "\n";
        foreach ($info as $field => $val) {
            if(is_array($val)) continue;
            $data .= '<field name="' . $field . '">' . "\n";
            $data .= '<value>' . htmlspecialchars($val) . '</value>' . "\n";
            $data .= '</field>' . "\n";
        }
        $data .= '</fields>' . "\n" .
            '<ids original="' . md5($file) . '" modified="' . time() . '" />' . "\n" .
            '<f href="' . $file . '" />' . "\n" .
            '</xfdf>' . "\n";

        return $data;
    }

    private function writePDFPage($values, $file)
    {
        if (!is_readable($file)) {
            throw new \BayCMS\Exception\notFound("File $file not found");
        }
        $xfdf = $this->createXFDF($file, $values);
        $pdf_fn = tempnam($this->tmpdir, "pdf_blatt");
        $fdf_fn = tempnam($this->tmpdir, 'fdf');
        $fp = fopen($fdf_fn, 'w');
        fwrite($fp, $xfdf);
        fclose($fp);
        system('pdftk ' . "$file" . ' fill_form ' . $fdf_fn . " output $pdf_fn flatten");
        //echo 'pdftk '."$file".' fill_form '. $fdf_fn. " output $pdf_fn flatten\n";
        unlink($fdf_fn); // delete temp file
        return $pdf_fn;
    }

    function joinPDFPages($pdfs)
    {
        $pdf = tempnam($this->tmpdir, 'pdf_full');
        $files = implode(" ", $pdfs);
        system("pdftk $files cat output $pdf");
        return $pdf;
    }

    function write($json = 0)
    {
        $matches = [];
        preg_match('|/([^/]+)$|i', $this->templates[0], $matches);
        $f_name =$matches[1];
        $pdf_files = [];
        $pdf = tempnam($this->tmpdir, 'pdf_full');
        $pages = count($this->values);
        for ($i = 0; $i < $pages; $i++) {
            $this->values[$i]['pdf_tk_total_pages'] = $pages;
            $this->values[$i]['pdf_tk_page'] = ($i + 1);
            $file = $this->writePDFPage($this->values[$i], $this->templates[$i]);
            if ($json) {
                $this->sendJsonMessage(
                    'PDF',
                    'processing ' . ($i + 1) . ' of ' . $pages . ')',
                    round(($i + 1) / $pages * 90, 1)
                );

            }
            $pdf_files[] = $file;
        }
        system("pdftk " . implode(' ', $pdf_files) . " cat output $pdf");
        foreach ($pdf_files as $f) {
            unlink($f);
        }
        if($this->background && is_readable($this->background)){
            $pdf2 = tempnam($this->tmpdir, 'pdf_full');
            system("pdftk $pdf background \"".$this->background."\" output $pdf2");
            unlink($pdf);
            $pdf=$pdf2;
        }

        if ($json) {
            $this->sendJsonMessage('PDF', 'finished pdf', 100);
            $this->sendJsonMessage('CLOSE');
        }
        return [
            'tmp_name' => $pdf,
            'type' => 'pdf',
            'name' => $f_name
        ];
    }



}