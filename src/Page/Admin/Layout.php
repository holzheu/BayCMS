<?php

namespace BayCMS\Page\Admin;

class Layout extends \BayCMS\Page\Page
{

    private \BayCMS\Fieldset\Form $form;
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct($context);
        $obj = new \BayCMS\Base\BayCMSObject($context);
        $obj->load($context->getOrgId());
        if (!$obj->checkWriteAccess()) {
            $this->error(401, 'No write Access');
        }

        $this->form = new \BayCMS\Fieldset\Form(
            $context,
            table: 'lehrstuhl',
            delete_button: false,
            cancel_button: false
        );

        $res=pg_query($this->context->getDbConn(),"select value from sysconfig where key='ENABLED_TEMPLATES'");
        if(! pg_num_rows($res)) $this->error(500,'sysconfig ENABLED_TEMPLATES not found. Run BayCMS Upgrader.');
        [$enabled_templates]=pg_fetch_row($res,0);
        $enabled_templates=explode(',',$enabled_templates);

        $files = scandir(__DIR__ . "/../../Template/");
        $values = [];
        foreach ($files as $f) {
            if (in_array($f, ['.', '..']))
                continue;
            $f = preg_replace('/\\.php$/', '', $f);
            $v = strtolower(preg_replace('/([A-Z])/', '.\\1', $f));
            $v = explode('.', $v);
            array_shift($v);
            $v=implode('.', $v);
            if(! in_array($v,$enabled_templates)) continue;
            $values[] = [$v, $f];
        }
        $this->form->addField(new \BayCMS\Field\Select(
            $context,
            'style',
            'Template',
            non_empty: 1,
            values: $values
        ));
        $this->form->addField(new \BayCMS\Field\BilangInput(
            $context,
            'head_',
            $context->t('Main Headline', 'Hauptüberschrfit')
        ));
        $this->form->addField(new \BayCMS\Field\BilangInput(
            $context,
            'subhead_',
            $context->t('Sub-Headline', 'Unterüberschrift')
        ));
        $this->form->addField(new \BayCMS\Field\BilangInput(
            $context,
            'title_',
            $context->t('Title', 'Titel'),
            help: ['de'=>$context->t('Shown in the browser window','Wird z.B. im Browserfenster bzw. Tab angezeigt'),
            'en'=>'']
        ));
        $this->form->addField(new \BayCMS\Field\Email(
            $context,
            'email',
            $context->t('Organization E-Mail', 'Organisations E-Mail')
        ));
        $this->form->addField(new \BayCMS\Field\UploadImage(
            $context,
            'org_logo',
            $context->t('Logo of Organization', 'Logo'),
            help: $context->t(
                'Logo is not scaled automatically. Make sure the logo has the correct size. UBT5 Template expects 142x68px',
                'Das Logo wird nicht automatisch skaliert. Stellen Sie sicher, dass das Logo die korrekte Größe hat. Das UBT5 Template erwartet 142x68px'
            )
        ));
        $this->form->addField(new \BayCMS\Field\UploadImage(
            $context,
            'org_favicon',
            $context->t('Favicon of Organization', 'Favicon'),
            height: 48,
            theight: 32,
            crop: 1,
            tcrop:1
        ));
        $template_felder = [
            ['logolink', 'Textinput', 'Logolink (URL or relativ Path)'],
            ['nobilang', 'Checkbox', 'No language switch', 'Keine Zweisprachigkeit'],
            ['nosearch', 'Checkbox', 'No search field', 'Kein Suchfeld'],
            ['nointern', 'Checkbox', 'No link internal', 'Kein Link interne Seiten'],
            ['bayceermember', 'Checkbox', 'Member of BayCEER'],
            ['supbayceer', 'Checkbox', 'Supported by BayCEER'],
            ['orggce', 'Checkbox', 'Organizer of GCE'],
            ['membergi', 'Checkbox', 'Member of GI'],
            ['terminanzahl', 'Number', 'Number of items in datebox', 'Anzahl Termine in Terminbox'],
            ['toplink_oben', 'Checkbox', 'Toplinks directly after HOME', 'Toplinks direkt nach HOME'],
            ['link_fak', 'URL', 'Link Faculty', 'Link Fakultät'],
            ['link_facebook', 'URL', 'Link Facebook'],
            ['link_instagram', 'URL', 'Link Instagram'],
            ['link_twitter', 'URL', 'Link Twitter'],
            ['link_bluesky', 'URL', 'Link Bluesky'],
            ['link_youtube', 'URL', 'Link Youtube'],
            ['link_blog', 'URL', 'Link Blog'],
            ['link_contact', 'URL', 'Link Contact Page', 'Link Kontakt']
        ];
        foreach ($template_felder as $f) {
            $class = "\\BayCMS\Field\\" . $f[1];
            $this->form->addField(new $class(
                $context,
                'te_' . $f[0],
                $context->t($f[2], $f[3] ?? null)
            ));
        }




    }

    public function page()
    {
        $this->form->load($this->context->getOrgId());
        if(($_GET['aktion']??'')=='save'){
            $this->form->setValues($_POST);
            $this->form->save();
            header("Location:".$_SERVER['PHP_SELF']);
            exit();
        }
        $this->context->printHeader();
        echo $this->form->getForm($this->t('Change Layout', 'Layout ändern'));
        $this->context->printFooter();
    }
}