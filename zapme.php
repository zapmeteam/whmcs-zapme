<?php

require __DIR__ . '/vendor/autoload.php';

use Exception;
use WHMCS\User\Client;
use WHMCS\Database\Capsule;
use ZapMeTeam\Whmcs\ZapMeModule;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

if (!defined('WHMCS')) {
    die('Denied access');
}

function zapme_config(): array
{
    return [
        'name'        => 'ZapMe',
        'description' => 'Módulo da ZapMe para o sistema WHMCS.',
        'version'     => '2.0',
        'language'    => 'portuguese-br',
        'author'      => 'ZapMe'
    ];
}

function zapme_activate(): array
{
    if (phpversion() < 7.3) {
        return [
            'status' => 'error',
            'description' => 'O módulo não foi ativado: versão do PHP é compatível (desejado: 7.3+)'
        ];
    }

    try {

        $now = date('Y-m-d H:i:s');

        Capsule::schema()->dropIfExists('mod_zapme');
        Capsule::schema()->dropIfExists('mod_zapme_templates');
        Capsule::schema()->dropIfExists('mod_zapme_logs');

        $schema = Capsule::schema();

        $schema->create('mod_zapme', function ($table) {
            $table->increments('id');
            $table->longText('api');
            $table->longText('secret');
            $table->boolean('status')->default(0);
            $table->boolean('logsystem')->default(1);
            $table->boolean('logautoremove')->default(0);
            $table->integer('clientconsentfieldid')->default(0);
            $table->integer('clientphonefieldid')->default(0);
            $table->text('service')->nullable();
            $table->text('created_at');
            $table->text('updated_at');
        });

        $schema->create('mod_zapme_templates', function ($table) {
            $table->increments('id');
            $table->text('code');
            $table->binary('message');
            $table->boolean('allowconfiguration');
            $table->text('configurations')->nullable();
            $table->boolean('status')->default(1);
            $table->text('created_at');
            $table->text('updated_at');
        });

        $schema->create('mod_zapme_logs', function ($table) {
            $table->increments('id');
            $table->text('code');
            $table->integer('messageid');
            $table->integer('clientid');
            $table->binary('message');
            $table->text('created_at');
            $table->text('updated_at');
        });

        $templates = [
            'invoicecreated' => [
                'text' => 'Olá %name%! Informamos que a fatura N° %invoiceid%, com vencimento em %duedate%, tem o valor total de R$ %value%, já encontra-se disponível em sua área do cliente para pagamento. Evite transtornos e efetue o pagamento até a data de vencimento.',
                'configuration' => true
            ],
            'invoicepaymentreminder' => [
                'text' => 'Olá %name%! Informamos que sua fatura N° %invoiceid% está em aberto há alguns dias em sua área do cliente, com o vencimento em %duedate% e o valor total de R$ %value%. Evite transtornos e efetue o pagamento da fatura.',
                'configuration' => true
            ],
            'invoicepaid' => [
                'text' => 'Olá %name%! Informamos que o pagamento de sua fatura N° %invoiceid%, foi confirmado com sucesso, agradecemos a sua confiança.',
                'configuration' => true
            ],
            'invoicecancelled' => [
                'text' => 'Olá %name%! Informamos que a fatura N° %invoiceid%, com vencimento em %duedate% e o valor total de R$ %value% foi cancelada em sua conta. Para mais detalhes entre em contato com o nosso suporte via ticket em sua área do cliente',
                'configuration' => true
            ],
            'invoicefirstoverduealert'  => [
                'text' => 'Olá %name%! Informamos que sua fatura N° %invoiceid% está vencida há 1 dia e esta mensagem serve como um lembrete de primeiro aviso de fatura em atraso. O vencimento da fatura era em %duedate%, com o valor total de R$ %value%. O não pagamento desta fatura poderá acarretar em suspensão dos serviços vinculado a ela.',
                'configuration' => true
            ],
            'invoicesecondoverduealert' => [
                'text' => 'Olá %name%! Informamos que sua fatura N° %invoiceid% está vencida há 2 dias e esta mensagem serve como um lembrete de segundo aviso de fatura em atraso. O vencimento da fatura era em %duedate%, com o valor total de R$ %value%. O não pagamento desta fatura poderá acarretar em suspensão dos serviços vinculado a ela.',
                'configuration' => true
            ],
            'invoicethirdoverduealert' => [
                'text' => 'Olá %name%! Informamos que sua fatura N° %invoiceid% está vencida há 3 dias e esta mensagem serve como um lembrete de terceiro e último aviso de fatura em atraso. O vencimento da fatura era em %duedate%, com o valor total de R$ %value%. Os serviços vinculados a esta fatura poderão ser suspensos e nenhum novo aviso será emitido.',
                'configuration' => true
            ],
            'ticketopen' => [
                'text' => 'Olá %name%! Recebemos o seu ticket de N° %ticket% em %date% as %hour% em nosso sistema. Aguarde, em breve um de nossos atendentes irá lhe atender.',
                'configuration' => true
            ],
            'ticketadminreply' => [
                'text' => 'Olá %name%! O seu ticket N° %ticket% foi respondido por nossa equipe neste exato momento (%date% - %hour%). Visite sua área de cliente para conferir maiores informações.',
                'configuration' => true
            ],
            'aftermodulecreate' => [
                'text' => 'Olá %name%! O seu produto %product% foi ativado! Para sua segurança os dados de acesso foram encaminhados ao seu e-mail neste momento. Caso não encontre entre em contato conosco.',
                'configuration' => true
            ],
            'aftermodulesuspend' => [
                'text' => 'Olá %name%! Informamos que o produto %product% foi suspenso, para mais informações acesse a área do cliente.',
                'configuration' => true
            ],
            'aftermoduleunsuspend' => [
                'text' => 'Olá %name%! Informamos que o produto %product% foi reativado, para mais informações acesse a área do cliente.',
                'configuration' => true
            ],
            'aftermoduleterminate' => [
                'text' => 'Olá %name%! Informamos que o produto %product% foi cancelado, para mais informações acesse a área do cliente.',
                'configuration' => true
            ],
            'aftermoduleready' => [
                'text' => 'Olá %name%! Informamos o serviço %product% encontra-se pronto para uso a partir de agora. Veja alguns detalhes sobre: Domínio: %domain%, IP: %ip%, Valor: R$ %value%, Vencimento: %duedate%. Outros detalhes como: nome de usuário e senha do serviço foram encaminhados ao seu e-mail por uma questão de segurança. Caso não tenha recebido entre em contato com nosso suporte via ticket em sua área do cliente',
                'configuration' => false
            ],
            'clientadd' => [
                'text' => 'Olá %name%! Seja bem-vindo a %companyname%! Agradecemos a preferência em nossa plataforma, ficamos contentes com a sua chegada.',
                'configuration' => true
            ],
            'clientlogin' => [
                'text' => 'Olá %name%! Identificamos um acesso à sua conta originado do endereço de IP %ipaddr%. Caso desconheça este IP recomendamos alterar sua senha imediatamente.',
                'configuration' => true
            ],
            'clientareapagelogin' => [
                'text' => 'Olá %name%! Identificamos uma falha de login originada deste IP %ipaddr% em %date% as %hour% sua conta: %email%. Caso você desconheça este IP ou tenha certeza que não foi você recomendamos alterar sua senha imediatamente.',
                'configuration' => true
            ],
            'clientchangepassword' => [
                'text' => 'Olá %name%! Informamos que a senha da sua conta (%email%) foi alterada neste momento e por isso achamos prudente te notificar. IP: %ipaddr%, Data da alteração: %date%, Hora: %hour%. Se você desconhece essa ação recomendamos recuperar sua senha neste momento e em seguida entre em contato com nosso suporte via ticket em sua área do cliente.',
                'configuration' => true
            ],
        ];

        $connection = Capsule::connection();

        foreach ($templates as $key => $value) {
            $connection->transaction(function ($handler) use ($key, $value, $now) {
                $handler->table('mod_zapme_templates')->insert([
                    'code'               => $key,
                    'message'            => $value['text'],
                    'allowconfiguration' => $value['configuration'],
                    'status'             => 1,
                    'created_at'         => $now,
                    'updated_at'         => $now
                ]);
            });
        }

        return [
            'status' => 'success',
            'description' => 'Módulo ativado com sucesso.'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Erro ao ativar o modulo, debug: ' . $e->getMessage()
        ];
    }
}

function zapme_deactivate(): array
{
    try {

        Capsule::schema()->dropIfExists('mod_zapme');
        Capsule::schema()->dropIfExists('mod_zapme_templates');
        Capsule::schema()->dropIfExists('mod_zapme_logs');

        return [
            'status'      => 'success',
            'description' => 'Módulo desativado com sucesso.'
        ];
    } catch (Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Erro ao desativar o modulo, debug: ' . $e->getMessage()
        ];
    }
}

function zapme_output($vars)
{
    $request = Request::createFromGlobals();
    $zapme   = new ZapMeModule;

    $tab = $request->get('action') ?? $request->get('tab');
    $tab = $tab === 'configuration' || $tab === 'manualmessage' ? null : $tab;

    echo $zapme->handleRequest($request);

    $config = Capsule::table('mod_zapme')->first();
    $config->service = unserialize($config->service);

    if (!isset($config->id) && $tab !== null) {
        echo (new RedirectResponse('addonmodules.php?module=zapme'))->setContent(
            sprintf('<!DOCTYPE html> <html> <head> <meta charset="UTF-8" /> <meta http-equiv="refresh" content="3;url=%1$s" /> <title>Oops!</title> </head> <body> <div class="alert alert-danger text-center"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> O módulo não encontra-se configurado para uso!</div> </body> </html>', htmlspecialchars('addonmodules.php?module=zapme', ENT_QUOTES, 'UTF-8'))
        )->getContent();
        return;
    }

    switch ($tab) {
        case 'templates':
            $templates = Capsule::table('mod_zapme_templates')->get();
            break;
        case 'editrules':
            $templates = Capsule::table('mod_zapme_templates')->where('id', (int) $request->get('template'))->get();
            break;
        case 'logs':
            $logs = Capsule::table('mod_zapme_logs')->orderBy('id', 'desc')->get();
            break;
    }

    if ($tab === null) {
        $customfields = Capsule::table('tblcustomfields')->where('type', 'client')->get();
        $version = file_get_contents('https://docs.zapme.com.br/whmcsmoduleversion.txt');
    }
?>
    <?php if (!isset($config->id)) : ?>
        <div class="alert alert-info text-center"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <b>O módulo não encontra-se configurado para uso!</b> Certifique-se de configurar o módulo para que o mesmo funcione corretamente.</div>
    <?php endif; ?>
    <?php if (isset($config->id) && $config->status == false) : ?>
        <div class="alert alert-danger text-center"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <b>ATENÇÃO!</b> O módulo encontra-se configurado, mas <b>o status está "Desativado".</b> Nenhuma mensagem será enviada até que o status esteja <b>"Ativo".</b></div>
    <?php endif; ?>
    <?php if ($version > $vars['version']) : ?>
        <div class="alert alert-warning text-center">
            Uma nova versão do módulo encontra-se disponível: <b><?= $version ?></b>. Realize o download da nova versão na documentação da ZapMe.
        </div>
    <?php endif; ?>
    <?php if (isset($config->id)) : ?>
        <div class="signin-apps-container">
            <p class="text-center">Status do Serviço <i class="fas fa-question-circle text-danger" aria-hidden="true" data-toggle="tooltip" data-placement="top" data-html="true" title="Estas são as informações atreladas ao seu serviço da ZapMe. Para atualizar essas informações pressione o botão Salvar do módulo"></i></p>
            <div class="row">
                <div class="col-sm-12 col-md-3">
                    <div class="app">
                        <div class="logo-container">
                            <h5>SERVIÇO</h5>
                        </div>
                        <div class="content-container">
                            <div class="description"><label class="label label-<?= $config->service['status'] === 'active' ? 'success' : 'danger' ?>"><span><?= $config->service['status'] === 'active' ? 'ATIVADO' : 'DESATIVADO' ?></span></label></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12 col-md-3">
                    <div class="app">
                        <div class="logo-container">
                            <h5>PLANO</h5>
                        </div>
                        <div class="content-container">
                            <div class="description"><?= $config->service['plan'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12 col-md-3">
                    <div class="app">
                        <div class="logo-container">
                            <h5>VENCIMENTO</h5>
                        </div>
                        <div class="content-container">
                            <div class="description"><?= date('d/m/Y', strtotime($config->service['duedate'])) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12 col-md-3">
                    <div class="app">
                        <div class="logo-container">
                            <h5>AUTENTICAÇÃO</h5>
                        </div>
                        <div class="content-container">
                            <div class="description"><label class="label label-<?= $config->service['auth'] === true ? 'success' : 'danger' ?>"><span><?= $config->service['auth'] === true ? 'DETECTADA' : 'NÃO DETECTADA' ?></span></label></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <ul class="nav nav-tabs admin-tabs" role="tablist">
        <li <?= $tab === null ? 'class="active"' : '' ?>><a class="tab-top" href="addonmodules.php?module=zapme" id="configurations" data-tab-id="1">Configuração</a></li>
        <li <?= $tab === 'templates' || $tab === 'editrules' ? 'class="active"' : '' ?> <?= !isset($config->id) ? 'style="display: none;' : '' ?>><a class="tab-top" href="addonmodules.php?module=zapme&tab=templates" id="templates" data-tab-id="2">Templates</a></li>
        <li <?= $tab === 'logs' ? 'class="active"' : '' ?> <?= !isset($config->id) ? 'style="display: none;' : '' ?>><a class="tab-top" href="addonmodules.php?module=zapme&tab=logs" id="logs" data-tab-id="3">Logs</a></li>
    </ul>
    <div class="tab-content admin-tabs">
        <div class="tab-pane <?= $tab === null ? 'active' : '' ?>" id="configurations">
            <div class="auth-container" style="margin: auto !important; border-top: none;">
                <div class="row">
                    <form action="addonmodules.php?module=zapme&internalconfig=true&action=configuration" method="post">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">Status</label>
                                    <select class="form-control" name="status">
                                        <option value="1" <?= isset($config->status) && $config->status == true ? 'selected' : '' ?>>Ativado</option>
                                        <option value="0" <?= isset($config->status) && $config->status == false ? 'selected' : '' ?>>Desativado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">API</label>
                                    <input type="text" name="api" class="form-control" value="<?= isset($config->api) ? $config->api : '' ?>" placeholder="Insira sua chave de API" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">Chave Secreta</label>
                                    <input type="number" name="secret" class="form-control" value="<?= isset($config->secret) ? $config->secret : '' ?>" placeholder="Insira sua sua Chave Secreta" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">Registros de Logs</label>
                                    <select class="form-control" name="logsystem">
                                        <option value="1" <?= isset($config->logsystem) && $config->logsystem == true ? 'selected' : '' ?>>Sim</option>
                                        <option value="0" <?= isset($config->logsystem) && $config->logsystem == false ? 'selected' : '' ?>>Não</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">Auto. Remoção dos Registros de Logs</label>
                                    <select class="form-control" name="logautoremove">
                                        <option value="1" <?= isset($config->logautoremove) && $config->logautoremove == true ? 'selected' : '' ?>>Sim</option>
                                        <option value="0" <?= isset($config->logautoremove) && $config->logautoremove == false ? 'selected' : '' ?>>Não</option>
                                    </select>
                                    Entenda: <i class="fas fa-question-circle text-danger" aria-hidden="true" data-toggle="tooltip" data-placement="top" data-html="true" title="Apaga os registros de logs do módulo todo dia primeiro de cada mês, através das ações de hooks do WHMCS."></i>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">Seletor de Telefone</label>
                                    <select class="form-control" name="clientphonefieldid">
                                        <option value="0">- Padrão do WHMCS</option>
                                        <?php foreach ($customfields as $customfield) : ?>
                                            <option value="<?= $customfield->id ?>" <?= isset($config->clientphonefieldid) && $config->clientphonefieldid == $customfield->id ? 'selected' : '' ?>>#<?= $customfield->id ?> - Nome: <?= $customfield->fieldname ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    Entenda: <i class="fas fa-question-circle text-danger" aria-hidden="true" data-toggle="tooltip" data-placement="top" data-html="true" title="Permite usar um campo customizado de Telefone diferente do padrão do WHMCS. <b>Este campo necessita que o formato do telefone seja: DDI + DDD + Telefone.</b> Caso não seja identificado o DDI no campo customizado o sistema irá obter o DDI do campo de Telefone padrão do WHMCS."></i>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="inputConfirmPassword">Conscentimento de Mensagens</label>
                                    <select class="form-control" name="clientconsentfieldid">
                                        <option value="0">- Nenhum</option>
                                        <?php foreach ($customfields as $customfield) : ?>
                                            <option value="<?= $customfield->id ?>" <?= isset($config->clientconsentfieldid) && $config->clientconsentfieldid == $customfield->id ? 'selected' : '' ?>>#<?= $customfield->id ?> - Nome: <?= $customfield->fieldname ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    Entenda: <i class="fas fa-question-circle text-danger" aria-hidden="true" data-toggle="tooltip" data-placement="top" data-html="true" title="Campo custommizado de cadastro para viabilizar o conscentimento do cliente para receber ou não as mensagens encaminhadas pelo sistema. Se definido como <b>Nenhum</b> o envio será efetuado normalmente.<br><br>O valor do campo customizado para seleção do cliente deve conter: Sim,Não (com ou sem acento). <b>Caso o cliente selecione Não, então os envios serão abortados.</b>"></i>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success btn-block">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="tab-pane <?= $tab === 'templates' ? 'active' : '' ?>" id="templates">
            <div class="signin-apps-container" style="max-width: none;">
                <div class="row">
                    <?php
                    $modulePagHiper = modulePagHiperExist();
                    foreach ($templates as $template) :
                        $templateConfiguration = templatesConfigurations($template->code); ?>
                        <div class="col-sm-12 col-md-2">
                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#editmodal-<?= $template->id ?>" style="float: right !important; font-size: 10px">EDITAR</button>
                            <div class="app" style="background-color: #<?= $template->status == false ? 'fdeeee;"' : 'f1fff0;' ?>">
                                <div class="logo-container">
                                    <h1><b>(#<?= $template->id ?>)</b> <?= $templateConfiguration['name'] ?></h1>
                                </div>
                                <div class="content-container">
                                    <div class="description"><?= $templateConfiguration['information'] ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="editmodal-<?= $template->id ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">#<?= $template->id ?> - <?= $templateConfiguration['name'] ?></h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form action="addonmodules.php?module=zapme&internalconfig=true&action=templates" method="post">
                                        <input type="hidden" name="messageid" value="<?= $template->id ?>">
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label>Mensagem</label>
                                                <textarea class="form-control" name="message" rows="8" style="resize: none;" required><?= $template->message ?></textarea>
                                            </div>
                                            <hr>
                                            <p>Variáveis Disponíveis</p>
                                            <?= drawTemplatesVariables($template->code, $modulePagHiper) ?>
                                            <?php if (isset($templateConfiguration['paghiper']) && $modulePagHiper === true) : ?>
                                                <div class="alert alert-warning text-center">
                                                    <?= $templateConfiguration['paghiper'] ?>
                                                </div>
                                                <small class="text-danger"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> O boleto bancário do PagHiper só será enviado se o sistema atender as seguintes condições: <b>I.</b> A fatura for superior à R$ 3,00. <b>II.</b> A fatura estiver com o método de pagamento PagHiper. <b>III.</b> O módulo do PagHiper estiver ativado e marcado como visível. <b>Caso contrário, as variáveis %paghiper_barcode% e %paghiper_boleto% serão removidas do envio</b></small>
                                            <?php endif; ?>
                                            <hr>
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select class="form-control" name="status">
                                                    <option value="1" <?= $template->status == true ? 'selected' : '' ?>>Ativado</option>
                                                    <option value="0" <?= $template->status == false ? 'selected' : '' ?>>Desativado</option>
                                                </select>
                                                <small class="text-danger"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> Se desativado, esta mensagem não será enviada</small>
                                            </div>
                                            <?php if ($template->allowconfiguration) : ?>
                                                <a class="btn btn-danger btn-sm" href="addonmodules.php?module=zapme&tab=templates&action=editrules&template=<?= $template->id ?>">REGRAS DE ENVIO</a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">Salvar</button>
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php unset($configuration);
                    endforeach; ?>
                </div>
            </div>
        </div>
        <div class="tab-pane <?= $tab === 'editrules' ? 'active' : '' ?>" id="editrules">
            <a class="btn btn-info btn-sm" href="addonmodules.php?module=zapme&tab=templates">VOLTAR</a>
            <div class="signin-apps-container">
                <?php if ($tab === 'editrules') {
                    $description = templatesConfigurations($template->code);
                    $configuration = unserialize($template->configurations);
                }
                ?>
                <?php if (!isset($description['rules']) || $description['rules'] === null) : ?>
                    <div class="alert alert-danger text-center">O template selecionado <b>(#<?= $template->id ?>)</b> não possui regras disponíveis para edição.</div>
                <?php else : ?>
                    <div class="alert alert-info text-center">
                        Você está editando as regras do template: <b>(#<?= $request->get('template') ?>)</b> <?= $templateConfiguration['name'] ?>
                    </div>
                    <form action="addonmodules.php?module=zapme&internalconfig=true&tab=templates&action=editrules&template=<?= $template->id ?>" method="post">
                        <input type="hidden" name="template" value="<?= $template->id ?>">
                        <?php foreach ($description['rules'] as $rule) : ?>
                            <div class="form-group">
                                <label><?= $rule['label'] ?></label>
                                <<?= drawInputFromTemplatesRules($configuration, $rule); ?>>
                                    <?php if ($rule['field']['type'] === 'select' && isset($rule['field']['options'])) : ?>
                                        <?php foreach ($rule['field']['options'] as $option) : ?>
                                            <option value="<?= $option ?>" <?= isset($configuration[$rule['id']]) && ($option == $configuration[$rule['id']]) ? 'selected' : '' ?>><?= $option ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if ($rule['field']['type'] === 'select') : ?></select><?php endif; ?>
                                    <small><?= $rule['description'] ?></small>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="tab-pane <?= $tab === 'logs' ? 'active' : '' ?>" id="logs">
            <button class="btn btn-danger btn-sm" style="margin: 0px 0px 15px 0px;" data-toggle="modal" data-target="#clearlogs">APAGAR REGISTROS</button>
            <table id="tablelog" class="table table-striped table-responsive" style="width: 100% !important">
                <thead>
                    <tr>
                        <td>#</td>
                        <td>Cliente</td>
                        <td>Mensagem</td>
                        <td>Data do Envio</td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) :
                        $client = Client::find($log->clientid); ?>
                        <tr>
                            <th><?= $log->id ?></th>
                            <th><a href="clientssummary.php?userid=<?= $client->id ?>" target=_blank><?= $client->firstname . ' ' . $client->lastname . ' (#' . $log->clientid . ')' ?></a></th>
                            <th><?= $log->message ?></th>
                            <th><?= date('d/m/Y H:i:s', strtotime($log->created_at)) ?></th>
                            <th> <a href="addonmodules.php?module=zapme&tab=logs&externalaction=consultmessage&messageid=<?= $log->messageid ?>" class="btn btn-info btn-sm"> <i class="fa fa-eye" aria-hidden="true"></i> </a> </th>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
            <div class="modal" id="clearlogs" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Apagar Registros de Logs</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form action="addonmodules.php?module=zapme&internalconfig=true&action=logs" method="post">
                                <p class="justify">Este procedimento irá remover todos os registros de logs do módulo existentes em seu banco de dados. <b>Para prosseguir confirme o procedimento abaixo:</b></p>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input id="my-input" class="form-check-input" type="checkbox" name="clearlogs">
                                        <label class="form-check-label text-danger">Desejo limpar os logs do sistema</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <p class="footer" style="margin-top: 10px !important;"><a href="https://zapme.com.br/" target="_blank"><b>ZapMe</b></a> - Notificações Inteligentes via WhatsApp | Versão: <b><?= $vars['version'] ?></b></p>
    <script>
        $(document).ready(function() {
            $("#tablelog").DataTable({
                "pageLength": 25,
                "order": [
                    [0, "desc"]
                ],
                responsive: true
            });
        });
    </script>
<?php } ?>