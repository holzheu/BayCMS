<?php

namespace BayCMS\Template;

class Ubt5 extends \BayCMS\Base\BasicTemplate
{
	private bool $header_out = false;
	public function __construct(?\BayCMS\Base\BayCMSContext $context = null, array $settings = [])
	{
		if (is_null($context))
			$this->context = $GLOBALS['context'];
		else
			$this->context = $context;

		$this->tiny_css = '/baycms-template/ubt5/css/main01.css';
		$this->tiny_style_formats = "[
			{title: 'Section full', block: 'section', classes: 'text full', wrapper: true},
			{title: 'Section', block: 'section', classes: 'text', wrapper: true},
			{title: 'Teaser full', block: 'section', classes: 'teaser full', wrapper: true},
			{title: 'Teaser half', block: 'section', classes: 'teaser half', wrapper: true},
			{title: 'Teaser third', block: 'section', classes: 'teaser third', wrapper: true}
			]";
		$this->tiny_template = "'/baycms-template/ubt5/templates.php'";

	}


	/*	function printMessage($msg,$class='success',$notice=''){
														   echo '<div class="alert alert-'.$class.' alert-dismissible" role="alert">
													   <button type="button" class="close" data-dismiss="alert" aria-label="Close">
													   <span aria-hidden="true">&times;</span></button><strong>'.$msg.'</strong>'. ($notice?'<br/>'.$notice:'').'</div>';
													   }
													   */
	public function getCSSClass($which)
	{
		if (strstr($which, 'table')) {
			//			if($which=='user_sub_table') return ' class="table table-condensed"';
			return ' class="list reverse"';
		}
		if ($which == 'form_div')
			return 'formrow'; //form-group does not work -- fieldset!!
		if ($which == 'h1')
			return ' class="text full grey"';
		return '';
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
				case 'INSERT TERMINE':
					$res = $this->create_termine($matches[2][$i]);
					$html = str_replace($matches[0][$i], $res, $html);
					break;
				case 'INSERT PUBLICATIONS':
					$res = $this->create_new_pubs($matches[2][$i]);
					$html = str_replace($matches[0][$i], $res, $html);
					break;
				case 'INSERT NEWS':
					$res = $this->create_news($matches[2][$i]);
					$html = str_replace($matches[0][$i], $res, $html);
					break;

			}

		}
		if (($GLOBALS['ubt_no_fulltextsection'] ?? '') && $html && !preg_match('/<section[^>]+class="/i', $html))
			return '<section class="text full">' . $html . '</section>';

		return $html;
	}

	public function create_slider_from_album($args)
	{
		$args = explode(',', $args);
		$id = $args[0] ?? 0;
		$height = $args[1] ?? 0;
		$fak = $args[2] ?? false;


		if (!is_numeric($id))
			return '<!-- INSERT SLIDER FAILED: ID not numeric -->';
		list($b_height) = pg_fetch_row(pg_query($this->context->getDbConn(), 'select num1 from objekt_universell where id=' . $id), 0);
		if ($b_height >= 880)
			$prefix = '';
		else
			$prefix = 'o';

		$res = pg_query($this->context->getDbConn(), "select non_empty(" . $this->context->getLangLang2('b.') . "),b.*,
				non_empty(" . $this->context->getLangLang2('bb.text_') . ") as beschr,bb.autor
				from bild b left outer join bild_beschreibung bb on bb.id=b.id
				where b.id_obj=$id  and not b.intern order by b.ordnung,non_empty,b.id");
		if (!pg_num_rows($res))
			return '<!-- INSERT SLIDER FAILED: No pictures found -->';
		$out = '
		<section class="slider'.($fak?' full':'').'">
		<div class="sliderContainer">';
		for ($i = 0; $i < pg_num_rows($res); $i++) {
			$r = pg_fetch_array($res, $i);
			$out .= '<div class="rsContent"><img class="rsImg" src="/' . $this->context->org_folder . '/de/image/' . $prefix . $r['name'] . '"
			alt="' . $r['non_empty'] . '">';
			if ($r['beschr'])
				$out .= '<div class="infoBlock">' . $r['beschr'] . '</div>';
			$out .= '</div>';
		}
		$out .= '</div>
		<div class="progress"><div class="progressBar">&nbsp;</div></div>';
		$res = pg_query($this->context->getDbConn(), "select non_empty(" . $this->context->getLangLang2('text_') . ")
				from objekt_universell where id=$id");
		$beschr = '';
		if (pg_num_rows($res))
			list($beschr) = pg_fetch_row($res, 0);
		if ($beschr && $beschr == strip_tags($beschr))
			$beschr = "<p>$beschr</p>";
		if ($beschr && $fak)
			$out .= '<section class="teaser">' . $beschr . '</section>';
		$out .= '
		</section>
		';
		if ($beschr && !$fak)
			$out .= '<section class="text full">' . $beschr . '</section>';

		return $out;
	}

	public function create_termine($args)
	{
		$args = explode(',', $args);
		$anzahl = $args[0] ?? 3;
		$text_only = $args[1] ?? false;
		$row1 = $this->context->row1;
		$ls_link = $this->context->org_folder;
		$lang = $this->context->lang;
		$lang2 = $this->context->lang2;
		if (!$row1['te_terminanzahl'])
			$row1['te_terminanzahl'] = 5;
		if (is_numeric($anzahl) && $anzahl)
			$row1['te_terminanzahl'] = $anzahl;
		if (!is_numeric($row1['te_terminanzahl']))
			$row1['te_terminanzahl'] = 5;
		$res = pg_query($this->context->getDbConn(), "select non_empty(k.kategorie,'') as kategorie,
				t.datum=now()::date or (t.datum_bis >=now()::date and t.datum<=now()::date) as heute,
				case when t.datum_nur_monat then to_char(t.datum,'MM/YYYY')
				else d1.kurz_$lang||'.&nbsp;'||to_char(t.datum,'" . $this->t('YYYY-MM-DD', "DD.MM.YYYY") . "') end as fdatum,
				to_char(t.von,'HH24:MI') as fvon,to_char(t.bis,'-HH24:MI') as fbis, t.id,
				non_empty(t.$lang,t.$lang2) as titel
				from termine t left outer join tag d1 on d1.id=extract(DOW from t.datum)
				left outer join (select non_empty(k.$lang,k.$lang2) as kategorie, v.id_von
				from verweis v, objekt k, art_objekt ao where v.id_auf=k.id and k.geloescht is null
				and k.id_art=ao.id and ao.uname='t_kategorie') k on k.id_von=t.id, art_objekt ao,
				objekt$row1[id] o where o.id=t.id and o.id_art=ao.id and ao.uname='termin'
				and t.id in (select t.id from termine t, objekt$row1[id] o
				where t.id=o.id and (t.datum>=now()::date or (t.datum_bis >=now()::date and
				t.datum<=now()::date)) order by t.datum limit 
				" . ($row1['te_terminanzahl'] + 0) . ") order by kategorie,t.datum,t.von");
		if (!pg_num_rows($res))
			return '<!-- INSERT TERMINE: nothing to display -->';
		$out = '';
		if (!$text_only)
			$out .= '<section class="sidebar grey termine">
		<h3>' . ($lang == 'de' ? "Aktuelle Termine" : 'Upcoming ...') . '</h3>
		<br/>
		';
		$kategorie = "";
		for ($i = 0; $i < min(pg_num_rows($res), $row1['te_terminanzahl']); $i++) {
			$r = pg_fetch_array($res, $i);
			if ($i > 0)
				$out .= '<hr/>';
			if ($kategorie != $r['kategorie'] && $r['kategorie']) {
				$kategorie = $r['kategorie'];
				$out .= "<h4>$kategorie:</h4>";
			}
			$out .= '<p>';
			$out .= ($r['heute'] == "t" ? "<span style=\"color:#ff0000\">" : "") . "$r[fdatum]" . ($r['heute'] == "t" ? "</span>" : "") .
				"<br/><strong>$r[titel]</strong><br/><a href=\"/$ls_link/$lang/aktuelles/termine/detail.php?id_obj=$r[id]\">... " .
				($lang == 'de' ? "mehr" : 'more') . "</a>
			</p>\n";
		}
		if (pg_num_rows($res) == $row1['te_terminanzahl'])
			$out .= "<hr/><p><strong><a href=\"/$ls_link/$lang/aktuelles/termine/termine.php\">" . ($lang == 'de' ? "Alle Termine" : 'show all') .
				"</a></strong></p>
			<br/>";
		if (!$text_only)
			$out .= "</section>";
		return $out;
	}

	public function create_news($args)
	{
		$args = explode(',', $args);
		$limit = $args[0] ?? 3;
		$weather = $args[1] ?? false;
		$text_only = $args[2] ?? false;
		$kat_select = $args[3] ?? 0;
		$kat_exclude = $args[4] ?? 0;
		$no_link = $args[5] ?? false;

		$datum = date("Y-m-d");
		$out = '';
		if (!$text_only)
			$out .= '<section class="sidebar grey news">
		<h3>' . $this->t('News ...', "Neuigkeiten") . '</h3>
		<br/>
		';
		if ($weather) {
			include __DIR__ . '/ubt5/wetter.inc';
			$out .= '<h4>' . $this->t('Current Weather', 'Aktuelle Messwerte') . "</h4>
				<p>$wetter</p><hr>";
		}
		$res = pg_query($this->context->getDbConn(), "select non_empty(" . $this->context->getLangLang2('n.') . ") as titel,
				non_empty(" . $this->context->getLangLang2('n.text_') . ") as text,
				non_empty(" . $this->context->getLangLang2('n.lang_') . ") as ltext,
				non_empty(" . $this->context->getLangLang2('n.url_') . ") as url,b.*,n.*, c." . $this->context->lang . " as news_kat,
				to_char(n.von,'DD.MM.YYYY') as fvon from
				news n left outer join bild b on b.id_obj=n.id and b.de='teaserbild', objekt" . $this->context->getOrgId() . " o, objekt c
				where n.id=o.id and n.id_kat=c.id and n.von<='$datum' and  ( n.bis>='$datum' or n.bis is null)"
			. ($kat_exclude ? " and c.id!=$kat_exclude" : '') . ($kat_select ? " and c.id=$kat_select" : '') . "
				and not n.intern order by n.von desc, n.ordnung,c." . $this->context->lang . " limit $limit");

		if (!pg_num_rows($res) && !$weather)
			return '<!-- INSERT NEWS: nothing to display -->';
		$kat = '';
		for ($i = 0; $i < pg_num_rows($res); $i++) {
			$r = pg_fetch_array($res, $i);
			if ($kat != $r['news_kat']) {
				$kat = $r['news_kat'];
				$out .= "<h4>$kat</h4>\n";
			}
			$r['text'] = strip_tags($r['text'], '<a><strong><em><br><br/>');
			$out .= "<p>$r[fvon]<br/>
			<strong>$r[titel]</strong><br/>" . ($r['name'] ? '</p><p><img class="nomobile" src="/' . $this->context->org_folder . '/de/image/' . $r['name'] . '"><br/>' : '') . "
			$r[text]
			";
			if ($r['ltext'])
				$r['url'] = "/" . $this->context->getOrgLinkLang() . "/aktuelles/news/detail.php?id_obj=$r[id]";

			if ($r['url'])
				$out .= "<a href=\"$r[url]\">..." . $this->t('more', 'mehr') . "</a>\n";
			$out .= "</p><hr>";
		}
		if (!$no_link)
			$out .= "<br/><p>
		<a href=\"/" . $this->context->getOrgLinkLang() . "/aktuelles/news/news.php\">" . $this->t('All News', 'Alle Neuigkeiten') . "</a>
</p>";
		if (!$text_only)
			$out .= "</section>";
		return $out;
	}


	public function create_new_pubs($args)
	{
		list($limit, $day_limit) = explode(',', $args);
		if (!is_numeric($limit))
			$limit = 3;
		if (!is_numeric($day_limit) || !$day_limit)
			$day_limit = 365;
		$config = $this->context->getModConfig("pub", array('%format_string', 'ignore_pubdate', 'autoren_format', 'eref%'));
		if (!is_array($GLOBALS['config'] ?? false))
			$GLOBALS['config'] = $config;
		else
			$GLOBALS['config']['autoren_format'] = $config['autoren_format'];

		if (!$config['eref_url'])
			return '';


		require_once $this->context->BayCMSRoot . '/inc/pub/eref.inc';
		$eref_json_default = '{
"authors":{
    "sep":",",
    "abbreviate_firstname":1,
    "surname_put_first":1,
    "name_sep":","
  },
"groups": {
    "Peer reviewed":{"type":"article","refereed":"yes"},
    "Books and Book sections":["book","book_section"],
    "Other publications":"*"
},
"list":{"order2":"years"},
"templates":{
"article":"<p>${authors}: <a href=\\"${url}\\">${title}</a>.  ${publication}, <strong>${volume}</strong>ISSET{${number}}{(${number})}ISSET{${pagerange}}{, ${pagerange}} (${year}).ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"book":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. -ISSET{${editors}}{ ${editors} (eds.). -} ${place_of_pub} : ${publisher}, ${year}.ISSET{${pages}}{ - ${pages}}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"book_section":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. <i> In: </i>ISSET{${editors}}{${editors} (eds.): }${book_title}. - ${place_of_pub} : ${publisher}, ${year}.ISSET{${pagerange}}{ - ${pagerange}.}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"article_paper":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. In: ${publication}, (${date})ISSET{${pagerange}}{, ${pagerange}}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"conference_item":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - (${pres_type_trans}), ${event_title}, ${event_dates}, ${event_location}.ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"review":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. ISSET{${title_reviewed}}{ (Rezension von: &quot;${title_reviewed}&quot;) }<i>In:</i>${publication}${book_title}, <strong>${volume}</strong>, S. ${pagerange}: ${year}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"preprint":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - ${place_of_pub}, ${date}. - ISSET{${pages}}{${pages} p.}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"working_paper":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - ${place_of_pub}, ${year}. - ISSET{${pages}}{${pages} p.}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"thesis":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - ${place_of_pub}: ${publisher}, ${year}.ISSET{${pages}}{ -  ${pages} p.}<br/>(Thesis, ${year}, ${institution})ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
 "report":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. -ISSET{${corp_creators}}{${corp_creators} (eds.),} ${place_of_pub}, ${year}. ISSET{${pages}}{${pages} p.}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
 "legal_commentary":"<p>${authors}:  <a href=\\"${url}\\">${title}.</a>, In: ${editors} (eds.): ${book_title}. - ${place_of_pub}: ${publisher}, ${year}ISSET{${pagerange}}{, p. ${pagerange}}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"translation":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - Transl.: ${translator}. - ${place_of_pub}, ${year}.ISSET{${pages}}{ - ${pages} S.}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"encyclopedia":"<p>${authors}: <a href=\\"${url}\\">${title}  </a>. In: ${editors} (Hrsg.): ${book_title}. - ${place_of_pub}: ${publisher}, ${year}.ISSET{${pagerange}}{ - p. ${pagerange}}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"patent":"<p>${authors}: <a href=\\"${url}\\">${title}</a><br/> ${id_number}, (${patent_date})ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"periodical_part":"<p><a href=\\"${url}\\">${title}</a>. - ${editors} (Hrsg.). - ${publication}, ${volume} (${year}), ${number}ISSET{${pages}}{, ${pages} S.}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"series_editor":"<p><a href=\\"${url}\\">${title}</a>. - ${editors} (eds.). - ${place_of_pub}: ${publisher}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"online":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. ${publication}, ${date}ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"bachelor":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - ${place_of_pub}, ${year}. ISSET{${pages}}{${pages} p.}<br/>(${thesis_type_trans}, ${year}, ${institution})ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"master":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - ${place_of_pub}, ${year}. ISSET{${pages}}{${pages} p.}<br/>(${thesis_type_trans}, ${year}, ${institution})ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>",
"habilitation":"<p>${authors}: <a href=\\"${url}\\">${title}</a>. - ${place_of_pub}, ${year}.  ISSET{${pages}}{${pages} p.}<br/>(${thesis_type_trans}, ${year}, ${institution})ISSET{${related_doi}}{<br/><a href=\\"https://dx.doi.org/${related_doi}\\">${related_doi}</a>}</p>"
},
"trans":{
 "masters":"Master thesis",
 "ma":"Magisterarbeit",
 "diploma":"Diploma thesis",
 "admission":"Zulassungsarbeit",
 "paper":"Paper",
 "lecture":"Lecture",
 "speech":"Speech",
 "poster":"Poster",
 "keynote":"Keynote",
 "other":"Other"
}
		        
}';

		if (!$config['eref_config'])
			$config['eref_config'] = $eref_json_default;
		$json = json_decode($config['eref_config'], true);
		$json['filter']['limit'] = $limit;
		$json['filter']['newer_than'] = date('Y-m', time() - 3600 * 24 * $day_limit);
		$p = new \erefParser();
		return $p->run($config['eref_url'], json_encode($json), 0);




	}


	public function getHead()
	{
		$out = '<!DOCTYPE html>
        <html class=" js flexbox canvas canvastext webgl no-touch geolocation postmessage no-websqldatabase indexeddb hashchange history draganddrop websockets rgba hsla multiplebgs backgroundsize borderimage borderradius boxshadow textshadow opacity cssanimations csscolumns cssgradients no-cssreflections csstransforms csstransforms3d csstransitions fontface generatedcontent video audio localstorage sessionstorage webworkers applicationcache svg inlinesvg smil svgclippaths">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($this->context->title) . '</title>
        <meta name="layout" content="main">
        <script type="text/javascript" src="/javascript/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="/javascript/jquery-ui/jquery-ui.min.js"></script>
        <script type="text/javascript" src="/jquery/jquery.datetimepicker.full.min.js"></script>
        <script type="text/javascript" src="/jquery/jquery.minicolors.min.js"></script>
        <style type="text/css">@import "/javascript/jquery-ui/themes/base/jquery-ui.min.css";</style>
        <style type="text/css">@import "/jquery/jquery.datetimepicker.min.css";</style>
        <style type="text/css">@import "/jquery/jquery.minicolors.css";</style>
		<script src="/baycms-template/ubt5/js/modernizr-2_6_2_min.js"></script>
		<script src="/baycms-template/bootstrap/js/bootstrap.min.js"></script>
        <link rel="image_src" href="/baycms-template/ubt5/i/fbThumb.png">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/common.css">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/normalize.css">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/main01.css">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/responsive.css">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/component.css">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/glyphicons.css">
        <link rel="stylesheet" href="/baycms-template/ubt5/css/popover.css">
		';
		if ($this->context->getOrgFavicon())
			$out .= '<link rel="shortcut icon" href="' . $this->context->getOrgFavicon() . '">' . "\n";
		else
			$out .= '<link rel="shortcut icon" href="/baycms-template/ubt5/favicon.ico">' . "\n";

		$out .= $this->context->ADDITIONAL_HTML_HEAD;
		$out .= $this->get('header_style');
		$out .= '
        <link rel="stylesheet"
            href="/baycms-template/ubt5/css/baycms4.0.css">
        <link rel="stylesheet"
            href="/baycms-template/ubt5/css/bayconf.css">
        ';
		$out .= '<meta
		content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"
		name="viewport" charset="utf-8">
	<meta name="viewport" http-equiv="X-UA-Compatible"
		content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
	<meta name="viewport"
		content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
	<meta name="viewport"
		content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
	<meta name="viewport"
		content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
	<meta name="viewport"
		content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
	';
		if ($this->context->get('row1', 'te_nosearch') == 't')
			$out .= '
			<style type="text/css">
			@media screen and (min-width: 769px)  
			{
			#ls-logo { max-width:350px;}
			#metanavigation { width: 300px;}
			}
			</style>
			';
		$out .= '</head>';
		return $out;
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

		$icon = (isset($icons[$type]) ? $icons[$type] : $type);

		return '<a href="' . $url . '" ' . $attrib . ' class="baycms_action_link">
		<span class="glyphicon glyphicon-' . $icon . '"></span> ' .
			$text . '</a>';
	}

	public function printHeader()
	{
		if ($this->header_out)
			return;
		echo $this->getHead();
		echo '<body ' . $this->context->additional_body_attributes . '>';
		if ($this->context->no_frame)
			return;

		echo '
		<p class="printUrl printOnly">
		Druckansicht der Internetadresse:<br>
		<script> document.write(document.URL); </script>
	</p>

	<a name="top"></a>
	<!--[if lt IE 7]>
            <p class="chromeframe">Sie benutzen einen <strong>veralteten</strong> Browser. Bitte <a href="http://browsehappy.com/">aktualisieren Sie Ihren Browser</a> oder <a href="http://www.google.com/chromeframe/?redirect=true">aktivieren Sie Google Chrome Frame</a>, um Ihr Surf-Erlebnis zu verbessern.</p>
        <![endif]-->
	<header>
		<a href="https://www.uni-bayreuth.de/" id="logo"
			title="Universtität Bayreuth Startseite"> <img src="/baycms-template/ubt5/logo-university-of-bayreuth.png"
			alt="Logo of the University of Bayreuth">
		</a>';
		if ($this->context->get('row1', 'org_logo')) {
			$logolink = $this->context->get('row1', 'te_logolink');
			$alt = strip_tags($this->context->getRow1String(''));
			if (!$logolink)
				$logolink = '/' . $this->context->org_folder . '/?lang=' . $this->context->lang;
			echo ' <a href="' . $logolink . '" id="ls-logo" title="' . $alt . '">
			<img src="' . $this->context->getOrgLogo() . '" alt="Logo ' . $alt . '">
			</a>';
		}
		echo '<nav id="metanavigation">
			<ul>
				<li id="btLogin"><a href="' . $this->context->get('H_LoginLogout', 'url') . '">' .
			$this->context->get('H_LoginLogout', 'text') . '</a>
				</li>';
		if ($this->context->get('row1', 'te_nobilang') != 't') {
			echo '<li id="bt' . $this->t('Deutsch', 'English') . '">
					<a href="' . $this->getLang2Link() . '">' .	$this->t('deutsch', 'english') . '</a></li>';

		}
		echo '</ul>
			</nav>
			<div style="clear: right;"></div>';
		if ($this->context->get('row1', 'te_nosearch') != 't') {
			echo '<form style="display: block;" name="search" id="searchform"
				action="https://uni-bayreuth.de/suche"
				method="get">
				<label for="search">Suche</label><input type="text" name="q" id="search" value="' .
				$this->t('Search', 'Suche') . '"
					autocomplete="on"> <input type="submit" name="sa" value="Suche">
				<!-- Funktioniert nicht!....
		<input title="Suche" name="search" id="search" type="text">
					<input name="Abschicken" value="submit" type="submit"> -->
			</form>';
		}
		$res = pg_query(
			$this->context->getDbConn(),
			"select non_empty(" . $this->context->getLangLang2('i.') . "),f.name,i.*
								from index_files i left outer join file f on i.id_file=f.id, kategorie k
								where i.id_super=k.id and k.link='top' and i.id_lehr=" .
			$this->context->get('row1', 'id') . "
								order by i.ordnung desc,non_empty"
		);
		$service_links = '';
		for ($i = 0; $i < pg_num_rows($res); $i++) {
			$r = pg_fetch_array($res, $i);
			if ($r['name'])
				$url = "/" . $this->context->getOrgLinkLang() . "/$r[name]$r[qs]";
			elseif ($r['url'])
				$url = $r['url'];
			else
				$url = "";
			if ($url)
				$service_links .= '<li><a href="' . $url . '" class="blank">' . $r['non_empty'] . '</a></li>' . "\n";
		}
		if ($service_links) {
			echo '<nav id="schnelleinstieg">
				<ul>
					<li><a href="#" class="main">' . $this->t('Service Links', 'Servicelinks') . '</a>
						<ul>' . $service_links . '</ul>
					</li>
				</ul>
			</nav>';
		}
		$homelink = 'http://' . ($this->context->get('row1', 'httphost') ?
			$this->context->get('row1', 'httphost') : $_SERVER['HTTP_HOST']) . "/" . $this->context->org_folder .
			"/index.php?lang=" . $this->context->lang;
		$this->settings['breadcrumb_url'] = [$homelink];
		$this->settings['breadcrumb_text'] = ["Home"];

		$responsive_nav = '';
		if ($this->context->min_power && !$this->context->IP_AUTH_OK) {
			$intern_extern_link = '<li><a href="' . $homelink . '">
			&lt;&lt; ' . $this->t('external site', 'externe Seiten') . "</a></li>";
		} elseif ($this->context->AUTH_OK) {
			if ($_SERVER['HTTP_HOST'] == "localhost")
				$url = "http://localhost";
			elseif ($this->context->NOSSL)
				$url = "";
			elseif ($this->context->get('row1', 'httpshost'))
				$url = "https://" . $this->context->get('row1', 'httpshost');
			else
				$url = "https://www.bayceer.uni-bayreuth.de";
			$intern_extern_link = "<li><a href=\"$url/" . $this->context->getOrgLinkLang() .
				"/" . $this->getLinkInternal() . "\">&gt;&gt; " . $this->t('internal site', 'interne Seiten') . "</a> </li>";
		} else
			$intern_extern_link = '';
		$nav = '<li><a href="/' . $this->context->org_folder . "?lang=" . $this->context->lang . '">Home</a></li>';
		$res = pg_query($this->context->getDbConn(), $this->context->H_kat_query);
		$id_kat = 1000;

		for ($i = 0; $i < pg_num_rows($res); $i++) {
			$r = pg_fetch_array($res, $i);
			$nav .= '<li><a href="' . $r['url'] . '"' . ($r['target'] ? $r['target'] : '') . '>' . $r['text'] . '</a>' .
				($r['link'] == $this->context->kategorie ? '<!--INSERT CHILDS HERE-->' : '') . '</li>';
			$tmp = $this->getNavigation($r);
			if ($tmp) {
				$r['url'] = "#";
			}
			if ($r['link'] == $this->context->kategorie)
				$id_kat = $r['id'];
			$responsive_nav .= '<li' . ($r['link'] == $this->context->kategorie ? ' class="active"' : '') .
				'><a href="' . $r['url'] . '">' . $r['text'] . '</a>' . $tmp . '</li>';
		}
		$responsive_nav .= $intern_extern_link;
		$nav .= $intern_extern_link;

		$temp = explode("/", $this->context->php_file, 2);
		if (!isset($temp[1]))
			$temp[1] = '';
		if ($temp[1] ?? false)
			$temp[0] .= "/";
		if ($_SERVER['QUERY_STRING'] ?? false)
			$temp[1] .= "?$_SERVER[QUERY_STRING]";


		if (!isset($this->settings['ubt_no_fulltextsection']))
			$this->settings['ubt_no_fulltextsection'] = (($this->context->modul == 'gru'
				&& $this->context->php_file == 'html.php') || !$this->context->modul);

		$res = pg_query(
			$this->context->getDbConn(),
			"select get_index_id('" . $this->context->kategorie . "/" .
			$this->context->modul . "/$temp[0]','$temp[1]'," .
			$this->context->get('row1', 'id') . ",$id_kat)"
		);

		[$index_id] = pg_fetch_row($res, 0);
		if (is_numeric($index_id)) {
			$res = pg_query(
				$this->context->getDbConn(),
				"select f.name,non_empty(" . $this->context->getLangLang2('i.') . "),i.* 
				from index_files i left outer join file f on f.id=i.id_file 
				where i.id=$index_id"
			);
			$r = pg_fetch_array($res, 0);
			$tmp_url = array(($r['name'] ? "/" . $this->context->getOrgLinkLang() . "/$r[name]$r[qs]" : "$r[url]"));
			$tmp_text = array($r['non_empty']);
			while ($r['id_super'] > 0 && $r['id_super'] != $id_kat) {
				$res = pg_query(
					$this->context->getDbConn(),
					"select f.name,non_empty(" . $this->context->getLangLang2('i.') . "),i.* 
						from index_files i left outer join file f on f.id=i.id_file 
						where i.id=$r[id_super]"
				);
				$r = pg_fetch_array($res, 0);
				$tmp_url[] = ($r['name'] ? "/" . $this->context->getOrgLinkLang() . "/$r[name]$r[qs]" : "$r[url]");
				$tmp_text[] = $r['non_empty'];
			}
			for ($i = count($tmp_url) - 1; $i >= 0; $i--) {
				$this->settings['breadcrumb_url'][] = $tmp_url[$i];
				$this->settings['breadcrumb_text'][] = strip_tags($tmp_text[$i]);
			}
		}

		if ($this->context->object_id && ($r['qs'] ?? '') != "?id_obj=" . ($_GET['id_obj'] ?? ''))
			$this->settings['breadcrumb_text'][] = strip_tags($this->context->object_title);
		if (!is_numeric($index_id) && $id_kat != 1000)
			$index_id = $id_kat;
		if (is_numeric($index_id)) {
			$res = pg_query(
				$this->context->getDbConn(),
				"select get_ubt5_index($index_id," .
				$this->context->get('row1', 'id') . ",'" .
				$this->context->get('row1', 'link') . "','" . $this->context->lang . "')"
			);
			[$sub_nav] = pg_fetch_row($res, 0);
		} else {
			$sub_nav = '';
		}
		$nav = str_replace('<!--INSERT CHILDS HERE-->', $sub_nav, $nav);

		if ($this->context->get('row1', 'te_bayceermember') == "t")
			$nav .= "<li><a href=\"http://www.bayceer.uni-bayreuth.de\">
			<img src=\"/baycms-template/ubt5/member_bayceer2.jpg\" border=0 width=120 height=60 alt=\"BayCEER Member\"></a></li>\n";
		if (strstr('/' . $this->context->get('row1', 'link'), "/gce"))
			$nav .= "<li><a href=\"" . ($this->context->lang == 'de' ?
				"https://www.elitenetzwerk.bayern.de" : "https://www.elitenetzwerk.bayern.de/en/home") . "\">
			<img src=\"/baycms-template/ubt5/enb_n_" . $this->context->lang . ".png\" border=0 width=172 height=85 alt=\"Elite Network of Bavaria\"></a></li>\n";

		if ($this->context->get('row1', 'te_supbayceer') == "t")
			$nav .= "<li><a href=\"http://www.bayceer.uni-bayreuth.de?lang=" . $this->context->lang . "\">
		<img src=\"/baycms-template/ubt5/supported_by_bayceer.jpg\" border=0 width=120 height=60 alt=\"Supported by BayCEER\"></a></li>\n";

		if ($this->context->get('row1', 'te_membergi') == "t")
			$nav .= "<li><a href=\"http://www.geographie.uni-bayreuth.de/de/index.html\">
		<img src=\"/baycms-template/ubt5/member_gi.jpg\" border=0 width=120 height=60 alt=\"Member GI\"></a></li>\n";

		if ($this->context->get('row1', 'te_orggce') == "t")
			$nav .= "<li><a href=\"http://www.bayceer.uni-bayreuth.de/gce/\">
		<img src=\"/baycms-template/ubt5/Organizer_of_GCE_WEB.jpg\" border=0 width=120 height=60 alt=\"Organizer of GCE\"></a></li>\n";

		echo '<div class="responsive" id="menu">
		<a href="#" id="respMenu" class="dl-trigger">Menü</a>
		<div id="dl-menu" class="dl-menuwrapper">
			<button>mobiles Menü</button>
			<ul class="dl-menu">
				<li class="dl-close"><a href="#">schließen</a></li>
				<li class="active"><a href="/' .
			$this->context->org_folder . "/?lang=" . $this->context->lang . '">Home</a>
				</li>' .
			$responsive_nav . '
			</ul>
		</div>';
		if ($service_links)
			echo '
		<a href="#" id="respQuicklinks">Servicelinks</a>
		<div id="ql-menu" class="dl-menuwrapper">
			<button>mobiles Schnelleinstieg Menü</button>
			<ul class="dl-menu">
				<li class="dl-close"><a href="#">schließen</a></li>
				' . $service_links . '
			</ul>
		</div>';
		echo '
		<a href="#" id="respSearch">Suche</a>
	</div>
	<!-- Ende Mobile Navigation -->';

		echo '<h2 id="headline">
			<strong><a href="' . $this->context->get('row1', 'te_link_fak') . '">' .
			$this->context->getRow1String('head_') . '
			</a> </strong> <span class="hidden">Schnelleinstieg</span><a href="#"
				class="btCloseQuicklinks hidden">Zurück zur Hauptnavigation</a>
		</h2>';

		$subhead = $this->context->getRow1String('subhead_');
		if ($subhead)
			echo "<h3>" . $subhead . "</h3>";
		echo '<nav id="breadcrumb">
		';
		for ($i = 0; $i < count($this->settings['breadcrumb_text']); $i++) {
			if ($i == (count($this->settings['breadcrumb_text']) - 1))
				echo "<a href=\"#\" class=\"active\">" . $this->settings['breadcrumb_text'][$i] . "</a>";
			else
				echo "<a href=\"" . $this->settings['breadcrumb_url'][$i] . "\">" .
					$this->settings['breadcrumb_text'][$i] . "</a>";
			if ($i < count($this->settings['breadcrumb_text']) - 1)
				echo "&nbsp;&nbsp;&gt;&nbsp;&nbsp;";
		}
		echo '</nav>
			<a href="#" id="btPrint">' . $this->t('print page', 'Seite drucken') . '</a>
	</header>

	<section id="main">

		<!-- Navigation groß! -->
		<nav id="navigation">
			<ul>' . $nav . '</ul>
			</nav>
	
			<section id="content">';
		if (!$this->settings['ubt_no_fulltextsection'])
			echo '<section class="text full">';

		echo '
<!--  Begin Content -->
';
		$this->header_out = true;
	}


	public function printFooter()
	{
		if (!$this->context->no_frame) {
			if (!$this->get('ubt_no_fulltextsection'))
				echo '</section>';

			echo ' </section>
			</section>';

			$social = '';
			if ($link = $this->context->get('row1', 'te_link_facebook'))
				$social .= '<a href="' . $link . '" class="facebook" title="Facebook" target="_blank">Facebook</a>';
			if ($link = $this->context->get('row1', 'te_link_twitter'))
				$social .= '<a href="' . $link . '" class="twitter" title="Twitter" target="_blank">Twitter</a>';
			if ($link = $this->context->get('row1', 'te_link_bluesky'))
				$social .= '<a href="' . $link . '" class="bluesky" title="Bluesky" target="_blank">Bluesky</a>';
			if ($link = $this->context->get('row1', 'te_link_instagram'))
				$social .= '<a href="' . $link . '" class="instagram" title="Instagram" target="_blank">Instagram</a>';
			if ($link = $this->context->get('row1', 'te_link_youtube'))
				$social .= '<a href="' . $link . '" class="youtube" title="Youtube-Kanal" target="_blank">Youtube-Kanal</a>';
			if ($link = $this->context->get('row1', 'te_link_blog'))
				$social .= '<a href="' . $link . '" class="blog" title="Blog" target="_blank">Blog</a>';
			if ($link = $this->context->get('row1', 'email'))
				$social .= '<a href="mailto:' . $link . '" class="contact" title="Kontakt aufnehmen" target="_blank">Kontakt aufnehmen</a>';

			echo ' <section id="social">
            ' . $social . '
        </section>

        <footer>
            <p class="social mobile">
                <span>Die Universität Bayreuth in sozialen Medien:</span>
            ' . $social . '
            </p>
            <p class="links">
                <!-- <a href="#" title="Ansprechpartner">Ansprechpartner</a> -->
                <a href="/' . $this->context->getOrgLinkLang() . '/top/gru/impressum.php" title="Impressum">Impressum</a>
                <!-- <a href="#" title="Disclaimer">Disclaimer</a> -->
                <a href="/' . $this->context->getOrgLinkLang() . '/top/gru/sitemap.php" title="Sitemap">Sitemap</a>';
			if ($link = $this->context->get('row1', 'te_link_contact'))
				echo '<a href="' . $link . '" title="Kontakt &amp; Anfahrt">Kontakt</a>';
			echo '
            </p>
        </footer>';
			$this->printCookieDingsBums();


		} //Ende no frame

		echo '<script  src="/baycms-template/ubt5/js/plugins.js"></script>
        <script  src="/baycms-template/ubt5/js/main.js"></script>';
		echo '</body>
		</html>';
		exit();


	}

	function getNavigation($kat_row)
	{
		if ($kat_row['link'] == $this->context->kategorie) {
			if (
				!pg_num_rows(
					pg_query(
						$this->context->getDbConn(),
						"select id_kat from kat_aliases where
					id_lehr=" . $this->context->get('row1', 'id') . " and id_kat=$kat_row[id] and no_dp_first union
					select id from kategorie where id=$kat_row[id] and no_dp_first"
					)
				)
				|| !($_SERVER['PHP_SELF'] == $kat_row['url'] || $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] == $kat_row['url'])
			) {
				$this->settings['breadcrumb_url'][] = $kat_row['url'];
				$this->settings['breadcrumb_text'][] = strip_tags($kat_row['text']);
			}
		}
		$ret = '';
		$res2 = pg_query(
			$this->context->getDbConn(),
			"select * from 
		get_full_index_table($kat_row[id],0,''," . $this->context->get('row1', 'id') . ",'" .
			$this->context->org_folder . "','" . $this->context->lang . "')"
		);
		$no_dropdown = 0;
		$level = 1;
		if (pg_num_rows($res2) == 1) {
			$r2 = pg_fetch_array($res2, 0);
			if ($r2['url'] == $kat_row['url'])
				$no_dropdown = 1;
		}
		if (!$no_dropdown && pg_num_rows($res2)) {
			$ret .= '
			<ul class="dl-submenu">
			<li class="dl-close"><a href="#">schließen</a></li>
			<li class="dl-back"><a href="#">zurück</a></li>
			<li class="active"><a href="' . $kat_row['url'] . '">' . $kat_row['text'] . '</a></li>';

			$r2 = pg_fetch_array($res2, 0);

			for ($j = 1; $j < pg_num_rows($res2) + 1; $j++) {
				if ($j < pg_num_rows($res2))
					$r_next = pg_fetch_array($res2, $j);
				else
					$r_next['level'] = 1;
				$class_active = '';
				if (strstr($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'], $r2['url'])) {
					//$class_active=' class="active"';
				} else
					$class_active = '';
				if ($r2['target'] > '')
					$target = ' target="' . $r2['target'] . '"';
				else
					$target = '';
				if (!$r2['url'])
					$r2['url'] = "#";

				if ($r_next['level'] > $r2['level']) {
					//Submenue!
					$ret .= '<li' . $class_active . '><a href="#"' . $target . '>&nbsp;&nbsp;&nbsp;&nbsp;' . $r2['name'] . '</a>
					<ul class="dl-submenu">
					<li class="dl-close"><a href="#">schließen</a></li>
					<li class="dl-back"><a href="#">zurück</a></li>
					<li class="active"><a href="' . $r2['url'] . '">' . $r2['name'] . '</a></li>';

				} else
					$ret .= '<li' . $class_active . '><a href="' . $r2['url'] . '"' . $target . '>&nbsp;&nbsp;&nbsp;&nbsp;' . $r2['name'] . '</a></li>' . "\n";
				if ($r_next['level'] < $r2['level']) {
					for ($l = $r2['level']; $l > $r_next['level']; $l--) {
						$ret .= '
					</ul></li><!-- close level ' . $l . ' -->
					';
					}
				}
				$r2 = $r_next;
			}
			$ret .= '</ul><!-- end -->
';
		}
		return $ret;

	}

}