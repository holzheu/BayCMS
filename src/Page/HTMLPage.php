<?php

namespace BayCMS\Page;

class HTMLPage extends Page
{
    private int $id;
    public function __construct(\BayCMS\Base\BayCMSContext $context, ?int $id=null)
    {
        if (is_null($id) && isset($_GET['id']))
            $id = $_GET['id'];
        if (is_null($id) && isset($_GET['id_obj']))
            $id = $_GET['id_obj'];
        if (is_null($id))
            throw new \BayCMS\Exception\missingData('You have to give an ID');
        $this->id=$id;
        parent::__construct($context);

    }


    public function page(string $pre_content='', string $post_content='')
    {
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select non_empty(' . $this->context->getLangLang2('h.text_') . ') as text, h.min_power
            from html_seiten h, objekt o where h.id=o.id and o.geloescht is null and h.id=$1',
            [$this->id]
        );
        if (!pg_num_rows($res))
            $this->error(
                '404 Not found',
                'There is no HTML-Page with id=' . $this->id
            );

        $r = pg_fetch_array($res, 0);
        if ($r['min_power'] > $this->context->get('row1', 'power', 0))
            $this->error(
                '401 Unautherized',
                'You do not have sufficient rights to access HTML-Page with id=' . $this->id
            );

        $this->context->printHeader();
        echo $pre_content;
        echo $this->context->TE->htmlPostprocess($r['text']);
        echo $post_content;
        $this->context->printFooter();

    }
}