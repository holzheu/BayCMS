<?php

namespace BayCMS\Util;

class Sendmail
{

    private \BayCMS\Base\BayCMSContext $context;


    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        $this->context = $context;
    }

    /**
     * Wrapper function for send. 
     * Create option-array and executes send
     * @param string $subject
     * @param string $message
     * @param string $to
     * @param string $show_to
     * @param mixed $cc
     * @param bool $show_cc
     * @param mixed $from
     * @param mixed $from_fullname
     * @param mixed $from_fallback
     * @param mixed $return_path
     * @param mixed $attachments Array of form [['name'=>'XXX','tmp_name'=>'xxx','size'=123,type='xxx'],...]
     * @param bool $html
     * @return void
     */
    public function sendMail(
        string $subject,
        string $message,
        string $to,
        string $show_to = true,
        ?string $cc = null,
        bool $show_cc = true,
        ?string $from = null,
        ?string $from_fullname = null,
        ?string $from_fallback = null,
        ?string $return_path = null,
        ?array $attachments = null, 
        bool $html = false
    ) {
        $options = ['to' => $to, 'show_to' => $show_to, 'show_cc' => $show_cc, 'html' => $html];
        if (!is_null($cc))
            $options['cc'] = $cc;
        if (!is_null($from))
            $options['from'] = $from;
        if (!is_null($from_fullname))
            $options['from_fullname'] = $from_fullname;
        if (!is_null($from_fallback))
            $options['from_fallback'] = $from_fallback;
        if (!is_null($return_path))
            $options['return_path'] = $return_path;
        if (!is_null($attachments))
            $options['attachments'] = $attachments;
        $this->send($subject, $message, $options);
    }

    /**
     * Executes the sendmail command
     * @param string $subject
     * @param string $message
     * @param array $options
     * @throws \BayCMS\Exception\fileNotReadable
     * @throws \BayCMS\Exception\missingData
     * @return void
     */
    public function send(string $subject, string $message, array $options = [])
    {
        if (!is_executable("/usr/sbin/sendmail"))
            throw new \BayCMS\Exception\fileNotReadable("/usr/sbin/sendmail is not executable");


        if (!($options['from'] ?? '')) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select email,kommentar from benutzer where id=$1',
                [$this->context->getUserId()]
            );
            [$from, $from_fullname] = pg_fetch_row($res, 0);
        } else
            $from = $options['from'];

        if ($options['from_fullname'] ?? '')
            $from_fullname = $options['from_fullname'];

        if (!preg_match("/^[_\.0-9a-z-]+@[0-9a-z][-0-9a-z\.]+\.[a-z]*$/i", $from) && isset($options['from_fallback']))
            $from = $options['from_fallback'];

        if (!preg_match("/^[_\.0-9a-z-]+@[0-9a-z][-0-9a-z\.]+\.[a-z]*$/i", $from)) {
            throw new \BayCMS\Exception\missingData("No sender address");
        }

        if (!isset($options['show_to']))
            $options['show_to'] = 1;
        if (!isset($options['show_cc']))
            $options['show_cc'] = 1;
        if (!isset($options['return_path']))
            $options['return_path'] = $from;

        $to_email[0] = '';
        $chunks_nr = 0;
        $to_header = array(
            'to' => '',
            'cc' => ''
        );
        $to_count = 0;
        foreach ($to_header as $key => $value) {
            if (!isset($options[$key]))
                continue;
            $to = $options[$key];
            if (!is_array($to))
                $to = preg_split('/ *, */', $to);
            for ($i = 0; $i < count($to); $i++) {
                if (!$to[$i])
                    continue;
                $to[$i] = trim($to[$i]);
                if (!preg_match("/^[_\.0-9a-z-]+@[0-9a-z][-0-9a-z\.]+\.[a-z]*$/i", $to[$i]))
                    trigger_error("$to[$i] is not a valid mail address", 'warning');
                else {
                    $to_email[$chunks_nr] .= ($to_email[$chunks_nr] ? ',' : '') . $to[$i];
                    if ($options['show_' . $key])
                        $to_header[$key] .= ($to_header[$key] ? ', ' : '') . $to[$i];
                    $to_count++;
                    if ($to_count > 49) {
                        $chunks_nr++;
                        $to_count = 0;
                    }
                }
            }
        }
        if (!$to_email[0])
            throw new \BayCMS\Exception\missingData("No valid recipient address");

        for ($j = 0; $j < count($to_email); $j++) {
            $fp = popen('/usr/sbin/sendmail -f' . $from . ' ' . $to_email[$j], "w");
            if (!$fp)
                throw new \BayCMS\Exception\fileNotReadable("Failed to start sendmail!");

            $eol = PHP_EOL;
            mb_internal_encoding('UTF-8');
            if ($to_header['to'])
                fputs($fp, "To:" . $to_header['to'] . $eol);
            if ($to_header['cc'])
                fputs($fp, "CC:" . $to_header['cc'] . $eol);
            $from_header = $from;
            if (isset($from_fullname))
                $from_header = '"' . mb_encode_mimeheader($from_fullname, 'UTF-8', "Q") . '"' . " <$from>";
            fputs($fp, "From: $from_header$eol");
            fputs($fp, "Reply-To: $from_header$eol");
            if ($options['return_path'])
                fputs($fp, "Return-Path: <$options[return_path]>" . $eol); // Return path for errors
            fputs($fp, "X-Sender: <$from>" . $eol);
            fputs($fp, "X-Mailer: PHP/" . phpversion() . $eol); // mailer
            fputs($fp, "X-Priority: 3" . $eol); //
            fputs($fp, "MIME-Version: 1.0$eol");
            fputs($fp, "Subject: " . mb_encode_mimeheader($subject, 'UTF-8', "Q") . $eol);
            if (isset($options['attachments']) && is_array($options['attachments']) && count($options['attachments']))
                $with_attachment = 1;
            else
                $with_attachment = 0;
            if ($with_attachment) {
                $mime_boundary = "-----=" . md5(uniqid(mt_rand(), 1));
                fputs($fp, 'Content-Type: multipart/mixed; charset="' . 'UTF-8' . '"; boundary="' . $mime_boundary . "\"$eol");
                fputs($fp, "This is a multi-part message in MIME format.$eol$eol");
                fputs($fp, "--" . $mime_boundary . $eol);
            }

            if ($options['html'] ?? false)
                fputs($fp, 'Content-Type: text/html; charset=' . 'UTF-8' . $eol);
            else
                fputs($fp, 'Content-Type: text/plain; charset=' . 'UTF-8' . $eol);
            fputs($fp, 'Content-Transfer-Encoding: 8bit' . $eol . $eol);
            if ($options['html'] ?? false)
                fputs($fp, wordwrap($message, 600, $eol));
            else
                fputs($fp, wordwrap(str_replace([
                    "\n",
                    "\r",
                    '<br />'
                ], [
                    '',
                    '',
                    $eol
                ], nl2br($message)), 600, $eol));
            fputs($fp, $eol);
            if ($with_attachment) {
                for ($i = 0; $i < count($options['attachments']); $i++) {
                    if ($fid = fopen($options['attachments'][$i]['tmp_name'], 'r')) {
                        fputs($fp, "--" . $mime_boundary . $eol);
                        fputs($fp, "Content-Disposition: attachment; filename=\"" . mb_encode_mimeheader($options['attachments'][$i]['name'], 'UTF-8', "Q") . "\"$eol");
                        fputs($fp, "Content-Length: " . $options['attachments'][$i]['size'] . "$eol");
                        fputs($fp, "Content-Type: " . $options['attachments'][$i]['type'] . "; name=\"" . mb_encode_mimeheader($options['attachments'][$i]['name'], 'UTF-8', "Q") . "\"$eol");
                        fputs($fp, "Content-Transfer-Encoding: base64$eol$eol");
                        fputs($fp, chunk_split(base64_encode(fread($fid, $options['attachments'][$i]['size']))) . $eol);
                        fclose($fid);
                    }
                }
                fputs($fp, "--" . $mime_boundary . "--");
                fputs($fp, $eol);
            }
            pclose($fp);
        }

    }



}