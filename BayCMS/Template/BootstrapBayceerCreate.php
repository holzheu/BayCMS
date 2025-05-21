<?php

namespace BayCMS\Template;

use BayCMS\Template\Bootstrap;

class BootstrapBayceerCreate extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $BILD_HTML = '';
$res = pg_query(
	$this->context->getDbConn(),
	"select non_empty(" . $this->context->getLangLang2('b.') . ") as titel,
		non_empty(" . $this->context->getLangLang2('i.url_') . ") as url,
		b.x,b.y,i.margin,b.name from 
		bild b, ubt3_images i where b.id=i.id_bild and i.id_lehr=" .
	$this->context->get('row1', 'id') . " order by i.ordnung,1"
);
$width = 580;
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

$this->info0 = '<div class="visible-md" style="height:20px; width: 200px;"></div>';
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
	
    .ias {
        font-family: "Libre Baskerville",serif;
        padding-top: 15px;
        text-transform: uppercase;
    }
    .headerin {
        float: left;
        padding-right: 10px; 
    }
    .ias h1, .ias h2, .ias h3{
        padding: 0;
        margin: 0;
    }
    .ias h1{
        font-size: 28px;
    }
    .ias h2{
        font-size: 15px;
    }
    .ias h3{
        font-size: 11px;
    }
    
	
	</style>';

$this->info3='';
$this->pre_nav_html='<div class="visible-xs visible-sm center-block" 
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
    <a href="/' . $this->context->org_folder . '/?lang=' . $this->context->lang .
		'">' . ($context->getOrgLogo()?'<img src="'.$context->getOrgLogo().'">':'') . '</a>

	<a href="/' . "bayceer/?lang=".$this->context->lang . '"><img src="/baycms-template/bootstrap.bayceer/bayceer_' . $this->context->lang . '2.gif"></a>
	</div>
    <div class="ias">
<hgroup class="headerin"><h2>Institute</h2><h3 style="margin-left: 71px;">of</h3></hgroup>
	<h1>African Studies</h1>
</div>
<h3 style="font-size:1.2em; font-weight: bold; color:#000;">' . $this->context->getRow1String('subhead_') . '</h3>

	</div>
	</div>
	<div class="clearfix"></div>
';
$this->css='bootstrap.bayceer';
$this->no_brand=false;
$this->info4='<p style="text-align:center;padding-top:10px;">
    <a href="http://www.maseno.ac.ke/">
    <img src="/baycms-template/bootstrap.bayceer.create/maseno.png"
    width="150" height="150" border="0" style="padding-top: 5px;"></a><br/>
    <a href="https://www.mu.ac.ke/">
    <img src="/baycms-template/bootstrap.bayceer.create/moi.png"
    width="150" height="150" border="0" style="padding-top: 5px;"></a><br/>
    <a href="http://www.lbda.co.ke/">
    <img src="/baycms-template/bootstrap.bayceer.create/basin1.png"
    width="150" height="147" border="0" style="padding-top: 5px;"></a><br/>
    <a href="http://www.ias.uni-bayreuth.de/en/index.php">
    <img src="/baycms-template/bootstrap.bayceer.create/ias.gif"
    width="150" height="152" border="0" style="padding-top: 5px;" alt="Institute of African Studies"
     title="Institute of African Studies"></a><br/>
    <a href="http://www.bayceer.uni-bayreuth.de/"><br/>
    <img src="/baycms-template/bootstrap.bayceer.create/bayceer_k.gif" width="157" height="44" border="0" style="padding-top: 5px;"></a><br/><br/><br/>
    
    </p>';

    }
}