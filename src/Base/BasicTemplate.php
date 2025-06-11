<?php

namespace BayCMS\Base;

// Template Class
class BasicTemplate extends \BayCMS\Base\BayCMSBase
{
    protected string $tiny_css = '';
    protected string $tiny_style_formats = '';
    protected string $tiny_template = '';
    protected string $tiny_image_class = '';
    protected string $additional_body_attributes = '';
    protected string $header_style = '';


    public function __get(string $name)
    {
        if (isset($this->$name))
            return $this->$name;
        return false;
    }

    protected function class2color(string $class)
    {
        if ($class == 'success')
            return '#008800';
        if ($class == 'danger')
            return '#cc0000';
        if ($class == 'warning')
            return '#ff8800';
        return '#000000';
    }

    public function getMessage(string $msg, string $class = 'success', string $notice = '', bool $inline = false)
    {
        if (!isset($this->context) || !$this->context->commandline) {
            if (!$inline) {
                $out = '<h4 style="color:' . $this->class2color($class) . '">' . $msg . '</h4>';
                if ($notice)
                    $out .= '<p>' . $notice . '</p>';
            } else {
                $out = '<span style="color:' . $this->class2color($class) . '">' . $msg . '</span><br/>';
                if ($notice)
                    $out .= $notice . '<br/>';
            }

            return $out;
        }

        if ($class == 'danger')
            $out = "\e[1;37;41m$msg\e[0m\n";
        elseif ($class == 'warning')
            $out = "\e[0;30;43m$msg\e[0m\n";
        elseif ($class == 'success')
            $out = "\e[1;32m$msg\e[0m\n";
        else
            $out = "$msg\n";

        return $out . ($notice ? "$notice\n" : '');

    }

    public function printMessage(string $msg, string $class = 'success', string $notice = '', bool $inline = false)
    {
        echo $this->getMessage($msg, $class, $notice, $inline);
    }

    public function getCSSClass($which)
    {
        if ($which == 'list_table')
            return ' border';
        if ($which == 'form_div')
            return 'formrow';
        return '';
    }

    public function getActionLink($url, $text, $attrib, $type = '', $options = array())
    {
        return '<a class="baycms_action_link" href="' . $url . '" ' . $attrib . '>' . $text . '</a>';
    }

    public function isBootstrap()
    {
        return 0;
    }

    public function htmlPostprocess($html)
    {
        return $html;
    }

    public function getLang2Link()
    {
        $qs = preg_replace("/aktion=[0-9a-z_]+/i", "", $_SERVER['QUERY_STRING']);
        if (preg_match('&/' . $this->context->org_folder . '/' . $this->context->lang . '/&', $_SERVER['PHP_SELF']))
            $url = preg_replace(
                '&/' . $this->context->org_folder . '/' . $this->context->lang . '/&',
                '/' . $this->context->org_folder . '/' . $this->context->lang2 . '/',
                $_SERVER['PHP_SELF']
            );
        else {
            $url = $_SERVER['PHP_SELF'];
            if (preg_match('/lang=' . $this->context->lang . '/', $qs))
                $qs = preg_replace('/lang=' . $this->context->lang . '/', 'lang=' . $this->context->lang2, $qs);
            else
                $qs .= ($qs ? '&' : '') . 'lang=' . $this->context->lang2;
        }
        return $url . ($qs ? '?' . $qs : '');
    }

    public function getLinkInternal()
    {
        $res = pg_query(
            $this->context->getDbConn(),
            "select f.name,i.qs from file f,index_files i where f.id_kat=400 
        and i.id_lehr=" . $this->context->getOrgId() . "
        and i.id_file=f.id and i.id_super=400 order by i.ordnung desc, 
        f." . $this->context->lang . " limit 1"
        );
        if (pg_num_rows($res)) {
            $r = pg_fetch_array($res, 0);
            $link = $r['name'] . $r['qs'] .
                (strstr($r['qs'], '?') ? '&' : '?') . 'force_login=1';
        } else
            $link = "intern/gru/index.php?force_login=1";
        return $link;
    }

    public function printCookieDingsBums()
    {
        echo '
		<div id="cookiedingsbums"><div>
		  <span>' . $this->t('This site makes use of cookies', 'Diese Webseite verwendet Cookies') . '</span> 
		  <a href="/' . $this->context->getOrgLinkLang() . '/top/gru/ds.php">' . $this->t('More information', 'weitere Informationen') . '</a></div>
		 <span id="cookiedingsbumsCloser" onclick="document.cookie = \'hidecookiedingsbums=1;path=/\';jQuery(\'#cookiedingsbums\').slideUp()">&#10006;</span>
		</div>
		
		<script>
		 if(document.cookie.indexOf(\'hidecookiedingsbums=1\') != -1){
		 jQuery(\'#cookiedingsbums\').hide();
		 }
		 else{
		 jQuery(\'#cookiedingsbums\').prependTo(\'body\');
		 jQuery(\'#cookiedingsbumsCloser\').show();
		 }
		</script>';
    }


}
