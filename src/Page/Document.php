<?php

namespace BayCMS\Page;
use BayCMS\Field\TextInput;

class Document extends Page
{

    protected \BayCMS\Fieldset\QueryBuilder $qb;
    protected \BayCMS\Fieldset\Domain $domain;

    protected \BayCMS\Fieldset\BayCMSList $list;

    protected array $values;
    protected string $class;

    protected string $db_table;
    protected ?int $id_query;


    /**
     * Creates instance of Document
     * @param \BayCMS\Base\BayCMSContext $context
     * @param string $class Name of the QB2 class
     */
    public function __construct(\BayCMS\Base\BayCMSContext $context, string $class)
    {
        $this->class = $class;
        if (get_class($this) == 'BayCMS\\Page\\Email')
            $this->db_table = 'qb2_email';
        else
            $this->db_table = 'qb2_document';
        $this->context = $context;

    }

    /**
     * Create the list for listing all Documents for a given QB2-class
     * @return void
     */
    public function createList()
    {
        $this->list = new \BayCMS\Fieldset\BayCMSList(
            context: $this->context,
            from: $this->db_table . ' t left outer join qb2_check c on t.id=c.id and c.id_lehr=' . $this->context->getOrgId() . ', 
            objekt_verwaltung' . $this->context->getOrgId() . ' o, qb2 q',
            where: 't.id=o.id and t.id_query=q.id and q.class=\'' .
            pg_escape_string($this->context->getDbConn(), $this->class) . '\'',
            id_query: 't.id',
            write_access_query: 'check_objekt(t.id,' . $this->context->getUserId() . ')',
            actions: ['edit'],
            jquery_row_click: true
        );
        $this->list->addField(new TextInput($this->context, name: 'Name'));
        $this->list->addField(new TextInput(
            $this->context,
            name: 'Mode',
            sql: "t.mode||case when t.mode='inc' then 
            case when c.new_datasets is null then '' else
            '<br/>'||
            c.new_datasets||' new datasets (last check: '||
            to_char(c.last_update,'YYYY-MM-DD HH24:MI')||
            ')' end else '' end"
        ));
        $this->list->addField(new TextInput($this->context, name: 'Query', sql: 'q.name'));
    }

    /**
     * Creates the QB2-Object
     * @throws \BayCMS\Exception\missingData
     * @throws \BayCMS\Exception\notFound
     * @return void
     */
    public function createQB()
    {
        if (isset($_REQUEST['id_query'])) {
            $this->id_query = $_REQUEST['id_query'];
        } elseif (isset($this->values['id'])) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select id_query from ' . $this->db_table . ' where id=$1',
                [$this->values['id']]
            );
            if (pg_num_rows($res))
                [$this->id_query] = pg_fetch_row($res, 0);
        }

        if (!isset($this->id_query)) {
            throw new \BayCMS\Exception\missingData('You have to give a query id');
        }
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select class from qb2 where id=$1',
            [$this->id_query]
        );
        if (!pg_num_rows($res))
            throw new \BayCMS\Exception\notFound('Query with ID ' . $this->id_query . ' does not exist');
        [$class] = pg_fetch_row($res, 0);
        $this->qb = new $class($this->context);
        $this->qb->load($this->id_query);
        if (isset($this->values))
            $this->qb->setAdditionalFields(explode(',', $this->values['fields']));

    }

    /**
     * Create the domain to list and edit the Documents based on a QB2-class
     * @return void
     */
    public function createDomain()
    {
        if (isset($_GET['id']))
            $this->load($_GET['id']);
        if (!isset($this->qb))
            $this->createQB();
        $this->domain = new \BayCMS\Fieldset\Domain(
            context: $this->context,
            table: $this->db_table,
            uname: $this->db_table,
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

        $this->domain->addField(new \BayCMS\Field\TextInput($this->context, name: 'Name', non_empty: 1), list_field: 1, search_field: 1);
        $this->domain->addField(new \BayCMS\Field\Select(
            $this->context,
            name: 'Mode',
            values: [
                ['inc', $this->t('incremental', 'incrementell')],
                ['full', $this->t('always full', 'immer alle')]
            ],
            non_empty: 1
        ), list_field: 1);

        $this->domain->addField(new \BayCMS\Field\Select(
            $this->context,
            name: 'min_power',
            description: $this->t('Download allowed for', 'Herunterladbar durch'),
            db_query: 'select power as id,
                     non_empty(' . $this->context->lang . ',' . $this->context->lang2 . ') as description
                     from power where power<=1000 order by 1 desc',
            default_value: 1000,
            non_empty: 1
        ));
        $this->domain->addField(new \BayCMS\Field\Number(
            $this->context,
            name: 'count',
            description: $this->t('Number of datasets per Page', 'Anzahl der Datensätze pro Seite'),
            non_empty: 1,
            default_value: 1
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
            $this->t('Fields used in the document', 'Genutzte Felder'),
            values: $values,
            help: $this->t(
                'Select all fields you use for replacement. This will make the replacement work even if the field is deselected in the query.',
                'Selektieren Sie alle Felder, die für die Ersetzungen genutzt werden. Das stellt sicher, dass die Ersetzung auch noch funktioniert, falls das entsprechende Feld in der Abfrage abgewählt wurde.'
            ),
            default_value: $default
        ));

        $this->domain->addField(new \BayCMS\Field\UploadFile(
            $this->context,
            name: 'template',
            description: $this->t('Template File', 'Template Datei') . ' (pdf, docx, pptx, odt, odp)',
            non_empty: 1,
            preg_match: '/(pdf|docx|odt|odp|pptx)/i'
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
     * @param mixed $id id of document
     * @param mixed $id_row id of relevant row. If null all values of the list are returned
     * @return array[]
     */
    public function getValueArray($id, $id_row = null)
    {
        $this->load($id);
        $this->createQB();
        $exclude = ($this->values['mode'] == 'inc' ? $id : false);
        $list = $this->qb->getList($exclude, $id_row);
        $qb_values = $list->getValues();

        $nr = 1;
        $values = [];
        $i = 0;
        $pages = ceil(count($qb_values) / $this->values['count']);
        foreach ($qb_values as $row) {
            $values[$i]['__ids'][] = $row['__id'];
            $values[$i]['__page_number'] = $i + 1;
            $values[$i]['__total_pages'] = $pages;
            foreach ($row as $key => $v) {
                $values[$i]['f' . $nr . '_' . $key] = $v;
            }
            $nr++;
            if ($nr > $this->values['count']) {
                $i++;
                $nr = 1;
            }
        }
        return $values;
    }

    /**
     * Load the document
     * @param mixed $id
     * @throws \BayCMS\Exception\notFound
     * @throws \BayCMS\Exception\accessDenied
     * @return void
     */
    public function load($id)
    {
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select d.*,f.name as template
            from qb2_document d, objekt o, file f, objekt of
        where d.id=o.id and o.geloescht is null 
        and of.id_obj=o.id and f.id=of.id and f.de=\'template\'
        and d.id=$1',
            [$id]
        );
        if (!pg_num_rows($res))
            throw new \BayCMS\Exception\notFound('There is no document with id=' . $id);
        $this->values = pg_fetch_array($res, 0);
        if ($this->context->getPower() < $this->values['min_power'])
            throw new \BayCMS\Exception\accessDenied('Your rights are not sufficient to read the document');
    }

    /**
     * Simple function to page a document 
     * @param mixed $id id of document
     * @param mixed $id_row id of relevant row. If null all pages are created
     * @return void
     */
    public function pageDocument($id, $id_row)
    {
        try {
            $val = $this->getValueArray($id, $id_row);
        } catch (\Exception $e) {
            $page = new \BayCMS\Page\ErrorPage($this->context, 401, $e->getMessage());
            $page->page();
        }
        if (!count($val)) {
            $page = new \BayCMS\Page\ErrorPage(
                $this->context,
                404,
                $this->t(
                    'Your admission status does not match this document',
                    'Ihr Zulassungsstatus passt nicht zum angeforderten Dokument.'
                )
            );
            $page->page();
        }

        $writer = new \BayCMS\Util\DocWriter(
            $this->context,
            $val,
            $this->context->BayCMSRoot . '/' . $this->values['template']
        );
        $res = $writer->write();
        $writer->send(
            $res['tmp_name'],
            $res['name'],
            true
        );
        //Script exits here
    }

    /**
     * Main page function 
     * @param string $pre_content
     * @param string $post_content
     * @return void
     */
    public function page(string $pre_content = '', string $post_content = '')
    {
        if ($_GET['id_file'] ?? false) {
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            $file->load($_GET['id_file']);
            $f = $file->get();
            if (($_GET['aktion'] ?? false) == 'del') {
                $file->erase();
                $obj = new \BayCMS\Base\BayCMSObject($this->context);
                $obj->load($f['id_parent']);
                $obj->erase();
                unset($_GET['aktion']);
            } else {
                $p = new \BayCMS\Page\BinPage(
                    $this->context,
                    file: $this->context->BayCMSRoot . '/' . $f['full_path'] . '/' . $f['name'],
                    file_name: $f['name']
                );
                $p->page();
            }
        }

        $action = $_GET['aktion'] ?? false;
        if ($action == 'preview' || $action == 'json') {
            $val = $this->getValueArray($_GET['id']);
            $writer = new \BayCMS\Util\DocWriter(
                $this->context,
                $val,
                $this->context->BayCMSRoot . '/' . $this->values['template']
            );
            if ($action == 'preview') {
                $res = $writer->write();
                $writer->send(
                    $res['tmp_name'],
                    $res['name'],
                    true
                );
                //Script exits here
            }
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            $res = $writer->write(true); //sends JSON

            $new_name = tempnam($this->context->BayCMSRoot . '/tmp', 'qb2');
            rename($res['tmp_name'], $new_name);
            $res['tmp_name'] = $new_name;
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $obj->set([
                'de' => $this->values['name'],
                'id_parent' => $_GET['id'],
                'uname' => 'qb2_run'
            ]);
            $id_run = $obj->save();
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            $file->set([
                'de' => $this->values['name'],
                'id_parent' => $id_run,
                'source' => $res['tmp_name'],
                'name' => $res['name'],
                'path' => 'inc/gru/qb2',
                'add_id_obj' => 1
            ]);
            $file->save();
            pg_query_params(
                $this->context->getRwDbConn(),
                'insert into qb2_run(id,name) values ($1,$2)',
                [$id_run, $this->values['name']]
            );

            pg_query($this->context->getRwDbConn(), 'begin');
            pg_prepare(
                $this->context->getRwDbConn(),
                'insert_qb2_rows',
                'insert into qb2_rows(id_run,id_obj,id_row) values($1,$2,$3)'
            );
            foreach ($val as $v) {
                foreach ($v['__ids'] as $id) {
                    $id_obj = ($this->qb->get('object') ? $id : null);
                    pg_execute(
                        $this->context->getRwDbConn(),
                        'insert_qb2_rows',
                        [$id_run, $id_obj, $id]
                    );
                }

            }
            pg_query($this->context->getRwDbConn(), 'commit');
            if (is_readable($res['tmp_name']))
                unlink($res['tmp_name']);
            exit();
        }

        $this->context->printHeader();
        echo $pre_content;
        if ($action) {
            $this->createDomain();
            $edit = $this->domain->getEdit();
            if ($edit['edit']) {
                echo $edit['html'];
                $this->context->printFooter();
            } else {
                $_GET['id'] = $edit['id'];
            }
        }

        if ($action == 'write') {
            $this->printJSEventSource();
            $this->context->printFooter();
        }

        if (isset($_GET['id'])) {
            $num = $this->runCheck($_GET['id']);
            echo '<h3>' . $this->values['name'] . '</h3>';
            echo $this->context->TE->getActionLink(
                '?id=' . $_GET['id'] . '&aktion=edit',
                $this->t('Edit', 'Bearbeiten'),
                '',
                'edit'
            );
            if ($num) {
                echo ' ';
                echo $this->context->TE->getActionLink(
                    '?id=' . $_GET['id'] . '&aktion=preview',
                    $this->t('Preview', 'Vorschau'),
                    '',
                    'eye-open'
                );
                echo ' ';
                echo $this->context->TE->getActionLink(
                    '?id=' . $_GET['id'] . '&aktion=write',
                    $this->t('Write document', 'Dokument schreiben'),
                    ' onClick="return confirm(\'' . $this->t('Are you sure', 'Sind Sie sicher') . '?\')"',
                    'save'
                );
                echo '<br/>';
                $this->context->TE->printMessage($num . ' ' . $this->t('new datasets', 'neue Datensätze'));
                echo $this->qb->getList()->getTable();
            } else {
                $this->context->TE->printMessage($this->t('Nothing to do', 'Nichts zu tun'));
            }



            //print Table with runs...
            $list = new \BayCMS\Fieldset\BayCMSList(
                context: $this->context,
                from: 'objekt of, file f, objekt o, qb2_document d, benutzer b',
                where: 'f.id=of.id and of.id_benutzer=b.id and of.id_obj=o.id and o.id_obj=d.id and d.id=' . $_GET['id'],
                actions: ['del'],
                write_access_query:'check_objekt(of.id,'.$this->context->getUserId().')',
                qs: 'id=' . $_GET['id'],
                id_name: 'id_file',
                id_query: 'f.id',
                order_by: ['o.ctime desc'],
                jquery_row_click: true
            );
            $list->addField(new TextInput($this->context, name: 'Date', sql: "to_char(o.ctime,'YYYY-MM-DD HH24:MI')"));
            $list->addField(new TextInput($this->context, name: 'User', sql: 'b.kommentar'));
            echo "<h3>".$this->t('Created files','Erzeugte Dateien')."</h3>
            <p>".$this->t('Just click on the row to download the file','Zum Downlad bitte auf die Zeile klicken').'</p>';
            echo $list->getTable();
            echo $this->context->TE->getActionLink('?', $this->t('back to list of document templates', 'zurück zur Liste der Dokumentvorlagen'), '', 'arrow-left');



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


    /**
     * runs the run-check for new rows
     * @param mixed $id
     * @return int
     */
    protected function runCheck($id = null)
    {
        $ids = [];
        if (is_null($id)) {
            $res = pg_query(
                $this->context->getRwDbConn(),
                'select t.id from ' .
                $this->db_table . ' t, objekt_verwaltung' . $this->context->getOrgId() . ' o, qb2 q' .
                ' where t.mode=\'inc\' and ' .
                't.id=o.id and t.id_query=q.id and q.class=\'' .
                pg_escape_string($this->context->getRwDbConn(), $this->class) . '\''
            );
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                [$ids[]] = pg_fetch_row($res, $i);
            }
        } else {
            $ids[] = $id;
        }

        pg_prepare(
            $this->context->getRwDbConn(),
            'insert_check',
            'insert into qb2_check(id,id_lehr,new_datasets,last_update)
            values($1,$2,$3,now())'
        );
        pg_prepare(
            $this->context->getRwDbConn(),
            'delete_check',
            'delete from qb2_check where id=$1 and id_lehr=$2'
        );

        $count = 0;
        for ($i = 0; $i < count($ids); $i++) {
            $this->load($ids[$i]);
            $this->createQB();
            $list = $this->qb->getList($this->values['mode'] == 'inc' ? $ids[$i] : false);

            pg_execute(
                $this->context->getRwDbConn(),
                'delete_check',
                [$ids[$i], $this->context->getOrgId()]
            );
            pg_execute(
                $this->context->getRwDbConn(),
                'insert_check',
                [$ids[$i], $this->context->getOrgId(), $list->getNumRows()]
            );
            $count += $list->getNumRows();
        }
        return $count;
    }

    /**
     * JSEventSource for progress bar
     * @param mixed $source
     * @param mixed $redirect
     * @return void
     */
    protected function printJSEventSource($source = '', $redirect = true)
    {
        if (!$source)
            $source = "'?id=" . $_GET['id'] . "&aktion=json'";
        echo ' <div id="contact-results">Starting...</div>
        <br />

        <progress id="contact-progressor" value="0" max="100" style="width:80%;"></progress>
        <span id="contact-percentage" style="text-align:right; display:block; margin-top:5px;">0</span>
        ';
        echo "
<script>
";
        require_once __DIR__ . '/../Util/JS/EventSource.js';
        echo "</script>
        
<script>
function printLog(message) {
var r = document.getElementById('contact-results');
r.innerHTML = message;
}

if(typeof(EventSource) !== 'undefined') {
printLog('processing started');
          var es = new EventSource($source);

    //a message is received
    es.addEventListener('message', function(e) {
    var result = JSON.parse( e.data );

    if(result.message>'')
    printLog(result.message);

    if(e.lastEventId == 'CLOSE') {
    printLog('Finished');
    es.close();
    var pBar = document.getElementById('contact-progressor');
    pBar.value = pBar.max; //max out the progress bar
    " . ($redirect ? "
    window.setTimeout(window.location.href = '?id=" . $_GET['id'] . "',5000);
" : '') . "
    }
    else {
    var pBar = document.getElementById('contact-progressor');
    pBar.value = result.progress;
    var perc = document.getElementById('contact-percentage');
    perc.innerHTML   = result.progress  + '%';
    perc.style.width = (Math.floor(pBar.clientWidth * (result.progress/100)) + 15) + 'px';
    }
    });

    es.addEventListener('error', function(e) {
    //printLog('Error occurred');
    es.close();

    });
    
    } else {
printLog('<b>Sorry! No server-sent events support. Please use another browser (e.g. Firefox).</b>');
    }
    
    </script>
    ";
    }
}