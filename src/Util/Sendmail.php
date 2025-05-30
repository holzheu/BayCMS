<?php

namespace BayCMS\Util;

use \PHPMailer\PHPMailer\PHPMailer;

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
     * @param bool $show_to
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
        bool $show_to = true,
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

        $from_fullname = '';
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

        if (!PHPMailer::validateAddress($from) && isset($options['from_fallback']))
            $from = $options['from_fallback'];


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

        if(! isset($options['attachments'])) $options['attachments']=[];

        $to_count = 0;
        $mail = new PHPMailer();
        $mail->isSendmail();
        $mail->setFrom($from, $from_fullname);
        $mail->Sender = $options['return_path'];
        $mail->Subject = $subject;
        $mail->CharSet = "UTF-8";
        if ($options['html'] ?? false) {
            $mail->msgHTML($message);
        } else
            $mail->Body = $message;
        for ($i = 0; $i < count($options['attachments']); $i++) {
            $mail->addAttachment($options['attachments'][$i]['tmp_name'], $options['attachments'][$i]['name']);
        }




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
                if (!PHPMailer::validateAddress($to[$i]))
                    trigger_error("$to[$i] is not a valid mail address", 'warning');
                else {
                    if ($options['show_' . $key]) {
                        if ($key == 'to')
                            $mail->addAddress($to[$i]);
                        else
                            $mail->addCC($to[$i]);
                    } else
                        $mail->addBCC($to[$i]);
                    $to_count++;
                    if ($to_count > 49) {
                        $mail->send();
                        $to_count = 0;
                        $mail = new PHPMailer();
                        $mail->isSendmail();
                        $mail->setFrom($from, $from_fullname);
                        $mail->Sender = $options['return_path'];
                        $mail->Subject = $subject;
                        $mail->CharSet = "UTF-8";
                        if ($options['html'] ?? false) {
                            $mail->msgHTML($message);
                        } else
                            $mail->Body = $message;
                        for ($i = 0; $i < count($options['attachments']); $i++) {
                            $mail->addAttachment($options['attachments'][$i]['tmp_name'], $options['attachments'][$i]['name']);
                        }
                        $to_count = 0;
                    }
                }
            }
        }
        if ($to_count)
            $mail->send();

    }



}