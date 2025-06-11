<?php

namespace BayCMS\Template;

class Bootstrap extends \BayCMS\Base\BasicTemplate{

    private bool $header_out = false;
    protected string $css = 'bootstrap';
    protected bool $no_brand = false;
    protected bool $home_nav = false;
    protected bool $navbar_inverted = true;
    protected bool $no_bilang = false;
    protected bool $wrap_container = false;
    protected bool $breadcrumbs = false;
    protected string $info0 = '';
    protected string $info1 = 'default';
    protected string $info2 = 'default';
    protected string $info3 = '';
    protected string $info4 = '';

    protected string $toplink = '';
    protected string $footer_text = '';
    protected string $footer_attr = '';
    protected string $pre_nav_html='';

    protected array $breadcrumb_url=[];
    protected array $breadcrumb_text=[];


    public function __construct(?\BayCMS\Base\BayCMSContext $context = null)
    {
        if (is_null($context))
            $this->context = $GLOBALS['context'];
        else
            $this->context = $context;
        $this->toplink = $this->context->t('About us', 'Über uns');

        $this->no_bilang = $this->context->get('row1', 'te_nobilang', false);

        $this->footer_text = 'powered by BayCMS © 2024 <a href="http://www.uni-bayreuth.de/">University of Bayreuth,</a>
            <a href="http://www.bayceer.uni-bayreuth.de/">BayCEER</a>';

        $this->tiny_css = '/baycms-template/' . $this->css . '/css/bootstrap.min.css';
        $this->tiny_image_class = "
image_class_list: [
{title: 'responsive', value: 'img-responsive'},
{title: 'rounded', value: 'img-responsive img-rounded'},
{title: 'circle', value: 'img-responsive img-circle'},
{title: 'thumbnail', value: 'img-responsive img-thumbnail'}
]";
        $this->tiny_style_formats = "[
{title: 'Well', inline: 'div', classes: 'well primary', wrapper: true},
{title: 'Well large', inline: 'div', classes: 'well well-lg primary', wrapper: true},
{title: 'Well small', inline: 'div', classes: 'well well-sm primary', wrapper: true},
{title: 'Jumbotron', inline: 'div', classes: 'jumbotron', wrapper: true},
{title: 'Button Link', selector: 'a', classes: 'btn btn-default'},
]";
        $this->tiny_template = "'/baycms-template/bootstrap/templates.php'";
    }

    public function __set(string $name, mixed $value){
        $this->$name=$value;
    }

    public function create_slider_from_album($args)
    {
        list($id, $height, $time) = explode(',', $args);
        if (!is_numeric($time))
            $time = 4000;
        if (!is_numeric($id))
            return '<!-- INSERT SLIDER FAILED: ID not numeric -->';
        $res = pg_query($GLOBALS['conn1'], "select non_empty(b.$GLOBALS[lang],b.$GLOBALS[lang2]),b.*,
				non_empty(bb.text_$GLOBALS[lang],bb.text_$GLOBALS[lang2]) as beschr,bb.autor
				from bild b left outer join bild_beschreibung bb on bb.id=b.id
				where b.id_obj=$id  and not b.intern order by b.ordnung,non_empty,b.id");
        if (!pg_num_rows($res))
            return '<!-- INSERT SLIDER FAILED: No pictures found -->';
        $out = '<div id="myCarousel" class="carousel slide" data-ride="carousel" data-interval="' . $time . '">
		<!-- Indicators -->
		<ol class="carousel-indicators">';
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $out .= '<li data-target="#myCarousel" data-slide-to="' . $i . '"' . ($i == 0 ? ' class="active"' : '') . '></li>';
        }
        $out .= '</ol>

		<!-- Wrapper for slides -->
		<div class="carousel-inner" role="listbox">';
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            if (is_numeric($height)) {
                $f = $height / $r['y'];
                $r['y'] = $height;
                $r['x'] = round($r['x'] * $f);
            }
            $out .= '<div class="item' . ($i == 0 ? ' active' : '') . '">
			<img class="img-responsive center-block" src="/' . $this->context->getOrgLinkLang() . '/image/' . $r['name'] . '" alt="' . $r['non_empty'] . '"
			width="' . $r['x'] . '" height="' . $r['y'] . '">
			<div class="carousel-caption">
			' . $r['beschr'] . '
			</div>
			</div>';
        }
        $out .= '</div>
		<!-- Left and right controls -->
		<a class="left carousel-control" href="#myCarousel" role="button" data-slide="prev">
		<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
		<span class="sr-only">Previous</span>
		</a>
		<a class="right carousel-control" href="#myCarousel" role="button" data-slide="next">
		<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
		<span class="sr-only">Next</span>
		</a>
		</div>
		';
        return $out;
    }

    public function getMessage(string $msg, string $class = 'success', string $notice = '', bool $inline = false)
    {
        if(! $this->context->commandline && ! $inline)
            return '<div class="alert alert-' . $class . ' alert-dismissible" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">&times;</span></button><strong>' . $msg . '</strong>' . ($notice ? '<br/>' . $notice : '') . '</div>';

        return parent::getMessage($msg, $class, $notice, $inline);
    }

    public function getCSSClass($which)
    {
        if (strstr($which, 'table')) {
            if ($which == 'user_sub_table')
                return ' class="table table-condensed"';
            return ' class="table table-hover"';
        }
        if ($which == 'form_div')
            return 'formrow'; //form-group does not work -- fieldset!!
        return '';
    }

    public function getActionLink($url, $text, $attrib, $type = '', $options = array())
    {
        $icons = array(
            'del' => 'remove',
            'edit' => 'edit',
            'properties' => 'share',
            'copy' => 'copy',
            'new' => 'plus'
        );
        if (!isset($options['class'])) {
            if ($type == 'new')
                $class = '';
            else
                $class = ' btn-xs';
        } else
            $class = $options['class'];
        $icon = (isset($icons[$type]) ? $icons[$type] : $type);
        if (!isset($options['hidden-xs']))
            $options['hidden-xs'] = 1;
        return '<a href="' . $url . '" ' . $attrib . ' class="btn' . $class . ' btn-default">
		<span class="glyphicon glyphicon-' . $icon . '"></span> ' . ($options['hidden-xs'] ? '<span class="hidden-xs">' : '') .
            $text . ($options['hidden-xs'] ? '</span>' : '') . '</a>';
    }

    public function isBootstrap()
    {
        return 1;
    }

    public function htmlPostprocess($html)
    {
        $c = preg_match_all('/<div *class="baycmspostprocess" *>([^:]+):([^ -]*)<\\/div>/i', $html, $matches);
        for ($i = 0; $i < $c; $i++) {
            switch (strtoupper($matches[1][$i])) {
                case 'INSERT SLIDER':
                    $res = $this->create_slider_from_album($matches[2][$i]);
                    $html = str_replace($matches[0][$i], $res, $html);
                    break;
            }

        }
        return $html;
    }

    public function getHead()
    {
        $out = '<!DOCTYPE html>
        <html>
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($this->context->title) . '</title>
        <meta name="layout" content="main">
        <link rel="stylesheet"
            href="/baycms-template/' . $this->css . '/css/bootstrap.min.css">
        <link rel="stylesheet"
            href="/baycms-template/bootstrap/css/signin.css">
        <link rel="stylesheet"
            href="/baycms-template/bootstrap/css/main.css">
            <script type="text/javascript" src="/javascript/jquery/jquery.min.js"></script>
            <script type="text/javascript" src="/javascript/jquery-ui/jquery-ui.min.js"></script>
            <script type="text/javascript" src="/jquery/jquery.datetimepicker.full.min.js"></script>
            <script type="text/javascript" src="/jquery/jquery.minicolors.min.js"></script>
            <style type="text/css">@import "/javascript/jquery-ui/themes/base/jquery-ui.min.css";</style>
            <style type="text/css">@import "/jquery/jquery.datetimepicker.min.css";</style>
            <style type="text/css">@import "/jquery/jquery.minicolors.css";</style>';

        if($this->context->getOrgFavicon())
            $out .= '<link rel="shortcut icon" href="' . $this->context->getOrgFavicon() . '">' . "\n";
        $out .= $this->context->ADDITIONAL_HTML_HEAD;
        $out .= $this->header_style;
        $out .= '
        <link rel="stylesheet"
            href="/baycms-template/bootstrap/baycms4.0.css">
        <link rel="stylesheet"
            href="/baycms-template/bootstrap/bayconf.css">
        </head>
        ';
        return $out;
    }


    public function getNavigation($kat_row, $right = 0)
    {
        if (($kat_row['link'] ?? '') == $this->context->kategorie) {
            if (
                !pg_num_rows(pg_query(
                    $this->context->getDbConn(),
                    "select id_kat from kat_aliases where
                id_lehr=" . $this->context->get('row1', 'id') . " and id_kat=$kat_row[id] 
                and no_dp_first union
                    select id from kategorie where id=$kat_row[id] and no_dp_first"
                ))
                || !($_SERVER['PHP_SELF'] == $kat_row['url'] ||
                    $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] == $kat_row['url'])
            ) {
                $this->breadcrumb_url[] = "$kat_row[url]";
                $this->breadcrumb_text[] = strip_tags($kat_row['text']);
            }
        }
        $out = '';
        $r = $kat_row;
        $res2 = pg_query(
            $this->context->getDbConn(),
            "select * from get_full_index_table($r[id],0,''," . $this->context->get('row1', 'id') .
            ",'" . $this->context->org_folder . "','" . $this->context->lang . "')"
        );
        $no_dropdown = 0;
        $level = 1;
        if (pg_num_rows($res2) == 1) {
            $r2 = pg_fetch_array($res2, 0);
            if ($r2['url'] == $r['url'])
                $no_dropdown = 1;
        }
        if (!$no_dropdown && pg_num_rows($res2)) {
            $out .= '<li class="dropdown"><a href="#" class="dropdown-toggle"	data-toggle="dropdown">
            ' . $r['text'] . '<b class="caret"></b></a>
            <ul class="dropdown-menu" role="menu">
            ';
            $r2 = pg_fetch_array($res2, 0);

            for ($j = 1; $j < pg_num_rows($res2) + 1; $j++) {
                if ($j < pg_num_rows($res2))
                    $r_next = pg_fetch_array($res2, $j);
                else
                    $r_next['level'] = 1;
                $class_active = '';
                if (strstr($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'], $r2['url'])) {
                    $class_active = ' class="active"';
                    $this->breadcrumb_url[] = $r2['url'];
                    $this->breadcrumb_text[] = strip_tags($r2['name']);
                }
                if ($r_next['level'] > $r2['level']) {
                    //Submenue!
                    $out .= '
                    <li class="dropdown-submenu' . ($right ? ' pull-left' : '') . '"><a href="#">' . $r2['name'] . '</a>
                    <ul class="dropdown-menu">
                    ';
                    if ($r2['url'] > '') {
                        if ($r2['target'] > '')
                            $target = ' target="' . $r2['target'] . '"';
                        else
                            $target = '';
                        $out .= '<li' . $class_active . '><a href="' . $r2['url'] . '"' . $target . '>' . $r2['name'] . '</a></li>';
                    }

                } elseif ($r2['url'] > '') {
                    if ($r2['target'] > '')
                        $target = ' target="' . $r2['target'] . '"';
                    else
                        $target = '';
                    $out .= '<li' . $class_active . '><a href="' . $r2['url'] . '"' . $target . '>' . $r2['name'] . '</a></li>';
                } elseif (strstr($r2['name'], '<hr'))
                    $out .= '<li class="divider"></li>';
                else
                    $out .= '<li>' . $r2['name'] . '</li>';

                while ($r_next['level'] < $r2['level']) {
                    $out .= '
</ul></li>
';
                    $r2['level']--;
                }
                $r2 = $r_next;
            }
            while ($r2['level']) {
                $out .= '
</ul></li>
';
                $r2['level']--;
            }
        } else
            $out .= '<li' . ($r['link'] == $this->context->kategorie ? ' class="active"' : '') . '>
            <a href="' . $r['url'] . '">' . $r['text'] . '</a></li>';

        return $out;
    }


    public function printHeader()
    {
        if ($this->header_out)
            return;
        echo $this->getHead();
        echo '<body ' . $this->context->additional_body_attributes . '>
            <div id="wrap"';
        if ($this->wrap_container)
            echo ' class="container"';
        echo '>';
        if (!$this->context->no_frame) {
            echo $this->pre_nav_html;
            echo '<nav class="navbar navbar-default';
            if ($this->navbar_inverted)
                echo ' navbar-inverse';
            echo '">
                    <div class="container">
                        <div class="navbar-header">
        
                            <button type="button" class="navbar-toggle" data-toggle="collapse"
                                data-target=".navbar-ex1-collapse">
                                <span class="sr-only">Toggle navigation</span> <span
                                    class="icon-bar"></span> <span class="icon-bar"></span> <span
                                    class="icon-bar"></span>
                            </button>';
            if ($this->no_brand) {
                echo '<a href="/' . $this->context->org_folder . '/?lang=' . $this->context->lang . '"
                                class="navbar-brand';
                if ($this->pre_nav_html)
                    echo ' visible-xs';
                echo '">';
                if ($this->context->getOrgLogo())
                    echo '<img style="height:100%;float:left;margin-right:10px;"
                     src="' . $this->context->getOrgLogo() . '">';
                echo '<strong>' . $this->context->getRow1String('head_') . '</strong>
                            </a>';
            }
            echo '</div>
                        <div class="collapse navbar-collapse navbar-ex1-collapse">
                            <ul class="nav navbar-nav">';
            $homelink = 'http://' . ($this->context->get('row1', 'httphost') ?
                $this->context->get('row1', 'httphost') : $_SERVER['HTTP_HOST']) . "/" . $this->context->org_folder .
                "/index.php?lang=" . $this->context->lang;
            $this->breadcrumb_url = [$homelink];
            $this->breadcrumb_text = ["Home"];
            $kats = [];
            //externe Kategorien
            $res = pg_query($this->context->getDbConn(), $this->context->H_kat_query_extern);
            //Top Links
            $res2 = pg_query(
                $this->context->getDbConn(),
                "select * from get_index_table(1000," . $this->context->get('row1', 'id') .
                ",'" . $this->context->org_folder . "','" . $this->context->lang . "')"
            );
            $kat_num = pg_num_rows($res) + pg_num_rows($res2);
            if ($kat_num > 6 && pg_num_rows($res2) > 1) {
                $top_links_as_dropdown = 1;
                $kat_num = pg_num_rows($res) + (pg_num_rows($res2) > 0);
            } else
                $top_links_as_dropdown = 0;

            //interne Kategorien
            if ($this->context->AUTH_OK) {
                $res3 = pg_query(
                    $this->context->getDbConn(),
                    $this->context->H_kat_query_intern
                );
                if (($kat_num + pg_num_rows($res3)) > 6)
                    $intern_extern_link = 1;
                else
                    $intern_extern_link = 0;
            }

            $kat_count = 0;
            if ($this->context->min_power && $intern_extern_link) {
                echo "<li><a href=\"$homelink\">&lt;&lt; " . $this->t('external site', 'Externe Seiten') . "</a></li>";
                for ($i = 0; $i < pg_num_rows($res3); $i++) {
                    $r = pg_fetch_array($res3, $i);
                    echo $this->getNavigation($r);
                }
                $kat_count = pg_num_rows($res3) + 1;

            } else {
                $kats = array();
                //Externe Links
                if ($this->home_nav) {
                    echo '<li' . (!$this->context->kategorie ? ' class="active"' : '') . '>
                    <a href="/' . $this->context->org_folder . '/?lang=' . $this->context->lang . '">Home</a></li>';
                    $kat_count++;
                }
                //Top-Links
                if (!isset($top_links))
                    $top_links = '';
                for ($i = 0; $i < pg_num_rows($res2); $i++) {
                    $r = pg_fetch_array($res2, $i);
                    $top_links .= '<li' . (strstr($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'], $r['url']) ? ' class="active"' : '') . '>
                        <a href="' . $r['url'] . '">' . $r['name'] . '</a></li>';
                    if ($i == 0)
                        $r_top_link = ['id' => 1000, 'text' => $this->toplink, 'url' => $r['url']];
                }

                if ($this->context->get('row1', 'te_toplink_oben') == 't') {
                    if ($top_links_as_dropdown)
                        $kats[] = $r_top_link;
                    else {
                        echo $top_links;
                        $kat_count += pg_num_rows($res2);
                    }
                }
                for ($i = 0; $i < pg_num_rows($res); $i++) {
                    $kats[] = pg_fetch_array($res, $i);
                }
                if (
                    $this->context->get('row1', 'te_toplink_oben') != 't' &&
                    $top_links_as_dropdown
                ) {
                    $kats[] = $r_top_link;
                }

                for ($i = 0; $i < count($kats); $i++) {
                    echo $this->getNavigation($kats[$i]);
                }
                $kat_count += count($kats);
                if (
                    $this->context->get('row1', 'te_toplink_oben') != 't' &&
                    !$top_links_as_dropdown
                ) {
                    echo $top_links;
                    $kat_count += pg_num_rows($res2);
                }
                if (isset($intern_extern_link) && $intern_extern_link) {
                    $kat_count++;
                    if ($_SERVER['HTTP_HOST'] == "localhost")
                        $url = "http://localhost";
                    elseif ($this->context->NOSSL)
                        $url = "";
                    elseif ($this->context->get('row1', 'httpshost'))
                        $url = "https://" . $this->context->get('row1', 'httpshost');
                    else
                        $url = "https://www.bayceer.uni-bayreuth.de";
                    echo "<li><a href=\"$url/" . $this->context->getOrgLinkLang() .
                        "/" . $this->getLinkInternal() . "\">&gt;&gt; " . $this->t('internal site', 'interne Seiten') . "</a> </li>";
                }

            }

            echo '</ul>
                            <ul class="nav navbar-nav navbar-right">';
            //Internal navigation if not more than 4 external links
            if ($this->context->AUTH_OK && !$intern_extern_link) {
                for ($i = 0; $i < pg_num_rows($res3); $i++) {
                    $r = pg_fetch_array($res3, $i);
                    echo $this->getNavigation($r, 0);
                }
                $kat_count += pg_num_rows($res3);
            }
            if ($this->context->AUTH_OK && $kat_count < 6) {
                echo '<li class="visible-lg"><a>Signed in as <strong>' .
                    $this->context->get('row1', 'kommentar') . '</strong>
                                    </a>
                                </li>';
            }
            if ($this->context->AUTH_OK || $this->context->get('row1', 'te_nointern') != 't') {
                echo '
                        <li><a href="' . $this->context->get('H_LoginLogout', 'url') . '"><span class="glyphicon glyphicon-log-';
                if (strtolower($this->context->get('H_LoginLogout', 'text')) == 'logout')
                    echo 'out';
                else
                    echo 'in';
                echo '"></span> ' . ucfirst(strtolower($this->context->get('H_LoginLogout', 'text'))) . '</a></li>';

            }
            if (!$this->no_bilang) {
                echo "<li><a class=\"navbar-link\"  href=\"" .$this->getLang2Link(). '">
            <img src="/baycms-template/bootstrap/' . $this->context->lang2 . '30x15.gif" alt="' .
                    $this->t('in deutsch', 'in english') . '"></a></li>';
            }
            echo '</ul>
        
                        </div>
                </div>
                </nav>
                ';
        }
        echo '<div class="container">';
        if (!$this->context->no_frame) {
            if ($this->breadcrumbs) {
                if (is_numeric($_GET['id_obj']) && $r['qs'] != "?id_obj=$_GET[id_obj]")
                    $this->breadcrumb_text[] = strip_tags($this->context->object_title);
                ;
                echo '<ol class="breadcrumb">';
                for ($i = 0; $i < count($this->breadcrumb_text); $i++) {
                    if ($i == (count($this->breadcrumb_text) - 1))
                        echo "<li class=\"active\">" . $this->breadcrumb_text[$i] . "</li>\n";
                    else
                        echo "<li><a href=\"$" . $this->breadcrumb_url[$i] . "\">" .
                            $this->breadcrumb_text[$i] . "</a></li>\n";
                }
                echo '</ol>';
            }
            echo '<div class="col-md-9">';
        }
        if (isset($GLOBALS['alert'])) {
            echo $GLOBALS['alert'];
            $GLOBALS['alert'] = '';
        }
        $this->header_out = true;

    }

    public function printFooter()
    {
        echo '</div>';
        if ($this->context->no_frame) {
            echo '</body>
            </html>';
            exit();

        }

        echo '<div class="col-md-3 col-sm-12">';
        echo $this->info0;

        if ($this->info1 == "default")
            include __DIR__ . "/termine.inc";
        else
            echo $this->info1;

        if ($this->info2 == "default") {
            $include = $this->context->BayCMSRoot . "/admin/" . $this->context->get('row1', 'id') . "/bootstrap_inc.php";
            if (is_readable($include))
                include $include;
        } else
            echo $this->info2;

        if ($this->info3 == "blog")
            include __DIR__ . "/blog.inc";
        else
            echo $this->info3;

        if ($this->info4 == "default")
            echo '';
        elseif ($this->info4 == "blog")
            include __DIR__ . '/blog.inc';
        else
            echo $this->info4;


        if ($this->context->get('row1', 'te_bayceermember') == "t")
            echo "<a href=\"http://www.bayceer.uni-bayreuth.de\">
    <img src=\"/baycms-template/bootstrap/member_bayceer2.gif\" border=0 width=120 height=60 alt=\"BayCEER Member\"></a>
<br/><br/>\n";

        if ($this->context->get('row1', 'te_supbayceer') == "t")
            echo "<a href=\"http://www.bayceer.uni-bayreuth.de\">
<img src=\"/baycms-template/bootstrap/supported_by_bayceer.jpg\" border=0 width=120 height=60 alt=\"Supported by BayCEER\"></a>
<br/><br/>\n";

        if ($this->context->get('row1', 'te_membergi') == "t")
            echo "<a href=\"http://www.geographie.uni-bayreuth.de/de/index.html\">
			<img src=\"/baycms-template/bootstrap/member_gi.jpg\" border=0 width=120 height=60 alt=\"Member GI\"></a>
<br/><br/>\n";

        if ($this->context->get('row1', 'te_orggce') == "t")
            echo "<a href=\"http://www.bayceer.uni-bayreuth.de/gce/\">
<img src=\"/baycms-template/bootstrap/Organizer_of_GCE_WEB.jpg\" border=0 width=120 height=60 alt=\"Organizer of GCE\">
</a><br/><br/>\n";

        echo '
</div>
</div>

</div>
<div id="footer"';
        if ($this->wrap_container)
            echo ' class="container"';
        echo ' ' . $this->footer_attr . '>
<p class="text-muted credit pull-left hidden-xs">' . $this->footer_text . '
 </p>
<p class="pull-right">
 <a href="/' . $this->context->getOrgLinkLang() . '/top/gru/impressum.php">' . $this->t('Imprint', "Impressum") . '</a> --- 
 <a href="/' . $this->context->getOrgLinkLang() . '/top/gru/sitemap.php">' . $this->t('Sitemap', "Inhaltsverzeichnis") . '</a>
 </p>

</div>
<script type="text/javascript" src="/baycms-template/bootstrap/js/bootstrap.min.js"></script>
<!-- 
<script src="/baycms-template/bootstrap/js/bundle-bundle_bootstrap_defer.js" type="text/javascript"></script>
-->';


        $this->printCookieDingsBums();
        echo '</body>
        </html>';
        exit();

    }

}