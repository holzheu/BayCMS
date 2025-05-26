<?php
namespace Tests;

use function PHPUnit\Framework\assertStringContainsString;

class BayCMSTest extends \PHPUnit\Framework\TestCase
{

    protected function getRequest($url)
    {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HEADER, 1);
        curl_setopt($c, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($c, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');

        $html = curl_exec($c);

        if (curl_error($c))
            return false;

        // Get the status code
        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return ['status' => $status, 'html' => $html];
    }


    protected function postRequest($url, $data)
    {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HEADER, 1);
        curl_setopt($c, CURLOPT_HEADER, 1);
        curl_setopt($c, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($c, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($data));
        $html = curl_exec($c);

        if (curl_error($c))
            return false;

        // Get the status code
        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return ['status' => $status, 'html' => $html];
    }

    protected function runRequest(string $url, string $needle, ?array $data = null)
    {
        if ($data === null)
            $res = $this->getRequest($url);
        else
            $res = $this->postRequest($url, $data);
        assertStringContainsString($needle, $res['html']);
        return $res;
    }

    public function testHTMLPage()
    {
        $this->runRequest(
            "http://localhost/btineu/de/top/gru/html.php?id_obj=27214",
            'Lorem ipsum dolor sit amet'
        );
    }

    public function testLoginLogout()
    {
        $data = ['php_auth_user' => 'gast', 'php_auth_pw' => '48d3d52d'];
        $this->runRequest(
            "http://localhost//btineu/de/intern/gru/html.php?id_obj=24701&force_login=1",
            "HTML-Seite",
            $data
        );

        $this->runRequest(
            "http://localhost/btineu/de/intern/kli_gp/klima.php",
            "Highchart Plots"
        );

        $this->runRequest(
            "http://localhost/btineu/de/verwaltung/termine/termine.php",
            "Termine verwalten"
        );

        $url = "http://localhost/btineu/de/verwaltung/termine/termine.php?aktion=save";
        $data = [];
        $this->runRequest(
            $url,
            "darf nicht leer sein",
            $data
        );

        $data = [
            'datum' => date('Y-m-d'),
            'de' => 'PHPUnit-Test',
            'id_art' => 75,
            'bis_unsichtbar' => 'f'
        ];

        $res = $this->runRequest(
            $url,
            "Eintrag gespeichert",
            $data
        );
        $match = [];
        preg_match("|\\?id_obj=([0-9]+)&aktion=edit|", $res['html'], $match);
        $id_obj = $match[1];
        $url = "http://localhost/btineu/de/verwaltung/termine/termine.php?aktion=edit&id_obj=" . $id_obj;

        $this->runRequest(
            $url,
            "PHPUnit-Test"
        );

        $url = "http://localhost/btineu/de/verwaltung/termine/termine.php?aktion=save&id_obj=" . $id_obj;
        $data['de'] = 'PHPUnit-Test-UPDATE';
        $res = $this->runRequest(
            $url,
            "PHPUnit-Test-UPDATE",
            $data
        );
        assertStringContainsString("Eintrag gespeichert", $res['html']);


        $url = "http://localhost/btineu/de/verwaltung/termine/termine.php?aktion=del&id_obj=" . $id_obj;
        $this->runRequest(
            $url,
            "Eintrag gel"
        );

        $url = "http://localhost/btineu/de/pers/gru/objekt.php";
        $data = ['erase_ids' => $id_obj];
        $this->runRequest(
            $url,
            "erasing $id_obj",
            $data
        );

        $url = "http://localhost/btineu/de/admin/gru/user.php";
        $this->runRequest(
            $url,
            "Location: /btineu/de/top/gru/login.php"
        );


        $url = "http://localhost/btineu/de/intern/gru/index.php?aktion=logout";
        $this->runRequest(
            $url,
            "Location: /btineu/de/top/gru/login.php"
        );

        $url = "http://localhost/btineu/de/pers/gru/objekt.php";
        $this->runRequest(
            $url,
            "Location: /btineu/de/top/gru/login.php"
        );
        

    }



}