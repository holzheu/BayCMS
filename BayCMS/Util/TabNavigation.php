<?php

namespace BayCMS\Util;

class TabNavigation extends \BayCMS\Base\BayCMSBase
{
    private array $names;
    private array $descriptions;
    private array $urls;
    private int $active = 0;
    private string $qs;

    private int $max_length;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        array $names,
        array $descriptions = null,
        array $urls = null,
        int $max_length = 85,
        string $qs = ''
    ) {
        $this->names = $names;
        if (is_null($descriptions))
            $this->descriptions = $this->names;
        else
            $this->descriptions = $descriptions;

        if (is_null($urls)) {
            $this->urls = [];
            for ($i = 0; $i < count($this->names); $i++) {
                $this->urls[] = '?tab=' . $this->names[$i];
                if (($_GET['tab'] ?? '') == $this->names[$i])
                    $this->active = $i;
            }
        } else {
            $this->urls = $urls;
            for ($i = 0; $i < count($this->urls); $i++) {
                if (strstr($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'], $this->urls[$i] . '?')) {
                    $this->active = $i;
                    break;
                }
            }
        }
        $this->max_length = $max_length;
        $this->qs = $qs;
        $this->context = $context;
    }

    public function getNavigation()
    {
        if(! isset($GLOBALS['TE'])) $this->context->initTemplate();
        $out = '';
        if ($GLOBALS['TE']->isBootstrap()) {
            $out .= '<ul class="nav nav-tabs">
          ';
            for ($i = 0; $i < count($this->names); $i++) {
                $out .= '<li' . ($this->active == $i ? ' class="active"' : '') . '>
                <a href="' . $this->addQS($this->urls[$i], $this->qs) . '">' .
                    $this->descriptions[$i] . '</a></li>' . "\n";
            }
            $out .= '</ul>';

        } else {
            $out .= '<div id="navsite">
 <h5>Navigationsmenü:</h5>
 <ul>';
            $rows = [];
            $row = 0;
            $length = 0;
            $active_row = 0;

            for ($i = 0; $i < count($this->names); $i++) {
                $length += 4 + mb_strlen($this->descriptions[$i], 'UTF-8');
                $rows[$row][] = $i;
                if ($i == $this->active)
                    $active_row = $row;
                if ($length > $this->max_length) {
                    $length = 0;
                    $row++;
                }
            }
            //Background rows
            for ($j = 0; $j < count($rows); $j++) {
                if ($j == $active_row)
                    continue;
                foreach ($rows[$j] as $i) {
                    $out .= '<li><a href="' . $this->addQS($this->urls[$i], $this->qs) . '">' .
                        $this->descriptions[$i] . '</a></li>' . "\n";
                }
                $out.="<br/>";
            }
            //Active Row
            foreach ($rows[$active_row] as $i) {
                $out .= '<li><a' . ($this->active == $i ? ' class="active"' : '') .
                    ' href="' . $this->addQS($this->urls[$i], $this->qs) . '">' .
                    $this->descriptions[$i] . '</a></li>' . "\n";
            }

            $out .= '</ul>
</div>';
        }
        return $out;
    }

    private function addQS($url, $qs)
    {
        if (!$qs)
            return $url;
        if (!strstr($url, '?'))
            return $url . '?' . $qs;
        return $url . '&' . $qs;
    }




}