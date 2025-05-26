<?php
namespace BayCMS\Page;

class Email extends Document
{

    private int $id_run;
    private string $sender;

    /**
     * Creates the domain to list and edit the emails
     * @return void
     */
    public function createDomain()
    {
        if (!isset($this->qb))
            $this->createQB();
        $this->domain = new \BayCMS\Fieldset\Domain(
            context: $this->context,
            table: $this->db_table,
            uname: $this->db_table
        );
        $this->domain->setListProperties(export_buttons: false);

        $this->domain->addField(new \BayCMS\Field\Hidden($this->context, name: 'id_query', default_value: $this->id_query));

        if (isset($_POST['id_query']))
            $_POST['id_parent'] = $_POST['id_query'];

        $this->domain->addField(new \BayCMS\Field\Comment(
            $this->context,
            'query',
            'Abfrage: <a href="' . $_SERVER['SCRIPT_NAME'] . '/Query?id=' . $this->id_query . '">' . $this->qb->getName() . '</a>'
        ));
        $this->domain->addField(
            new \BayCMS\Field\TextInput($this->context, name: 'Name', non_empty: 1),
            list_field: 1,
            search_field: 1
        );
        $this->domain->addField(new \BayCMS\Field\Select(
            $this->context,
            name: 'Mode',
            values: [
                ['inc', $this->t('incremental', 'incrementell')],
                ['full', $this->t('always full', 'immer alle')]
            ],
            non_empty: 1
        ), list_field: 1);


        $this->domain->addField(new \BayCMS\Field\TextInput(
            $this->context,
            name: 'subject',
            description: $this->t('Subject', 'Betreff'),
            non_empty: 1
        ));
        $this->domain->addField(new \BayCMS\Field\Textarea(
            $this->context,
            name: 'message',
            description: $this->t('Message', 'Nachrichtentext'),
            non_empty: 1
        ));
        $this->domain->addField(new \BayCMS\Field\Select(
            $this->context,
            name: 'email_type',
            description: $this->t('E-Mail Type', 'E-Mail Typ'),
            values: ['text', 'html'],
            post_input: '
            <script language="javascript" type="text/javascript" src="/baycms-tinymce4/tinymce.min.js"></script>
            <script>
            function set_editor(){
               if($("#eform_email_type").val()=="html"){
                   tinyMCE.init({
                     selector : "#eform_message",
                     relative_urls : false,
                     remove_script_host : false,
                     plugins : "fullscreen,link,paste",
                         paste_auto_cleanup_on_paste : true,
                         toolbar: " bold italic subscript superscript link | bullist numlist | fullscreen", 
                         menubar:false,
                         statusbar: false,
                         language : "' . $this->context->lang . '",	    
                         debug : false,
                         valid_elements : "p,strong/b.italic/i,ul,ol,li,sub,sup,a[href|target],br,table,tr,th,td,h1,h2,h3,h4,h5,h6,span"	    
                    });
                 
               } else {
                   tinymce.remove("#eform_message");
                   v=$("#eform_message").val();
                   $("#eform_message").val($(v).text());
             
               }
            }
            function set_editor_on_change(){
               if($("#eform_email_type").val()=="html"){
                   v=$("#eform_message").val().replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, \'$1\'+ \'<br>\' +\'$2\');
                   $("#eform_message").val(v);
               }
               set_editor();	
            }
            $("#eform_email_type").change(set_editor);
            $(document).ready(set_editor);
          </script>           
            
            '
        ));

        $this->domain->addField(new \BayCMS\Field\Comment(
            $this->context,
            name: 'replacement',
            description: $this->qb->getReplacementTable($this->db_table == 'qb2_document'),
            not_in_table: true
        ));
        $values = [];
        $default = [];
        foreach ($this->qb->getFieldList() as $name => $desc) {
            $values[] = [$name, '${' . $name . '} - ' . $desc];
            $default[] = $name;
        }
        $this->domain->addField(new \BayCMS\Field\SelectCheckbox(
            $this->context,
            'fields',
            $this->t('Fields used in the email', 'Genutzte Felder'),
            values: $values,
            help: $this->t(
                'Select all fields you use for replacement. This will make the replacement work even if the field is deselected in the query.',
                'Selektieren Sie alle Felder, die für die Ersetzungen genutzt werden. Das stellt sicher, dass die Ersetzung auch noch funktioniert, falls das entsprechende Feld in der Abfrage abgewählt wurde.'
            ),
            default_value: $default
        ));
        $this->domain->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            name: 'no_save',
            description: $this->t('Do not save E-Mail', 'E-Mail nicht speichern'),
        ));
        $this->domain->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            name: 'no_pers',
            description: $this->t('Do not personalize E-Mail', 'E-Mail nicht personalisieren'),
        ));
        $email_fields = [];
        foreach ($this->qb->getEmailFields() as $fname) {
            $email_fields[] = [
                $fname,
                $this->qb->getField($fname)->getDescription(false)
            ];
        }
        $this->domain->addField(new \BayCMS\Field\SelectMulti(
            $this->context,
            name: 'email_fields',
            non_empty: 1,
            values: $email_fields,
            description: $this->t('Recipients', 'Empfänger'),
        ));
        $this->domain->addField(new \BayCMS\Field\Email(
            $this->context,
            name: 'to_email',
            description: $this->t('Additional recipient', 'Zusätzlicher Empfänger'),
        ));
        $this->domain->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            name: 'to_show',
            description: $this->t(
                'Show reciptients in E-Mail header',
                'Empfänger in Mail anzeigen'
            ),
            default_value: true
        ));
        $this->domain->addField(new \BayCMS\Field\Email(
            $this->context,
            name: 'cc_email',
            description: $this->t('CC recipient', 'CC Adresse'),
        ));
        $this->domain->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            name: 'cc_show',
            description: $this->t(
                'Show CC reciptients in E-Mail header',
                'CC Empfänger in Mail anzeigen'
            )
        ));
        $this->domain->addField(new \BayCMS\Field\UploadObjectFiles(
            $this->context,
            name: 'attachments',
            description: $this->t('Attachments', 'Anhänge'),
        ));


        $this->domain->addField(new \BayCMS\Field\Comment(
            $this->context,
            name: 'runs',
            description: "<h4>Runs</h4>",
            not_in_form: true
        ));



    }

    /**
     * Get the values-array used for replacement
     * @param mixed $id id of email
     * @param mixed $id_row id of relevant row. If null all values of the list are returned
     * @return array[]
     */
    public function getValueArray($id, $id_row = null)
    {
        $this->load($id);
        $this->createQB();
        $exclude = ($this->values['mode'] == 'inc' ? $id : false);
        $list = $this->qb->getList($exclude, $id_row, email_fields: true);
        $qb_values = $list->getValues();

        $values = [];
        $i = 0;
        foreach ($qb_values as $row) {
            foreach ($row as $key => $v) {
                $values[$i][$key] = $v;
            }
            $i++;
        }
        return $values;
    }

    /**
     * Load the email
     * @param mixed $id
     * @throws \BayCMS\Exception\notFound
     * @return void
     */
    public function load($id)
    {
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select e.*
            from qb2_email e, objekt o, file f
        where e.id=o.id and o.geloescht is null
        and e.id=$1',
            [$id]
        );
        if (!pg_num_rows($res))
            throw new \BayCMS\Exception\notFound('There is no email with id=' . $id);
        $this->values = pg_fetch_array($res, 0);
    }

    /**
     * Create one mail based on a value array
     * @param mixed $v value array
     * @return array<array|mixed>
     */
    private function createMail($v)
    {
        $subject = $this->values['subject'];
        $message = $this->values['message'];
        $id = $v['__id'];
        if ($this->values['no_pers'] != 't') {
            $search = [];
            $replace = [];
            foreach ($v as $key => $value) {
                if (is_array($value))
                    continue;
                $search[] = '${' . $key . '}';
                $replace[] = $value;
            }


            $subject = str_replace($search, $replace, $subject);
            $message = str_replace($search, $replace, $message);
        }
        $message=wordwrap($message,75,PHP_EOL);
        $to = [];
        if ($this->values['email_fields']) {
            $email_fields = explode(',', $this->values['email_fields']);
            foreach ($email_fields as $f) {
                $to[] = $v[$f];
            }
        }
        if ($this->values['to_email'])
            $to[] = $this->values['to_email'];

        return [$subject, $message, $to, $id];
    }


    /**
     * Send a json message (for progress bar)
     * @param mixed $id
     * @param mixed $message
     * @param mixed $progress
     * @return void
     */
    private function sendJsonMessage($id, $message = '', $progress = 0)
    {
        $d = ['message' => $message, 'progress' => $progress];

        echo "id: $id" . PHP_EOL;
        echo "data: " . json_encode($d) . PHP_EOL;
        echo PHP_EOL;

        ob_flush();
        flush();
    }

    /**
     * Creates the run-form with the preview of the mails
     * @return string
     */
    private function runForm()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            action: '?aktion=email&id=' . $_GET['id'],
            id_parent: $_GET['id'],
            uname: 'qb2_run',
            table: 'qb2_run',
            submit: $this->t('send mails', 'E-Mails senden')
        );
        $form->addField(new \BayCMS\Field\Hidden(
            $this->context,
            name: 'name',
            default_value: $this->values['name']
        ));
        $out = '<table ' . $this->context->TE->getCSSClass('table') . '><tr><td>';
        $out .= $this->context->TE->getActionLink(
            '?aktion=preview&id=' . $_GET['id'] . '&i=' . (($_GET['i'] ?? 0) - 1),
            $this->t("back", "zurück"),
            '',
            'backward'
        ) . ' ';
        $out .= $this->context->TE->getActionLink(
            '?aktion=preview&id=' . $_GET['id'] . '&i=' . (($_GET['i'] ?? 0) + 1),
            $this->t("forward", "vor"),
            '',
            'forward'
        );
        $out .= "</td></tr>";

        $options = $this->emailOptions();
        $val = $this->getValueArray($_GET['id']);
        for ($i = 0; $i < count($val); $i++) {
            $r = $this->createMail($val[$i]);
            $out .= "<tr><td>TO: " . implode(", ", $r[2]);
            if ($i == ($_GET['i'] ?? 0)) {
                if ($options['cc'] ?? false)
                    $out .= "<br/>CC: " . $options['cc'];
                $out .= "<br/>Subject: " . $r[0] . "<br/><br/>";
                if ($this->values['email_type'] == 'html')
                    $out .= $r[1];
                else
                    $out .= nl2br(wordwrap($r[1]));

                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select get_filetype_image(f.name),non_empty(f.de,f.en) as link,f.name,f.id from file f, objekt o where
                    o.id=f.id_obj and o.id_obj=$1',
                    [$_GET['id']]
                );
                if (pg_num_rows($res)) {
                    $out .= "<br/><br/>" . $this->t('Attachments', 'Dateianhänge') . "<br/>";

                    for ($i = 0; $i < pg_num_rows($res); $i++) {
                        $r = pg_fetch_array($res, $i);
                        $out .= "<a href=\"/" . $this->context->org_folder . "/" . $this->context->lang . "/top/gru/get.php?f=";
                        $out .= urlencode($this->context->BayCMSRoot . '/' . $r['name']);
                        $out .= "&n=";
                        $out .= urlencode($r['link']);
                        $out .= "\" target=\"_blank\">$r[get_filetype_image] $r[link]</a> ";
                        $out .= "<br/>\n";

                    }
                }
            }
            $out .= "</td></tr>";
        }
        $out .= "</table>";
        $form->addField(new \BayCMS\Field\Comment(
            $this->context,
            name: 'preview',
            description: $out
        ));
        for ($i = 0; $i < 3; $i++) {
            $form->addField(new \BayCMS\Field\UploadObjectFiles(
                $this->context,
                name: 'attr' . $i,
                description: 'Attachment ' . ($i + 1),
                path: 'inc/qb2/att'
            ));
        }
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            name: 'Sender',
            no_add_to_query: true,
            values: [
                $this->context->get('row1', 'email'),
                $this->context->get('row1', 'user_email')
            ]
        ));
        if (($_GET['aktion'] ?? '') == 'email') {
            $error = $form->setValues($_POST);
            echo "xxx";

            if (!$error) {
                $this->id_run = $form->save();
                $res = pg_query_params(
                    $this->context->getRwDbConn(),
                    'select non_empty(f.de,f.en) as link,f.name,f.id
                    from file f, objekt o where f.id=o.id and o.id_obj=$1',
                    [$_GET['id']]
                );
                for ($i = 0; $i < pg_num_rows($res); $i++) {
                    $r = pg_fetch_array($res, $i);
                    $file = new \BayCMS\Base\BayCMSFile($this->context);
                    $file->set([
                        'id_parent' => $this->id_run,
                        'path' => 'inc/qb2/att',
                        'source' => $this->context->BayCMSRoot . '/' . $r['name'],
                        'name' => $r['link'],
                        'de' => $r['link'],
                        'add_id_obj' => true
                    ]);
                    $file->save();
                }
                $this->sender = $_POST['sender'];
                sleep(0.1);
                return '';
            }

        }
        return $form->getForm('Preview');

    }

    /**
     * Returns array of email options used by mailer
     * @param mixed $sender
     * @return array{from: mixed, html: bool, show_cc: bool, show_to: bool}
     */
    private function emailOptions($sender = '')
    {
        $options = [
            'from' => $sender,
            'show_to' => $this->values['to_show'] == 't',
            'show_cc' => $this->values['cc_show'] == 't',
            'html' => $this->values['email_type'] == 'html'
        ];
        if ($this->values['cc_email'])
            $options['cc'] = $this->values['cc_email'];
        return $options;
    }

    /**
     * Helper function to get array of attachments used for sendmail
     * @param BayCMS\Base\BayCMSContext $context 
     * @param int $id_run
     * @return array{name: mixed, size: bool|int, tmp_name: string, type: mixed[]|bool}
     */
    static public function getAttachment(\BayCMS\Base\BayCMSContext $context, int $id_run): array|bool
    {
        $res = pg_query_params(
            $context->getRwDbConn(),
            'select get_mime_type(f.name),non_empty(f.de,f.en) as link,f.name,f.id
			from file f, objekt o where 
            f.id=o.id and o.id_obj is not null and o.id_obj=$1',
            [$id_run]
        );
        if (!pg_num_rows($res))
            return false;
        $attachments = [];
        for ($j = 0; $j < pg_num_rows($res); $j++) {
            $r = pg_fetch_array($res, $j);
            $filename = $context->BayCMSRoot . '/' . $r['name'];
            $size = filesize($filename);
            if ($size) {
                $attachments[] = [
                    'tmp_name' => $filename,
                    'size' => $size,
                    'type' => $r['get_mime_type'],
                    'name' => $r['link']
                ];
            }
        }
        return $attachments;
    }

    /**
     * Send the emails
     * @return never
     */
    public function send()
    {

        $sender = $_GET['sender'];
        if (!preg_match("/^[_\.0-9a-z-]+@[0-9a-z][-0-9a-z\.]+\.[a-z]{2,3}$/i", $sender)) {
            $this->sendJsonMessage("CLOSE", "Der Absender ($sender) ist keine gültige e-Mail-Adresse!");
            exit();
        }

        $id_run = $_GET['id_run'] ?? false;
        if (!$id_run) {
            $this->sendJsonMessage("CLOSE", "Missing id_run");
            exit();
        }

        $val = $this->getValueArray($_GET['id']);
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        pg_query($this->context->getRwDbConn(), 'begin');
        pg_prepare(
            $this->context->getRwDbConn(),
            'insert_qb2_rows',
            'insert into qb2_rows(id_run,id_obj,id_row) values($1,$2,$3)'
        );
        pg_prepare(
            $this->context->getRwDbConn(),
            'insert_qb2_emails',
            'insert into qb2_emails(id_run,id_obj,id_row,to_address,cc_address,header,subject,message,email_type,sendtime) 
            values($1,$2,$3,$4,$5,$6,$7,$8,$9,now())'
        );
        $mailer = new \BayCMS\Util\Sendmail($this->context);
        $options = $this->emailOptions($sender);
        $attachments=Email::getAttachment( $this->context, $id_run);
        if($attachments) $options['attachments']=$attachments;

        $i = 0;
        if ($this->values['no_pers'] == 't') {
            $options['show_to'] = false;
            $to_addresses = [];
            $ids = [];
            foreach ($val as $v) {
                [$subject, $message, $to, $ids[]] = $this->createMail($v);
                array_push($to_addresses, $to);
            }
            $offset = 0;
            $i = 0;
            while ($offset < count($to_addresses)) {
                $to = array_slice($to_addresses, $offset, 50);
                $this->sendJsonMessage('XML', 'processing ' . ($i + 1) . ' of ' . ceil(count($to_addresses) / 50) . ')', ($i + 1) / count($to_addresses) / 50 * 90);
                $i++;
                $options['to'] = implode(",", $to);
                $mailer->send($subject, $message, $options);
                for ($i = $offset; $i < min(count($ids, $offset + 50)); $i++) {
                    $id_obj = ($this->qb->isObject() ? $ids[$i] : null);
                    pg_execute(
                        $this->context->getRwDbConn(),
                        'insert_qb2_rows',
                        [$id_run, $id_obj, $ids[$i]]
                    );
                    if ($this->values['no_save'] != 't') {
                        $header = 'From: ' . $sender . "\n";
                        if ($options['show_to'])
                            $header .= 'To: ' . $options['to'] . "\n";
                        if ($options['show_cc'])
                            $header .= 'CC: ' . $options['cc'] . "\n";
                        //id_run,id_obj,id_row,to_address,cc_address,header,subject,message,email_type

                        pg_execute(
                            $this->context->getRwDbConn(),
                            'insert_qb2_emails',
                            [$id_run, $id_obj, $ids[$i], $options['to'], $options['cc'], $header, $subject, $message, $this->values['email_type']]
                        );
                    }
                }
                $offset += 50;
            }

        } else {
            foreach ($val as $v) {
                [$subject, $message, $to, $id] = $this->createMail($v);

                $this->sendJsonMessage('XML', 'processing ' . ($i + 1) . ' of ' . count($val) . ')', ($i + 1) / count($val) * 90);
                $i++;
                $options['to'] = implode(",", $to);
                $mailer->send($subject, $message, $options);
                $id_obj = ($this->qb->isObject() ? $id : null);
                pg_execute(
                    $this->context->getRwDbConn(),
                    'insert_qb2_rows',
                    [$id_run, $id_obj, $id]
                );
                if ($this->values['no_save'] != 't') {
                    $header = 'From: ' . $sender . "\n";
                    if ($options['show_to'])
                        $header .= 'To: ' . $options['to'] . "\n";
                    if ($options['show_cc'])
                        $header .= 'CC: ' . $options['cc'] . "\n";
                    //id_run,id_obj,id_row,to_address,cc_address,header,subject,message,email_type

                    pg_execute(
                        $this->context->getRwDbConn(),
                        'insert_qb2_emails',
                        [$id_run, $id_obj, $id, $options['to'], $options['cc']??'', $header, $subject, $message, $this->values['email_type']]
                    );
                }


            }
        }

        $this->sendJsonMessage('XML', 'finished mailing', 100);
        $this->sendJsonMessage('CLOSE');
        pg_query($this->context->getRwDbConn(), 'commit');

        exit();

    }

    /**
     * Page function. Prints out all content (HTML, JSON)
     * @param string $pre_content
     * @param string $post_content
     * @return void
     */
    public function page(string $pre_content = '', string $post_content = '')
    {
        $action = $_GET['aktion'] ?? false;
        if ($action == 'json') {
            $this->send();
        }



        $this->context->printHeader();
        echo $pre_content;
        if ($_GET['id'] ?? null)
            $this->load($_GET['id']);


        if ($action) {
            $this->createDomain();
            $edit = $this->domain->getEdit();
            if ($edit['edit']) {
                echo $edit['html'];
                $this->context->printFooter();
            } else {
                $_GET['id'] = $edit['id'];
                if ($_GET['id']) {
                    $this->load($_GET['id']);
                    if (($_GET['aktion'] != 'email'))
                        $_GET['aktion'] = 'preview';
                }

            }
        }


        if ($_GET['id'] ?? null) {
            echo '<h3>' . $this->values['name'] . '</h3>';
            echo $this->context->TE->getActionLink(
                '?id=' . $_GET['id'] . '&aktion=edit',
                $this->t('Edit', 'Bearbeiten'),
                '',
                'edit'
            );
            if ($num = $this->runCheck($_GET['id'])) {
                if (in_array($_GET['aktion'] ?? false, ['preview', 'email'])) {
                    $run_form = $this->runForm();
                    if ($run_form) {
                        echo $run_form;
                        $this->context->printFooter();
                    } else if (($_GET['aktion'] ?? false) == 'email') {
                        $this->printJSEventSource("'?id=$_GET[id]&sender=" . $this->sender .
                            "&id_run=" . $this->id_run . "&aktion=json'", $this->values['mode'] == 'inc');
                        $this->context->printFooter();
                    }
                } else {
                    echo ' ' . $this->context->TE->getActionLink(
                        '?id=' . $_GET['id'] . '&aktion=preview',
                        $this->t('Preview', 'Vorschau'),
                        '',
                        'eye-open'
                    );
                    $this->context->TE->printMessage($num . ' ' . $this->t('new datasets', 'neue Datensätze'));
                }
            } else {
                $this->context->TE->printMessage($this->t('No new datasets', 'Keine neuen Datensätze'));
            }



            if (isset($_GET['id_run'])) {
                $list = new \BayCMS\Fieldset\BayCMSList(
                    context: $this->context,
                    from: 'qb2_run r, qb2_emails t',
                    where: 'r.id=t.id_run and r.id=' . $_GET['id_run'],
                    qs: 'id=' . $_GET['id'] . '&id_run=' . $_GET['id_run'],
                    jquery_row_click: true
                );
                $list->addField(new \BayCMS\Field\TextInput(
                    $this->context,
                    name: 'Sendtime',
                    sql: "to_char(t.sendtime,'YYYY-MM-DD HH24:MI')"
                ));
                $list->addField(new \BayCMS\Field\TextInput(
                    $this->context,
                    name: 'to_address',
                    description: 'To'
                ));
                $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'Subject'));
                $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'Message'));
                echo "<h3>" . $this->t('Sent E-Mails', 'Versendete E-Mails') . "</h3>";
                echo $list->getTable();
                echo $this->context->TE->getActionLink('?id=' . $_GET['id'], $this->t('back to mailing overview', 'zurück zur Liste der Mailaktionen'), '', 'arrow-left');


            } else {
                $list = new \BayCMS\Fieldset\BayCMSList(
                    context: $this->context,
                    from: 'objekt o, qb2_run e, benutzer b',
                    where: 'o.id_benutzer=b.id and o.id=e.id and o.id_obj=' . $_GET['id'],
                    qs: 'id=' . $_GET['id'],
                    id_name: 'id_run',
                    id_query: 'e.id',
                    order_by: ['o.ctime desc'],
                    jquery_row_click: true
                );
                $list->addField(new \BayCMS\Field\TextInput(
                    $this->context,
                    name: 'Date',
                    sql: "to_char(o.ctime,'YYYY-MM-DD HH24:MI')"
                ));
                $list->addField(new \BayCMS\Field\TextInput(
                    $this->context,
                    name: 'User',
                    sql: 'b.kommentar'
                ));
                echo "<h3>" . $this->t('Previous Mailings', 'Bisherige Versandaktionen') . "</h3>";
                echo $list->getTable();
                echo $this->context->TE->getActionLink('?', $this->t('back to list of e-mail templates', 'zurück zur Liste der E-Mailvorlagen'), '', 'arrow-left');

            }
            //print Table with runs...




            $this->context->printFooter();
        }

        $this->createList();
        echo $this->context->TE->getActionLink(
            '?check=1',
            $this->t('Check for new datasets', 'Auf neue Datensätze prüfen'),
            '',
            'ok'
        ) . '<br/>';
        if ($_GET['check'] ?? false)
            $this->runCheck();
        echo $this->list->getTable();
        echo $post_content;
        $this->context->printFooter();
    }


}