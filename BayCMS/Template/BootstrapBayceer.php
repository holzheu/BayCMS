<?php

namespace BayCMS\Template;

use BayCMS\Template\Bootstrap;

class BootstrapBayceer extends Bootstrap
{
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null)
    {
        parent::__construct($context);
        $this->css = 'bootstrap.bayceer';
        $this->home_nav = true;
        $BILD_HTML = '';
        $res = pg_query(
            $this->context->getDbConn(),
            "select non_empty(" . $this->context->getLangLang2('b.') . ") as titel,
		non_empty(" . $this->context->getLangLang2('i.url_') . ") as url,
		b.x,b.y,i.margin,b.name from 
		bild b, ubt3_images i where b.id=i.id_bild and i.id_lehr=" .
            $this->context->get('row1', 'id') . " order by i.ordnung,1"
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
                $BILD_HTML .= ($r['url'] ? "<a href=\"$r[url]\">" : "") . "<img src=\"/" . $this->context->getOrgLinkLang() . "/image/$r[name]\" 
		width=\"$r[x]\" height=\"$r[y]\" border=\"0\" style=\"margin-right: $r[margin]px;\" alt=\"$r[titel]\" 
		title=\"$r[titel]\">" . ($r['url'] ? "</a>" : "");
                $x += $r['margin'];
            }
        }

        $this->header_style = '<style>' . ($this->context->get('no_frame') ? '' : '
            @media (min-width: 768px){ 
            #wrap {
                 background: #fff url(/baycms-template/bootstrap.bayceer/bg_sm40.png) center 0 repeat-x;
            }
            }
            @media (min-width: 992px) {
            #wrap {
                 background: #fff url(/baycms-template/bootstrap.bayceer/bg_md40.png) center 0 no-repeat;
            }
            }
            @media (min-width: 1200px) {
            #wrap {
                 background: #fff url(/baycms-template/bootstrap.bayceer/bg_lg40_3000.png) center 0 no-repeat;
            }
            }
                    
            ') . '
            .navbar {
               /*background-color: rgba(54,193,241,0.7);*/
               background: rgba(159,161,158,0.5);
               border-color: transparent;
            }
            
            
            </style>';
        $this->info3 = '';
        $this->info4 = 'default';
        $this->pre_nav_html = '<div class="visible-xs visible-sm center-block" 
            style="width:100%; background: url(/baycms-template/bootstrap.bayceer/bg_top.gif) 0 0 repeat-x;">
            <div class="container">
            <div class="pull-right" style="line-height:10px;"><a href="http://www.uni-bayreuth.de">
            <img src="/baycms-template/bootstrap.bayceer/unilogo.jpg">
            </a>
            </div>
            </div>
            </div>
            
            <div class="hidden-xs hidden-sm" style="position: relative; top: 0; left: 60%; height:24px; width:40%"><a href="http://www.uni-bayreuth.de">
            <img src="/baycms-template/bootstrap.bayceer/blank.png" style="height:15px; width:100%;border:0"></a>
            </div>
            
            <div class="hidden-xs center-block"  style="width:100%; background-color: none; height:117px;">
            
            
            <div class="container">
            <div class="pull-right hidden-sm hidden-xs" style="padding-top:19px;">' . $BILD_HTML . '</div>
            <div class="pull-left">
            <a href="/bayceer/?lang=' . $this->context->lang .
            '"><img src="/baycms-template/bootstrap.bayceer/bayceer_' . $this->context->lang . '2.gif"></a>
            </div>
            </div>
            </div>
            <div class="clearfix"></div>
            ';
        
        $this->css = 'bootstrap.bayceer';
        $this->no_brand = false;
        
    }
}