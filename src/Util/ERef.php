<?php
/**
 * ERef-Parser
 * 
 * $p = new \BayCMS\Util\ERef();
 * $p->run($eref_url, $json_config);
 */
namespace BayCMS\Util;

define('erefParserVersion', 'v2023-05-25');

class ERef
{

    private ?array $config=null;
    // ///////////////////////////////////////////////////////////////////
    // Funktionen zur Darstellung
    function cleanup_template($raw)
    {
        $raw = str_replace(array("\n", "\r"), " ", $raw);
        $raw = preg_replace('/\\$\\{[a-z_]+\\}/', '', $raw); // remove remaining variables
        $raw = preg_replace('/ISSET\\{\\s*\\}\\{([^\\}]*)\\}/', '', $raw); //remove empty ISSET{}{Hers. }
        $raw = preg_replace('/ISSET\\{[^\\}]+\\}\\{([^\\}]*)\\}/', '\1', $raw); //remove ISSET{content}{Hers. content}
        $raw = preg_replace('/<[sebiu][^>]*><\\/[sebiu][^>]*>/', "", $raw); // remove empty tags
        $raw = preg_replace('/^<p> *:/', '<p>', $raw); // remove ":" at start of publication (== no author)
        $raw = preg_replace('/^ *:/', '', $raw); // remove ":" at start of publication (== no author)
        $raw = preg_replace('/([,:;]) *[,:;]/', '\1', $raw); // remove ", ," or ": ," or "; ," sequence
        $raw = preg_replace('/([,:;]) *[,:;]/', '\1', $raw);
        return $raw;
    }

    function format_institution(&$institution)
    {
        $ret = "";
        for ($i = 0; $i < count($institution); $i++) {
            if ($i > 0)
                $ret .= ", ";
            $ret .= $institution[$i];
        }
        return $ret;
    }

    function format_editors(&$autoren)
    {
        $ret = "";
        for ($i = 0; $i < count($autoren); $i++) {
            if ($i > 0)
                $ret .= ", ";
            $ret .= $autoren[$i]->given . ' ' . $autoren[$i]->family;
        }
        return $ret;
    }

    function format_autoren(&$autoren, &$config)
    {
        $ret = "";
        for ($i = 0; $i < count($autoren); $i++) {
            if ($config['abbreviate_firstname'])
                $firstname = mb_substr($autoren[$i]->name->given->__toString(), 0, 1) . '.';
            else
                $firstname = $autoren[$i]->name->given->__toString();
            if ($i > 0) {
                if ($i == count($autoren) - 1)
                    $ret .= $config['last_sep'] . " ";
                else
                    $ret .= $config['sep'] . " ";
            }
            if ($autoren[$i]->name->given . ' ' . $autoren[$i]->name->family == $config['highlight'])
                $ret .= "<b>";
            if ($config['surname_put_first'])
                $ret .= $autoren[$i]->name->family->__toString() . $config['name_sep'] . ' ' . $firstname;
            else
                $ret .= $firstname . $config['name_sep'] . $autoren[$i]->name->family;
            if ($autoren[$i]->name->given . ' ' . $autoren[$i]->name->family == $config['highlight'])
                $ret .= "</b>";
        }
        return $ret;
    }

    function format_pub(&$pub)
    {
        // Ersetzungsarray aufbauen
        $replace = array();
        $search = array();
        foreach ($pub->children() as $child) {
            $replace[] = str_replace(array("{", "}"), array("&#7b;", "&#7d;"), $child->__toString());
            $search[] = '${' . $child->getName() . '}';
        }
        // Template suchen
        if (isset($this->config['templates'][$pub->type->__toString()]))
            $f = $this->config['templates'][$pub->type->__toString()];
        else
            $f = $this->config['templates']['*'];
        // Ersetzen und Säubern
        return $this->cleanup_template(str_replace($search, $replace, $f)) . "\n";
    }

    // //////////////////////////////////////////////////////////////////
    // sucht zu einer gegebenen Publikation die Gruppe aus dem config-Array
    function get_group(&$pub)
    {
        if (!isset($this->config['groups']))
            return "";
        reset($this->config['groups']);
        foreach ($this->config['groups'] as $group => $gr_config) {
            $match = 1;
            foreach ($gr_config as $key => $value) {
                $match = $value[0] == '*' || (isset($pub->{$key}) && in_array($pub->{$key}->__toString(), $value));
                if (!$match)
                    break;
            }
            if ($match)
                return trim($group);
        }
        return "";
    }

    function translate($text)
    {
        if (isset($this->config['trans'][$text]))
            return $this->config['trans'][$text];
        else
            return $text;
    }

    function run($url, $json, $output = 1)
    {
        $out =  "<!-- Class BayCMS/Util/ERef erefParser Verion " . erefParserVersion . " stefan.holzheu@uni-bayreuth.de -->";

        // //////////////////////////////////////////////////////////////////
        // URL und JSON prüfen und verarbeiten
        $this->config = json_decode($json, True);

        if (!isset($url)) {
            echo "<!-- url ist nicht angegeben -- beende!-->";
            return;
        }

        if ($this->config === null) {
            echo "<!-- JSON-Config fehlerhaft oder nicht angegeben -- nutze default -->";
            $this->config = array();
        }

        mb_internal_encoding('UTF-8');

        // //////////////////////////////////////////////////////////////////
        // Groups-Config vorbereiten
        if (isset($this->config['groups'])) {
            $groups = array();
            foreach ($this->config['groups'] as $group => $gr_config) {
                $groups[] = trim($group);
                // Einfaches Format z.B. ["article","book"] in assoziatives Umwandeln
                // z.B. {"type":"conference_item","pres_type":"keynote"}
                if (!is_array($gr_config) || !isset($gr_config["type"]))
                    $gr_config = array(
                        'type' => $gr_config
                    );

                // Alle Einzelwerte in Arrays mit Länge 1 umwandeln
                // vereinfacht die spätere Verarbeitung
                foreach ($gr_config as $key => $value) {
                    if (!is_array($value))
                        $gr_config[$key] = array(
                            $value
                        );
                }

                // Umgewandelte config im config-Array speichern
                $this->config['groups'][$group] = $gr_config;
            }
        }

        // //////////////////////////////////////////////////////////////////
        // years-Config vorbereiten
        if (!isset($this->config['years'])) {
            $this->config['years'] = array(
                'min' => 1900,
                'max' => 3000
            );
        }
        if (isset($this->config['years']['selected']) && !is_array($this->config['years']['selected'])) {
            $this->config['years']['selected'] = array(
                $this->config['years']['selected']
            );
        }
        if (isset($this->config['years']['selected'])) {
            for ($i = 0; $i < count($this->config['years']['selected']); $i++) {
                $this->config['years']['map'][$this->config['years']['selected'][$i]] = 1;
            }
        }

        // //////////////////////////////////////////////////////////////////
        // Authors-Config vorbereiten
        // Default Author config
        $default_author = array(
            'sep' => ',',
            'highlight' => '',
            'last_sep' => ',',
            'abbreviate_firstname' => 0,
            'surname_put_first' => 0,
            'name_sep' => ' '
        );
        if (isset($this->config['authors']))
            $this->config['authors'] = array_merge($default_author, $this->config['authors']);
        else
            $this->config['authors'] = $default_author;

        // //////////////////////////////////////////////////////////////////
        // templates-Config vorbereiten
        // default templates -- sollte möglichst gut vorgegeben werden
        $default_templates = array(
            '*' => '<p>${authors}: <a href="${url}"><b>${title}</b></a>, ${publication}, ${volume}, ${pagerange}: ${year}</p>',
            "article" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>.  <i>In:</i> ${publication}, <b>${volume}</b> (${year}). - ISSET{${pagerange}}{S. ${pagerange}}.ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "book" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. -ISSET{${editors}}{ ${editors} (Hrsg.). -} ${place_of_pub} : ${publisher}, ${year}.ISSET{${pages}}{ - ${pages} S.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "book_section" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. <i> In: </i>ISSET{${editors}}{${editors} (Hrsg.): }${book_title}. - ${place_of_pub} : ${publisher}, ${year}.ISSET{${pagerange}}{ - S. ${pagerange}.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "article_paper" => '<p>${authors}: <a href="${url}"><b>${title}.</b></a><i> In:</i> ${publication}, (${date})ISSET{${pagerange}}{, S. ${pagerange}}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "conference_item" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. - (${pres_type_trans}), <i>Veranstaltung: </i> ${event_title}, ${event_dates}, ${event_location}.ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "review" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. ISSET{${title_reviewed}}{ (Rezension von: &quot;${title_reviewed}&quot;) }<i>In:</i>${publication}${book_title}, <b>${volume}</b>, S. ${pagerange}: ${year}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "preprint" => '<p>${authors}: <a href="${url}"><b>${title}.</b></a>. - ${place_of_pub}, ${date}. - ISSET{${pages}}{${pages} S.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "working_paper" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. - ${place_of_pub}, ${year}. - ISSET{${pages}}{${pages} S.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "thesis" => '<p>${authors}:<a href="${url}"><b>${title}</b></a>. - ${place_of_pub}: ${publisher}, ${year}.ISSET{${pages}}{ -  ${pages} S.}<br/>(Dissertation, ${year}, ${institution})ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "report" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. -ISSET{${corp_creators}}{${corp_creators} (Hrsg.),} ${place_of_pub}, ${year}. ISSET{${pages}}{${pages} S.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "legal_commentary" => '<p>${authors}:  <a href="${url}"><b>${title}.</b></a>, In: ${editors} (Hrsg.): ${book_title}. - ${place_of_pub}: ${publisher}, ${year}ISSET{${pagerange}}{, S. ${pagerange}}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "translation" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. - Übers.: ${translator}. - ${place_of_pub}, ${year}.ISSET{${pages}}{ - ${pages} S.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "encyclopedia" => '<p>${authors}: <a href="${url}"><b>${title}</b>  </a>. In: ${editors} (Hrsg.): ${book_title}. - ${place_of_pub}: ${publisher}, ${year}.ISSET{${pagerange}}{ - S. ${pagerange}}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "patent" => '<p>${authors}: <a href="${url}"><b>${title}</b></a><br/> ${id_number}, (${patent_date})ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "periodical_part" => '<p><a href="${url}"><b>${title}</b></a>. - ${editors} (Hrsg.). - ${publication}, ${volume} (${year}), ${number}ISSET{${pages}}{, ${pages} S.}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "series_editor" => '<p><a href="${url}"><b>${title}</b></a>. - ${editors} (Hrsg.). - ${place_of_pub}: ${publisher}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "online" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. In: ${publication}, ${date}ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "bachelor" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. - ${place_of_pub}, ${year}. ISSET{${pages}}{${pages} S.}<br/>(${thesis_type_trans}, ${year}, ${institution})ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "master" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. - ${place_of_pub}, ${year}. ISSET{${pages}}{${pages} S.}<br/>(${thesis_type_trans}, ${year}, ${institution})ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>',
            "habilitation" => '<p>${authors}: <a href="${url}"><b>${title}</b></a>. - ${place_of_pub}, ${year}.  ISSET{${pages}}{${pages} S.}<br/>(${thesis_type_trans}, ${year}, ${institution})ISSET{${doi}}{<br/><a href="https://dx.doi.org/${doi}">${doi}</a>}</p>'
        );
        // Nutzerspezifische templates mit Default-templates verschmelzen.
        if (isset($this->config['templates']))
            $this->config['templates'] = array_merge($default_templates, $this->config['templates']);
        else
            $this->config['templates'] = $default_templates;

        // //////////////////////////////////////////////////////////////////
        // List-Config vorbereiten
        $default_list = array(
            'editors' => '(eds.)',
            'pub_sep' => '',
            'order1_template' => '<h3>${group}</h3>',
            'order2_template' => '<h4>${group}</h4>'
        );
        if (isset($this->config['list']))
            $this->config['list'] = array_merge($default_list, $this->config['list']);
        else
            $this->config['list'] = $default_list;
        if (isset($this->config['groups']) && !isset($this->config['list']['order1']))
            $this->config['list']['order1'] = 'groups';
        if (!isset($this->config['list']['order1']))
            $this->config['list']['order1'] = 'years';
        if (!isset($this->config['list']['order3']))
            $this->config['list']['order3'] = '';

        // //////////////////////////////////////////////////////////////////
        // Trans-Config vorbereiten
        $default_trans = array(
            'masters' => 'Masterarbeit',
            'ma' => 'Magisterarbeit',
            'diploma' => 'Diplomarbeit',
            'admission' => 'Zulassungsarbeit',
            'paper' => 'Paper',
            'lecture' => 'Vorlesung',
            'speech' => 'Vortrag',
            'poster' => 'Poster',
            'keynote' => 'Programmrede',
            'other' => 'Sonstiges'
        );
        if (isset($this->config['trans']))
            $this->config['trans'] = array_merge($default_trans, $this->config['trans']);
        else
            $this->config['trans'] = $default_trans;

        // //////////////////////////////////////////////////////////////////
        // Trans-Config vorbereiten
        $default_filter = array();
        if (isset($this->config['filter']))
            $this->config['filter'] = array_merge($default_filter, $this->config['filter']);
        else
            $this->config['filter'] = $default_filter;


        $speed_times[] = microtime(true);

        // //////////////////////////////////////////////////////////////////
        // XML-Laden und zur Darstellung vorbereiten (abflachen)
        $xml = simplexml_load_file(trim($url));
        if ($xml === false) {
            echo "<!-- url ist nicht angegeben -- beende!-->";
            return;
        }
        $xml_count = count($xml->eprint);

        if (!isset($this->config['xpath']))
            $this->config['xpath'] = 'e:eprint';
        $xml->registerXPathNamespace('e', 'http://eprints.org/ep2/data/2.0');
        $xml = $xml->xpath($this->config['xpath']);

        $map = array();
        $years = array();
        $selected = 0;
        $now = date_create();
        $num_pubs = count($xml);
        for ($i = 0; $i < $num_pubs; $i++) {
            if (isset($this->config['groups'])) {
                $group = $this->get_group($xml[$i]); // Gruppe bestimmen
                if (!$group)
                    continue;
                $group_map[$group][] = $i;
            }
            $year = substr($xml[$i]->date, 0, 4);

            if (isset($this->config['years']['map'])) {
                if (!isset($this->config['years']['map'][$year]))
                    continue;
            } else {
                if ($year < $this->config['years']['min'] || $year > $this->config['years']['max'])
                    continue;
            }
            if (
                isset($this->config['filter']['originate_ubt']) &&
                $this->config['filter']['originate_ubt'] != $xml[$i]->originate_ubt->__toString()
            )
                continue;
            if (
                isset($this->config['filter']['newer_than']) &&
                $this->config['filter']['newer_than'] > substr($xml[$i]->date, 0, 7)
            )
                continue;

            // Publikation wurde _nicht_ herausgefiltert
            $selected++;
            $date = substr($xml[$i]->date, 0, 7);
            if (strlen($date) < 7)
                $date .= '-01';
            $date = date_create($date);
            $order3 = $date->diff($now)->format("%Y%M%d") . ' ' . $i;
            if ($this->config['list']['order3'] == 'author')
                $order3 = $xml[$i]->creators->item[0]->name->family . ' ' . $order3;
            if ($this->config['list']['order1'] == 'groups')
                $map[$group][$year][$order3] = $i;
            else
                $map[$year][$group][$order3] = $i;
            $years[$year] = 1;
            // Zusätzliche flache Knoten setzen (für Darstellung)
            $xml[$i]->addChild('authors', $this->format_autoren($xml[$i]->creators->item, $this->config['authors']));
            unset($xml[$i]->creators);
            $xml[$i]->addChild('year', $year);
            $xml[$i]->addChild('url', $xml[$i]->attributes()->id);
            if (isset($this->config['list']['title_short'])) {
                if (mb_strlen($xml[$i]->title, 'utf-8') > 40)
                    $xml[$i]->title = mb_substr($xml[$i]->title, 0, $this->config['list']['title_short']) . "...";
            }
            if (isset($xml[$i]->editors)) {
                $xml[$i]->editors = $this->format_autoren($xml[$i]->editors->item, $this->config['authors']) .
                    ' ' . $this->config['authors']['editors'];
            }
            if (isset($xml[$i]->book_editors)) {
                $xml[$i]->addChild('editors', $this->format_editors($xml[$i]->book_editors->item) .
                    ' ' . $this->config['authors']['editors']);
                unset($xml[$i]->book_editors);
            }
            if (isset($xml[$i]->translator)) {
                $xml[$i]->translator = $this->format_autoren($xml[$i]->translator->item, $this->config['authors']);
            }
            if (isset($xml[$i]->institution)) {
                $xml[$i]->institution = $this->format_institution($xml[$i]->institution->item);
            }
            if (isset($xml[$i]->publisher_doi))
                $xml[$i]->addChild('doi', $xml[$i]->publisher_doi->__toString());
            elseif (isset($xml[$i]->related_doi))
                $xml[$i]->addChild('doi', $xml[$i]->related_doi->__toString());
            if (isset($xml[$i]->corp_creators)) {
                $xml[$i]->corp_creators = $this->format_institution($xml[$i]->corp_creators->item);
            }
            if (isset($xml[$i]->thesis_type))
                $xml[$i]->addChild('thesis_type_trans', $this->translate($xml[$i]->thesis_type->__toString()));
            if (isset($xml[$i]->pres_type))
                $xml[$i]->addChild('pres_type_trans', $this->translate($xml[$i]->pres_type->__toString()));
        }

        // //////////////////////////////////////////////////////////////////
        // Anzeige der Publikationen
        if (!isset($groups))
            $groups = array(
                ''
            );
        if ($this->config['list']['order1'] == 'groups') {
            $o1 = $groups;
            $o2 = array_keys($years);
        } else {
            $o1 = array_keys($years);
            $o2 = $groups;
        }

        $count = 0;
        for ($i = 0; $i < count($o1); $i++) {
            if (
                isset($this->config['filter']['limit'])
                && $this->config['filter']['limit'] <= $count
            )
                break;
            if (!isset($map[$o1[$i]]))
                continue; // kein Eintrag
            $out .= str_replace('${group}', $o1[$i], $this->config['list']['order1_template']);
            for ($j = 0; $j < count($o2); $j++) {
                if (
                    isset($this->config['filter']['limit'])
                    && $this->config['filter']['limit'] <= $count
                )
                    break;
                if (!isset($map[$o1[$i]][$o2[$j]]))
                    continue; // kein Eintrag
                if (isset($this->config['list']['order2']))
                    $out .= str_replace('${group}', $o2[$j], $this->config['list']['order2_template']);
                ksort($map[$o1[$i]][$o2[$j]]);
                foreach ($map[$o1[$i]][$o2[$j]] as $key => $value) {
                    if (
                        isset($this->config['filter']['limit'])
                        && $this->config['filter']['limit'] <= $count
                    )
                        break;
                    $out .= $this->format_pub($xml[$value]);
                    $out .= $this->config['list']['pub_sep'];
                    $count++;

                }
            }
        }
        if ($output)
            echo $out;
        else
            return $out;
    }
}