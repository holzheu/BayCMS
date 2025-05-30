<?php

namespace BayCMS\Template;

class BootstrapUbt extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null, $fgcolor='#009260',$f2color='#ffffff'){
        parent::__construct($context);

        $row1=$this->context->row1;
        
        $BOOTSTRAP_HOME_NAV = 0;
        if ($this->context->get('row1', 'org_logo'))
            $logo = '<img src="' . $this->context->getOrgLogo() . '" style="padding-right:10px;">';
        else {
            $logo = '';
            $BOOTSTRAP_HOME_NAV = 1;
        }
        $logo_link = $this->context->get('row1', 'te_logolink');
        $default_home_link = '/' . $this->context->org_folder . '/?lang=' . $this->context->lang;
        if (!$logo_link)
            $logo_link = $default_home_link;
        if ($logo_link != $default_home_link)
            $BOOTSTRAP_HOME_NAV = 1;
        
        
        $BOOTSTRAP_PRE_NAV_HTML='<div class="center-block" 
        style="width:100%; background: url(/'.$this->context->org_folder.'/de/template/bootstrap.ubt/bg_top.gif) 0 0 repeat-x;">
        <div class="container">
        <div class="pull-right" style="line-height:10px;">'.
        ($this->context->get('row1','te_fgbio')=='t'?'<a class="hidden-xs" style="color:#fff; border-right: 1px solid #fff; padding: 3px 10px;" href="http://www.biologie.uni-bayreuth.de/">'.
                ($this->context->lang=="en"?"Department of Biology":"Fachgruppe Biologie").'</a>':'').
        ($this->context->get('row1','te_fggeo')=='t'?'<a class="hidden-xs"  style="color:#fff; border-right: 1px solid #fff; padding: 3px 10px;" href="http://www.neu.uni-bayreuth.de/de/Uni_Bayreuth/Fakultaeten/2_Biologie_Chemie_und_Geowissenschaften/geowissenschaften/fachgruppe/de/">'.
                ($this->context->lang=="en"?"Department of Geosciences":"Fachgruppe Geowissenschaften").'</a>':'').
        ($this->context->get('row1','te_fak2')=='t'?'<a class="hidden-xs"  style="color:#fff; border-right: 1px solid #fff; padding: 3px 10px;" href="http://www.bcg.uni-bayreuth.de">'.
                ($this->context->lang=="en"?"Faculty of Biology, Chemistry and Earth Sciences":
                "Fakult&auml;t f&uuml;r Biologie, Chemie und Geowissenschaften").'</a>':'').
        '
        
        <a href="http://www.uni-bayreuth.de">
        <img src="/'.$this->context->org_folder.'/de/template/bootstrap.ubt/unilogo.jpg">
        </a>
        </div>
        </div>
        </div>
        <div class="visible-xs center-block" style="width:100%; background-color: '.$fgcolor.'; min-height:15px;">
        <div class="container">
        <div class="pull-left">
        <h3 style="font-size:1.2em; font-weight: bold; color:'.$f2color.';">'.$this->context->getRow1String("subhead_").'</h3>
        </div>
        </div>
        </div>
        
        <div class="hidden-xs center-block" style="width:100%; background-color:'.$fgcolor.'; min-height:100px;">
        <div class="container">
        <div class="pull-left">
        <a href="'.$logo_link.'">
        '.$logo.'
        </a>
        </div>
        <div class="pull-right hidden-sm" style="padding-top: 10px;">';
        
        $UBT3_HEADER_IMAGE_HEIGHT = 70;
        
        $res = pg_query(
            $this->context->getDbConn(),
            "select non_empty(" . $this->context->getLangLang2("b.") . ") as titel,
                non_empty(" . $this->context->getLangLang2("i.url_") . ") as url,b.x,b.y,i.margin,b.name from
                bild b, ubt3_images i where b.id=i.id_bild and i.id_lehr=$row1[id] order by i.ordnung,1"
        );
        $width = 420;
        $x = 0;
        $i = 0;
        $num = pg_num_rows($res);
        while ($x < $width && $i < $num) {
            $r = pg_fetch_array($res, $i);
            $i++;
            $x += $r['x'];
            if ($x <= $width) {
                $BOOTSTRAP_PRE_NAV_HTML .= ($r['url'] ? "<a href=\"$r[url]\">" : "") . "<img src=\"/".$this->context->getOrgLinkLang()."/image/$r[name]\"
                width=\"$r[x]\" height=\"$r[y]\" border=\"0\" style=\"margin-right: $r[margin]px;\" alt=\"$r[titel]\"
                title=\"$r[titel]\">" . ($r['url'] ? "</a>" : "");
                $x += $r['margin'];
            }
        }
        
        $BOOTSTRAP_PRE_NAV_HTML.='</div>
        <h3 style="font-size:1.2em; font-weight: bold; color:'.$f2color.';">'.$this->context->getRow1String('subhead_').'</h3>
        <h1 style="font-size:1.5em; font-weight: bold; color:'.$f2color.';">'.$this->context->getRow1String('head_').'</h1>
        </div>
        </div>';
        
        $this->css='bootstrap.ubt';
        $this->tiny_css = '/baycms-template/' . $this->css . '/css/bootstrap.min.css';
        $this->pre_nav_html=$BOOTSTRAP_PRE_NAV_HTML;
        $this->home_nav=$BOOTSTRAP_HOME_NAV;
        $this->navbar_inverted=false;
        
    }
}