<?php

namespace BayCMS\Page;

class Login extends Page
{
    public function page()
    {
        $this->context->printHeader();
        if ($_GET['url'] ?? false) {
            if ($this->context->getUserId() > 0) {
                $this->context->TE->printMessage($this->t(
                    'You do not have the rights so access this page. Please use an other login.',
                    'Mit dieser Nutzerkennung haben Sie keinen Zugriff auf die angeforderte Seite. Bitte nutzen Sie eine andere Kennung.'
                ), 'danger');
            } else {
                if ($_GET['login'] ?? '')
                    $this->context->TE->printMessage($this->t(
                        'Login or password not valid.',
                        'Login oder Passwort sind nicht korrekt.'
                    ), 'danger');
                elseif ($_GET['logout'] ?? '')
                    $this->context->TE->printMessage($this->t(
                        'You have been logged out.',
                        'Sie haben sich abgemeldet'
                    ));
            }
        }

        if (!isset($_GET['url'])) {
            $res = pg_query(
                $this->context->getDbConn(),
                "select f.name,i.qs from file f,index_files i where 
            f.id_kat=400 and i.id_lehr=" . $this->context->getOrgId() . " and i.id_file=f.id 
            and i.id_super=400 order by i.ordnung desc, f.de limit 1"
            );
            if (pg_num_rows($res)) {
                $r = pg_fetch_array($res, 0);
                $link = $r['name'] . $r['qs'] . (strstr($r['qs'], '?') ? '&' : '?') . 'force_login=1';
            } else
                $link = "intern/gru/index.php?force_login=1";
            $_GET['url'] = '/' . $this->context->getOrgLinkLang() . '/' . $link;
        }

        $httpshost = $this->context->get('row1', 'httpshost');
        if (!$httpshost)
            $httpshost = $_SERVER['HTTP_HOST'];
        $httphost = $this->context->get('row1', 'httphost');
        if (!$httphost)
            $httphost = $_SERVER['HTTP_HOST'];
        $SSL_DEFAULT = !($_SERVER['HTTP_HOST'] == "localhost" || isset($GLOBALS['NOSSL']) || $this->context->get('row1', 'nossl') == 't');
        $action_ssl = "https://$httpshost/" . str_replace('"',urlencode('"'),$_GET['url']);
        $action_nossl = "http://$httphost/" . str_replace('"',urlencode('"'),$_GET['url']);

        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            action: $SSL_DEFAULT ? $action_ssl : $action_nossl,
            submit: $this->t('login', 'anmelden'),
            cancel_button: false
        );
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'PHP_AUTH_USER',
            $this->t('Username', 'Benutzername'),
            default_value: $_GET['login'] ?? ''
        ));
        $form->addField(new \BayCMS\Field\Password(
            $this->context,
            'PHP_AUTH_PW',
            $this->t('Password', 'Passwort')
        ));
        if (!isset($GLOBALS['NOSSL']))
            $form->addField(new \BayCMS\Field\Checkbox(
                $this->context,
                'ssl',
                $this->t('Use SSL', 'SSL benutzen'),
                default_value: $SSL_DEFAULT ? 't' : 'f',
                input_options: " onChange=\"if(this.checked) this.form.action='$action_ssl'; else this.form.action='$action_nossl';\""
            ));
        echo $form->getForm($this->t(
            'Please enter login and password',
            'Bitte Benutzernamen und Passwort eingeben'
        ));
        $this->context->printFooter();
    }
}