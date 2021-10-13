<?php

namespace ZapMeTeam\Whmcs;

use WHMCS\User\Client;
use WHMCS\Service\Service;
use WHMCS\Database\Capsule;
use ZapMeTeam\Api\ZapMeApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

if (!defined('WHMCS')) {
    die('Denied access');
}

class ZapMeModule
{
    /** * @var string */
    private $now;

    public function __construct()
    {
        $this->now = date('Y-m-d H:i:s');
    }

    /**
     * Handle incoming request of module
     *
     * @param Request $request
     * 
     * @return void
     */
    public function handleRequest(Request $request)
    {
        $method = $request->getMethod();

        if ($method === 'POST') {
            if ($request->get('internalconfig') === null) {
                return;
            }

            switch ($request->get('action')) {
                case 'configuration':
                    return $this->internalActionEditConfigurations($request->request);
                    break;
                case 'templates':
                    return $this->internalActionEditTemplates($request->request);
                    break;
                case 'editrules':
                    return $this->internalActionEditTemplateRules($request->request);
                    break;
                case 'logs':
                    return $this->internalActionEditLogs($request->request);
                    break;
                case 'manualmessage':
                    return $this->externalActionManualMessage($request->request);
                    break;
            }
        } else {
            if ($request->get('externalaction') === null) {
                return;
            }

            switch ($request->get('externalaction')) {
                case 'invoicereminder':
                    return $this->externalActionInvoiceReminder($request->query);
                    break;
                case 'serviceready':
                    return $this->externalActionServiceReady($request->query);
                    break;
                case 'consultmessage':
                    return $this->internalActionConsultMessageStatus($request->query);
                    break;
            }
        }
    }

    /**
     * Internal action for edit conifgurations
     *
     * @param ParameterBag|null $post
     * 
     * @return void
     */
    private function internalActionEditConfigurations(ParameterBag $post = null)
    {
        $api    = $post->get('api');
        $secret = $post->get('secret');

        $zapMeApi = (new ZapMeApi)
            ->setApi($api)
            ->setSecret($secret)
            ->authApi()
            ->getResult('all', false);

        if (!is_array($zapMeApi) && !isset($zapMeApi['result'])) {
            logActivity('[ZapMe] Erro: ' . $zapMeApi);
            return alert('Ops! <b>Houve algum erro ao validar a sua API.</b> Verifique os logs do sistema e contate o suporte da ZapMe.</b>', 'danger');
        }

        $service = serialize([
            'status'  => $zapMeApi['service'],
            'duedate' => $zapMeApi['duedate'],
            'plan'    => $zapMeApi['planname'],
            'auth'    => $zapMeApi['qrcodeauth'],
        ]);

        Capsule::table('mod_zapme')->truncate();

        Capsule::table('mod_zapme')->insert([
            'api'                  => $post->get('api'),
            'secret'               => $post->get('secret'),
            'status'               => (int) $post->get('status'),
            'logsystem'            => (int) $post->get('logsystem'),
            'logautoremove'        => (int) $post->get('logautoremove'),
            'clientconsentfieldid' => (int) $post->get('clientconsentfieldid'),
            'clientphonefieldid'   => (int) $post->get('clientphonefieldid'),
            'service'              => $service,
            'created_at'           => $this->now,
            'updated_at'           => $this->now
        ]);

        return alert('Tudo certo! <b>Módulo configurado e atualizado com sucesso.</b>');
    }

    /**
     * Internal action for edit templates
     *
     * @param ParameterBag|null $post
     * 
     * @return string
     */
    private function internalActionEditTemplates(ParameterBag $post = null): string
    {
        $templateId = (int) $post->get('messageid');

        if (!Capsule::table('mod_zapme_templates')->where('id', $templateId)->exists()) {
            return alert('<b>Ooops!</b> O template solicitado para edição não existe no banco de dados.', 'danger');
        }

        Capsule::table('mod_zapme_templates')->where('id', $templateId)->update([
            'message'    => $post->get('message'),
            'status'     => (int) $post->get('status'),
            'updated_at' => $this->now
        ]);

        return alert('Tudo certo! <b>Template #' . $templateId . ' editado com sucesso.</b>');
    }

    /**
     * Internal action for edit template rules
     *
     * @param ParameterBag|null $post
     * 
     * @return string
     */
    private function internalActionEditTemplateRules(ParameterBag $post = null): string
    {
        $template = Capsule::table('mod_zapme_templates')->where('id', $post->get('template'))->first();
        $templateDescriptions = templatesConfigurations($template->code);

        if (!isset($templateDescriptions['rules'])) {
            return alert('Ops! <b>O template selecionado <b>(#' . $template->id . ')</b> não possui regras de envio.', 'danger');
        }

        $post->remove('token');
        $post->remove('template');

        $post = $post->all();

        foreach ($templateDescriptions['rules'] as $rule => $informations) {
            if ($informations['field']['type'] === 'text') {
                $post[$informations['id']] = trim($post[$informations['id']], ',');
            }
        }

        Capsule::table('mod_zapme_templates')->where('id', $template->id)->update([
            'configurations' => serialize($post),
            'updated_at'     => $this->now
        ]);

        return alert('Tudo certo! <b>Procedimento efetuado com sucesso.</b>');
    }

    /**
     * Internal action for edit logs
     *
     * @param ParameterBag|null $post
     * 
     * @return string
     */
    private function internalActionEditLogs(ParameterBag $post = null): string
    {
        $clearlogs = $post->get('clearlogs');

        if ($clearlogs === null) {
            return alert('Ops! <b>Você não confirmou o procedimento.</b>', 'danger');
        }

        if ($clearlogs !== null) {
            Capsule::table('mod_zapme_logs')->truncate();
        }

        return alert('Tudo certo! <b>Procedimento efetuado com sucesso.</b>');
    }

    /**
     * External action for invoice reminder (using hook)
     *
     * @param ParameterBag|null $get
     * 
     * @return string
     */
    private function externalActionInvoiceReminder(ParameterBag $get = null): string
    {
        $invoicePaymentReminder = (new ZapMeHooks)->prepare('InvoicePaymentReminder')->InvoicePaymentReminder(['invoiceid' => $get->get('invoiceid')], true);

        if ($invoicePaymentReminder === true) {
            return alert('Tudo certo! <b>Procedimento efetuado com sucesso.</b>');
        } else {
            return alert('Ops! <b>O procedimento não foi realizado!</b> Confira os logs do sistema.', 'danger');
        }
    }

    /**
     * External action for service ready
     *
     * @param ParameterBag|null $get
     * 
     * @return string
     */
    private function externalActionServiceReady(ParameterBag $get = null): string
    {
        $template = new ZapMeTemplateHandle('AfterModuleReady');
        $hooks    = new ZapMeHooks;
        $module   = $hooks->getModuleConfiguration();

        if ($template->templateStatus() === false) {
            return alert('Ops! <b>O procedimento não foi realizado!</b> Confira os logs do sistema.', 'danger');
        }

        $service = Service::find($get->get('serviceid'));
        $client  = $service['client'];
        $product = $service['product'];

        if (clientConsentiment('AfterModuleReady', $client, $module->clientconsentfieldid) === false) {
            return alert('Ops! <b>O procedimento não foi realizado!</b> Confira os logs do sistema.', 'danger');
        }

        $message = $template->defaultVariables($client)->serviceVariables($service, $product)->getTemplateMessage();
        $phone   = clientPhoneNumber($client, $module->clientphonefieldid);

        $response = (new ZapMeApi)
            ->setApi($module->api)
            ->setSecret($module->secret)
            ->sendMessage($phone, $message)
            ->getResult('all', false);

        if (isset($response['result']) && $response['status_result'] === 'message_queued') {
            if ($module->logsystem == true) {
                moduleSaveLog($message, 'aftermoduleready', $client->id, $response['messageid']);
            }
            return alert('Tudo certo! <b>Procedimento efetuado com sucesso.</b>');
        } else {
            logActivity('[ZapMe][AfterModuleReady] Envio de Mensagem: Erro: ' . $response);
            return alert('Ops! <b>O procedimento não foi realizado!</b> Confira os logs do sistema.', 'danger');
        }
    }

    /**
     * External action for manual send message
     *
     * @param ParameterBag|null $post
     * 
     * @return string
     */
    private function externalActionManualMessage(ParameterBag $post = null): string
    {
        $template = new ZapMeTemplateHandle('AfterModuleReady');
        $hooks    = new ZapMeHooks;
        $module   = $hooks->getModuleConfiguration();

        $client = Client::find($post->get('userid'));

        if (clientConsentiment('AfterModuleReady', $client, $module->clientconsentfieldid) === false) {
            return alert('Ops! <b>O procedimento não foi realizado!</b> Confira os logs do sistema.', 'danger');
        }

        $message = $template->defaultVariables($client, $post->get('message'))->getTemplateMessage();
        $phone   = clientPhoneNumber($client, $module->clientphonefieldid);

        $response = (new ZapMeApi)
            ->setApi($module->api)
            ->setSecret($module->secret)
            ->sendMessage($phone, $message)
            ->getResult('all', false);

        if (isset($response['result']) && $response['status_result'] === 'message_queued') {
            if ($module->logsystem == true) {
                moduleSaveLog($message, 'manualmessage', $client->id, $response['messageid']);
            }
            return alert('Tudo certo! <b>Procedimento efetuado com sucesso.</b>');
        } else {
            logActivity('[ZapMe][AfterModuleReady] Envio de Mensagem: Erro: ' . $response);
            return alert('Ops! <b>O procedimento não foi realizado!</b> Confira os logs do sistema.', 'danger');
        }
    }

    /**
     * Internal action for consult message status
     *
     * @param ParameterBag|null $get
     * 
     * @return string
     */
    private function internalActionConsultMessageStatus(ParameterBag $get = null): string
    {
        $messageId = $get->get('messageid');

        if ($messageId === null || empty($messageId)) {
            return alert('Ops! <b>O procedimento não foi realizado!</b> O id da mensagem não foi encontrado ou está vazio.', 'danger');
        }

        $zapMeModule = Capsule::table('mod_zapme')->first();

        $response = (new ZapMeApi)
            ->setApi($zapMeModule->api)
            ->setSecret($zapMeModule->secret)
            ->consultMessage($messageId)
            ->getResult('all', false);

        $status = [
            'queue'                => 'Em Fila',
            'message_sent'         => 'Mensagem Enviada',
            'no_device_connection' => 'Dispositivo Sem Conexão',
            'missing_number'       => 'Número Inexistente',
            'blocked_number'       => 'Número Bloqueado',
            'qrcode_expired'       => 'QRCode Expirado',
        ];

        if (isset($response['result']) && $response['status_result'] === 'consulted_successfully') {
            return "<div class=\"alert alert-success\">
                        <h2 class=\"text-success\"><i class=\"fa fa-check-circle\" aria-hidden=\"true\"></i> <b>Consulta Realizada</b></h2>
                        <ul class=\"list-unstyled\">
                            <li><b>Id:</b> {$messageId}</li>
                            <li><b>Destinatário:</b> {$response['phone']}</li>
                            <li><b>Status:</b> {$status[$response['messagestatus']]}</li>
                            <li><b>Mensagem:</b> {$response['message']}</li>
                            <li><b>Criado:</b> " . date('d/m/Y H:i:s', strtotime($response['created'])) . "</li>
                            <li><b>Atualizado:</b> " . date('d/m/Y H:i:s', strtotime($response['updated'])) . "</li>
                        </ul>
                    </div>";
        } else {
            return "<div class=\"alert alert-danger text-center\"><i class=\"fa fa-exclamation-circle\" aria-hidden=\"true\"></i> Ops! <b>Não foi possível consultar o status da mensagem: {$messageId}.</b> Verifique se a mensagem existe no relatório da sua conta na ZapMe.</div>";
        }
    }
}
