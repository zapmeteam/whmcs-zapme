<?php

namespace ZapMeTeam\Whmcs;

use WHMCS\Database\Capsule;
use Symfony\Component\HttpFoundation\Request;

if (!defined('WHMCS')) {
    die('Denied access');
}

class ZapMeTemplateHandle
{
    /** * @var object */
    protected $template;

    /** * @var string|null */
    protected $hook;

    public function __construct(string $hook)
    {
        $this->hook = $hook;
        $hook = strtolower($hook);

        $template = Capsule::table('mod_zapme_templates')->where('code', $hook)->first();
        $template->configurations = unserialize($template->configurations);

        $this->template = $template;
    }

    /**
     * Check template status
     *
     * @return boolean
     */
    public function templateStatus(): bool
    {
        if ($this->template->status === 'disable') {
            logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: Template Desativado');
            return false;
        }

        return true;
    }

    /**
     * Check if template has valid configurations
     *
     * @return boolean
     */
    public function templateHasValidConfigurations(): bool
    {
        return !empty($this->template->configurations) && $this->template->allowconfiguration == true;
    }

    /**
     * Check if template has valid rule configurations
     *
     * @param string $rule
     * 
     * @return boolean
     */
    private function templateHasValidRuleConfiguration(string $rule): bool
    {
        return isset($this->template->configurations[$rule]) && $this->template->configurations[$rule] !== '';
    }

    /**
     * Perform controlByClient rule action
     *
     * @param mixed $client
     * 
     * @return void
     */
    public function controlByClient($client)
    {
        $rule = 'controlByClient';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $clients = explode(',', $this->template->configurations[$rule]);

        foreach ($clients as $key => $value) {
            if ($value !== '' && (int) $client->id == (int) $value) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: O id do cliente (#' . $client->id . ') foi detectado nas regras de controle de envio');
                return false;
            }
        }

        return true;
    }

    /**
     * Perform controlByMinimalValue rule action
     *
     * @param mixed $invoice
     * 
     * @return void
     */
    public function controlByMinimalValue($invoice)
    {
        $rule = 'controlByMinimalValue';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        if ($invoice->total < $this->template->configurations[$rule]) {
            logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: O valor da fatura é inferior ao determinado (' . $this->template->configurations[$rule] . ') nas regras de controle de envio');
            return false;
        }

        return true;
    }

    /**
     * Perform controlByWeekDay rule action
     *
     * @return void
     */
    public function controlByWeekDay()
    {
        $rule = 'controlByWeekDay';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $days = explode(',', $this->template->configurations[$rule]);

        foreach ($days as $key => $value) {
            if ($value !== '' && $value === date('w')) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: O dia do envio foi determinado nas regras de controle de envio');
                return false;
            }
        }

        return true;
    }

    /**
     * Perform controlByGateway rule action
     *
     * @param mixed $invoice
     * 
     * @return void
     */
    public function controlByGateway($invoice)
    {
        $rule = 'controlByGateway';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $gateways = explode(',', $this->template->configurations[$rule]);

        foreach ($gateways as $key => $value) {
            if (!empty($value) && mb_strpos($invoice->paymentmethod, $value) !== false) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: O gateway do pagamento foi determinado nas regras de controle de envio');
                return false;
            }
        }

        return true;
    }

    /**
     * Perform controlByDeppartment rule action
     *
     * @param mixed $vars
     * 
     * @return void
     */
    public function controlByDeppartment($vars)
    {
        $rule = 'controlByDeppartment';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $depparments = explode(',', $this->template->configurations[$rule]);

        foreach ($depparments as $key => $value) {
            if ($value !== '' && (int) $value == (int) $vars['deptid']) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: O departamento do ticket foi determinado nas regras de controle de envio');
                return false;
            }
        }

        return true;
    }

    /**
     * Perform controlByTeamMemberName rule action
     *
     * @param mixed $vars
     * 
     * @return void
     */
    public function controlByTeamMemberName($vars)
    {
        $rule = 'controlByTeamMemberName';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $members = explode(',', $this->template->configurations[$rule]);

        foreach ($members as $key => $value) {
            if (!empty($value) && mb_strpos($vars['admin'], $value) !== false) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: Nome do membro da equipe foi determinado nas regras de controle de envio');
                return false;
            }
        }

        return true;
    }

    /**
     * Perform controlByPartsOfEmail rule action
     *
     * @param mixed $vars
     * 
     * @return void
     */
    public function controlByPartsOfEmail($vars)
    {
        $rule = 'controlByPartsOfEmail';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $members = explode(',', $this->template->configurations[$rule]);

        foreach ($members as $key => $value) {
            if (!empty($value) && mb_strpos($vars['email'], $value) !== false) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: Parte do e-mail do cliente foi determinado nas regras de controle de envio');
                return false;
            }
        }

        return true;
    }

    /**
     * Perform controlByClientStatus rule action
     *
     * @param mixed $client
     * 
     * @return void
     */
    public function controlByClientStatus($client)
    {
        $rule = 'controlByClientStatus';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $status = $this->template->configurations[$rule];

        $translated = [
            'Ativo'   => 'Active',
            'Inativo' => 'Inactive',
            'Fechado' => 'Closed',
        ];

        if ($status !== 'Qualquer' && ($translated[$status] === $client->status)) {
            logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: O status do cliente foi determinado nas regras de controle de envio');
            return false;
        }

        return true;
    }

    /**
     * Perform controlByServerId rule action
     *
     * @param mixed $service
     * 
     * @return void
     */
    public function controlByServerId($service)
    {
        $rule = 'controlByServerId';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $servers = explode(',', $this->template->configurations[$rule]);

        foreach ($servers as $key => $value) {
            if ($value !== '' && (int) $value == (int) $service['id']) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: Id do servidor foi determinado nas regras de controle de envio');
                return false;
            }
        }
    }

    /**
     * Perform controlByProductId rule action
     *
     * @param mixed $product
     * 
     * @return void
     */
    public function controlByProductId($product)
    {
        $rule = 'controlByProductId';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $products = explode(',', $this->template->configurations[$rule]);

        foreach ($products as $key => $value) {
            if ($value !== '' && (int) $value == (int) $product['id']) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: Id do produto foi determinado nas regras de controle de envio');
                return false;
            }
        }
    }

    /**
     * Perform controlByPartsOfProductName rule action
     *
     * @param mixed $product
     * 
     * @return void
     */
    public function controlByPartsOfProductName($product)
    {
        $rule = 'controlByPartsOfProductName';

        if ($this->templateHasValidRuleConfiguration($rule) === false) {
            return true;
        }

        $members = explode(',', $this->template->configurations[$rule]);

        foreach ($members as $key => $value) {
            if (!empty($value) && mb_strpos($product['name'], $value) !== false) {
                logActivity('[ZapMe][' . $this->hook . '] Envio de Mensagem Abortado: Parte do nome do produto foi determinado nas regras de controle de envio');
                return false;
            }
        }
    }

    /**
     * Set default variables into template message
     *
     * @param mixed $client
     * @param mixed $alternativeMessage
     * 
     * @return ZapMeTemplateHandle
     */
    public function defaultVariables($client, $alternativeMessage = null): ZapMeTemplateHandle
    {
        $templateMessage = $this->template->message;

        if (file_exists(ZAPMEMODULE_HOMEPATH . '/language/messages.php')) {
            include ZAPMEMODULE_HOMEPATH . '/language/messages.php';
            $hook = strtolower($this->hook);
            if (isset($language[$hook]) && $client->country !== 'BR') {
                $templateMessage = $language[$hook];
            }
        }

        $message = $alternativeMessage ?? $templateMessage;

        $message = str_replace('%name%', $client->fullname, $message);
        $message = str_replace('%firstname%', $client->firstname, $message);
        $message = str_replace('%lastname%', $client->lastname, $message);
        $message = str_replace('%email%', $client->email, $message);
        $message = str_replace('%company%', $client->companyName, $message);

        $this->template->message = $message;

        return $this;
    }

    /**
     * Set client variables into template message
     *
     * @param mixed $client
     * @param mixed $vars
     * @param string $type
     * 
     * @return ZapMeTemplateHandle
     */
    public function clientVariables($client, $vars, string $type = 'simple'): ZapMeTemplateHandle
    {
        $message = $this->template->message;

        if ($type === 'register') {
            $settings = full_query("SELECT (
                SELECT value FROM tblconfiguration where setting = 'Domain'
           ) as domain,
           (
               SELECT value FROM tblconfiguration where setting = 'SystemURL'
           ) as systemurl,
           (
               SELECT value FROM tblconfiguration where setting = 'CompanyName'
           ) as companyname");

            $result = mysql_fetch_array($settings);

            $message = str_replace('%website%', $result['domain'], $message);
            $message = str_replace('%whmcs%', $result['systemurl'], $message);
            $message = str_replace('%companyname%', $result['companyname'], $message);
        } else {
            $request = Request::createFromGlobals();
            $message = str_replace('%ipaddr%', $request->getClientIp(), $message);
            $message = str_replace('%date%', date('d/m/Y'), $message);
            $message = str_replace('%hour%', date('H:i'), $message);
        }

        $this->template->message = $message;

        return $this;
    }

    /**
     * Set invoice variables into template message
     *
     * @param mixed $invoice
     * 
     * @return ZapMeTemplateHandle
     */
    public function invoicesVariables($invoice): ZapMeTemplateHandle
    {
        $message = $this->template->message;

        $message = str_replace('%invoiceid%', $invoice->id, $message);
        $message = str_replace('%duedate%', date('d/m/Y', strtotime($invoice->duedate)), $message);
        $message = str_replace('%value%', $invoice->total, $message);

        $this->template->message = $message;

        return $this;
    }

    /**
     * Set ticket variables into template message
     *
     * @param mixed $ticket
     * @param mixed $vars
     * 
     * @return ZapMeTemplateHandle
     */
    public function ticketsVariables($ticket, $vars): ZapMeTemplateHandle
    {
        $message = $this->template->message;

        $message = str_replace('%id%', $ticket->id, $message);
        $message = str_replace('%tid%', $ticket->tid, $message);
        $message = str_replace('%title%', $ticket->title, $message);
        $message = str_replace('%date%', date('d/m/Y', strtotime($ticket->lastreply)), $message);
        $message = str_replace('%hour%', date('H:i', strtotime($ticket->lastreply)), $message);
        $message = str_replace('%deptname%', $vars['deptname'], $message);

        $this->template->message = $message;

        return $this;
    }

    /**
     * Set service variables into template message
     *
     * @param mixed $service
     * @param mixed $product
     * 
     * @return ZapMeTemplateHandle
     */
    public function serviceVariables($service, $product): ZapMeTemplateHandle
    {
        $message = $this->template->message;

        $message = str_replace('%id%', $service['id'], $message);
        $message = str_replace('%product%', $product['name'], $message);
        $message = str_replace('%duedate%', date('d/m/Y', strtotime($service['nextduedate'])), $message);
        $message = str_replace('%value%', number_format($service['amount'], 2, ',', '.'), $message);
        $message = str_replace('%ip%', $service['dedicatedip'], $message);
        $message = str_replace('%domain%', $service['domain'], $message);
        $message = str_replace('%user%', $service['username'], $message);
        $message = str_replace('%password%', decrypt($service['password']), $message);

        $this->template->message = $message;

        return $this;
    }

    /**
     * Attach PagHiper Billet into template message
     *
     * @param mixed $invoice
     * @param mixed $client
     * 
     * @return array
     */
    public function attachPagHiperBilletOnHooks($invoice, $client): array
    {
        $document = [];

        if (modulePagHiperExist() === false) {
            $this->removePagHiperBilletDetailsFromMessage();
            logActivity('[ZapMe][' . $this->hook . '] Inclusão de detalhes do Boleto Bancário da PagHiper foram rejeitados: O gateway PagHiper não está configurado como ativado e visível.');
            return $document;
        }

        if (mb_strpos($invoice->paymentmethod, 'paghiper') === false) {
            $this->removePagHiperBilletDetailsFromMessage();
            logActivity('[ZapMe][' . $this->hook . '] Inclusão de detalhes do Boleto Bancário da PagHiper foram rejeitados: A fatura não está com o método de pagamento PagHiper.');
            return $document;
        }

        if ($invoice->total < 3.00) {
            $this->removePagHiperBilletDetailsFromMessage();
            logActivity('[ZapMe][' . $this->hook . '] Inclusão de detalhes do Boleto Bancário da PagHiper foram rejeitados: A fatura é inferior a R$ 3,00');
            return $document;
        }

        $whmcsurl = rtrim(\App::getSystemUrl(), "/");
        $get      = file_get_contents($whmcsurl . '/modules/gateways/paghiper.php?invoiceid=' . $invoice->id . '&uuid=' . $client->id . '&mail=' . $client->email . '&json=1');
        $paghiper = json_decode($get);

        if (isset($paghiper->digitable_line) && isset($paghiper->url_slip_pdf)) {
            $this->template->message = str_replace('%paghiper_barcode%', $paghiper->digitable_line, $this->template->message);

            if (mb_strpos($this->template->message, '%paghiper_boleto%') !== false) {

                $pdf = modulePagHiperExtractPdf($paghiper->url_slip_pdf);
                if ($pdf !== null) {
                    $document = ['document' => $pdf, 'filetype' => 'pdf'];
                }
                $this->template->message = str_replace('%paghiper_boleto%', '', $this->template->message);
            }
        }

        return $document;
    }

    /**
     * Clean PagHiper variables from template message
     *
     * @return void
     */
    private function removePagHiperBilletDetailsFromMessage(): void
    {
        $this->template->message = str_replace('%paghiper_barcode%', '', $this->template->message);
        $this->template->message = str_replace('%paghiper_boleto%', '', $this->template->message);
    }

    /**
     * Get template message
     *
     * @return string
     */
    public function getTemplateMessage(): string
    {
        return $this->template->message;
    }
}
