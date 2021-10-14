<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Denied access');
}

define('ZAPMEMODULE_HOMEPATH', __DIR__);
define('ZAPMEMODULE_ACTIVITYLOG', true);

if (!function_exists('modulePagHiperExtractPdf')) {
    /**
     * Generate base64 from PagHiper Billet
     *
     * @param string $link
     * 
     * @return string
     */
    function modulePagHiperExtractPdf(string $link): string
    {
        $billet = base64_encode(file_get_contents($link));

        if ($billet === null || empty($billet)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $link);
            $return = curl_exec($ch);
            curl_close($ch);

            $billet = base64_encode($return);
        }

        return $billet;
    }
}

if (!function_exists('modulePagHiperExist')) {
    /**
     * Check if PagHiper module exist and it's actived
     *
     * @return boolean
     */
    function modulePagHiperExist(): bool
    {
        $modulePagHiper = Capsule::table('tblpaymentgateways')->where('gateway', 'paghiper')->where('setting', 'visible')->first();

        return $modulePagHiper->value === 'on';
    }
}

if (!function_exists('moduleSaveLog')) {
    /**
     * Save module logs
     *
     * @param string $message
     * @param string $code
     * @param integer $clientId
     * @param integer $messageId
     * 
     * @return boolean
     */
    function moduleSaveLog(string $message, string $code, int $clientId, int $messageId): bool
    {
        $now = date('Y-m-d H:i:s');

        Capsule::table('mod_zapme_logs')->insert([
            'code'       => $code,
            'message'    => $message,
            'clientid'   => $clientId,
            'messageid'  => $messageId,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        return true;
    }
}


if (!function_exists('clientConsentiment')) {
    /**
     * Handle client consentiment to receive messages
     *
     * @param string $hook
     * @param mixed $client
     * @param integer $clientConsentFieldId
     * 
     * @return boolean
     */
    function clientConsentiment(string $hook, $client, int $clientConsentFieldId): bool
    {
        if ($clientConsentFieldId == 0) {
            return true;
        }

        if ($client->customFieldValues === null || empty($client->customFieldValues)) {
            return true;
        }

        foreach ($client->customFieldValues as $key => $field) {
            if ((int) $field->fieldid == (int) $clientConsentFieldId) {
                $value = $field->value;
            }
        }

        if ($value === null) {
            return true;
        }

        $value = str_replace(['Ã', 'ã'], 'a', $value);
        $value = strtolower($value);

        if ($value === 'n' || $value === 'nao') {
            if (ZAPMEMODULE_ACTIVITYLOG === true) {
                logActivity('[ZapMe][' . $hook . '] Envio de Mensagem Abortado: O cliente ' . $client->firstname . ' (#' . $client->id . ') desativou o recebimento de alertas através do campo customizado');
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('clientPhoneNumber')) {
    /**
     * Handle client phone number
     * 
     * @param mixed $client
     * @param integer $clientPhoneFieldId
     * 
     * @return string
     */
    function clientPhoneNumber($client, int $clientPhoneFieldId): string
    {
        if ($clientPhoneFieldId == 0) {
            $value = str_replace(['(', ')', ' ', '-', '.', '+'], '', $client->phonenumber);
        } else {
            foreach ($client->customFieldValues as $key => $field) {
                if ((int) $field->fieldid == (int) $clientPhoneFieldId) {
                    $value = $field->value;
                }
            }

            if ($value === null || empty($value)) {
                $value = str_replace(['(', ')', ' ', '-', '.', '+'], '', $client->phonenumber);
            }

            $value = str_replace(['(', ')', ' ', '-', '.', '+'], '', $value);
            $value = trim($value);

            if (strlen($value) == 11) {
                $phone  = explode('.', $client->phonenumber);
                $ddi    = str_replace(['+', ' '], '', $phone[0]);
                $value = $ddi . $value;
            }
        }

        return $value;
    }
}

if (!function_exists('alert')) {
    /**
     * Draw div alerts
     * 
     * @param string $message
     * @param string $type
     * 
     * @return string
     */
    function alert(string $message, string $type = 'success'): string
    {
        $type = $type === 'error' ? 'danger' : $type;
        return '<div class="alert alert-' . $type . ' text-center">' . ($type === 'success' ? '<i class="fa fa-check-circle" aria-hidden="true"></i>' : '<i class="fa fa-exclamation-circle" aria-hidden="true"></i>') . ' ' . $message . '</div>';
    }
}

if (!function_exists('templatesConfigurations')) {
    /**
     * Save all template informations
     * 
     * @param string $code
     * 
     * @return array
     */
    function templatesConfigurations(string $code): array
    {
        $types = [
            'invoicecreated' => [
                'name'        => 'Fatura Criada',
                'information' => 'Modelo de mensagem enviada quando uma fatura é criada pelo WHMCS',
                'paghiper'    => 'Para anexar o boleto bancário do PagHiper neste template escreva: %paghiper_boleto% em qualquer parte da mensagem',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for <b>inferior</b> ao valor definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByGateway',
                        'label'       => 'Controle de Envio por Gateway',
                        'description' => 'Aborta o envio se o gateway para pagamento for um dos determinados <b>(insira o codinome do gateway - verifique no banco de dados, tabela: tblinvoices, coluna: paymentmethod - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ]

                ],
                'variables' => [
                    'invoiceid'        => 'Id da fatura',
                    'duedate'          => 'Vencimento da fatura (d/m/Y)',
                    'value'            => 'Valor total',
                    'paghiper_barcode' => 'Código de Barras do Boleto Bancário da PagHiper',
                ]
            ],
            'invoicepaymentreminder' => [
                'name'        => 'Lembrete de Fatura',
                'information' => 'Modelo de mensagem utilizado no lembrete de fatura em aberto (manualmente ou via hook)',
                'paghiper'    => 'Para anexar o boleto bancário do PagHiper neste template escreva: %paghiper_boleto% em qualquer parte da mensagem',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for <b>inferior</b> ao valor definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByGateway',
                        'label'       => 'Controle de Envio por Gateway',
                        'description' => 'Aborta o envio se o gateway para pagamento for um dos determinados <b>(insira o codinome do gateway - verifique no banco de dados, tabela: tblinvoices, coluna: paymentmethod - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ]

                ],
                'variables' => [
                    'invoiceid'        => 'Id da fatura',
                    'duedate'          => 'Vencimento da fatura (d/m/Y)',
                    'value'            => 'Valor total',
                    'paghiper_barcode' => 'Código de Barras do Boleto Bancário da PagHiper',
                ]
            ],
            'invoicepaid' => [
                'name'        => 'Fatura Paga',
                'information' => 'Modelo de mensagem enviada quando o pagamento de uma fatura é confirmado',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for o <b>mínimo</b> definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByGateway',
                        'label'       => 'Controle de Envio por Gateway',
                        'description' => 'Aborta o envio se o gateway para pagamento for um dos determinados <b>(insira o codinome do gateway - verifique no banco de dados, tabela: tblinvoices, coluna: paymentmethod - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'invoiceid' => 'Id da fatura',
                    'duedate'   => 'Vencimento da fatura (d/m/Y)',
                    'value'     => 'Valor total',
                ]
            ],
            'invoicecancelled' => [
                'name'        => 'Fatura Cancelada',
                'information' => 'Modelo de mensagem enviada quando uma fatura é cancelada',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for o <b>mínimo</b> definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'invoiceid' => 'Id da fatura',
                    'duedate'   => 'Vencimento da fatura (d/m/Y)',
                    'value'     => 'Valor total',
                ]
            ],
            'invoicefirstoverduealert' => [
                'name'        => 'Fatura em Atraso <b>[1]</b>',
                'information' => 'Modelo de mensagem enviada no primeiro aviso de fatura em atraso',
                'paghiper'    => 'Para anexar o boleto bancário do PagHiper neste template escreva: %paghiper_boleto% em qualquer parte da mensagem',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for o <b>mínimo</b> definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'invoiceid'        => 'Id da fatura',
                    'duedate'          => 'Vencimento da fatura (d/m/Y)',
                    'value'            => 'Valor total',
                    'paghiper_barcode' => 'Código de Barras do Boleto Bancário da PagHiper',
                ]
            ],
            'invoicesecondoverduealert' => [
                'name'        => 'Fatura em Atraso <b>[2]</b>',
                'information' => 'Modelo de mensagem enviada no segundo aviso de fatura em atraso',
                'paghiper'    => 'Para anexar o boleto bancário do PagHiper neste template escreva: %paghiper_boleto% em qualquer parte da mensagem',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for o <b>mínimo</b> definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'invoiceid'        => 'Id da fatura',
                    'duedate'          => 'Vencimento da fatura (d/m/Y)',
                    'value'            => 'Valor total',
                    'paghiper_barcode' => 'Código de Barras do Boleto Bancário da PagHiper',
                ]
            ],
            'invoicethirdoverduealert' => [
                'name'        => 'Fatura em Atraso <b>[3]</b>',
                'information' => 'Modelo de mensagem enviada no terceiro aviso de fatura em atraso',
                'paghiper'    => 'Para anexar o boleto bancário do PagHiper neste template escreva: %paghiper_boleto% em qualquer parte da mensagem',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByMinimalValue',
                        'label'       => 'Controle de Envio por Valor Mínimo',
                        'description' => 'Aborta o envio se o valor da fatura for o <b>mínimo</b> definido',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'invoiceid'        => 'Id da fatura',
                    'duedate'          => 'Vencimento da fatura (d/m/Y)',
                    'value'            => 'Valor total',
                    'paghiper_barcode' => 'Código de Barras do Boleto Bancário da PagHiper',
                ]
            ],
            'ticketopen' => [
                'name'        => 'Ticket Criado',
                'information' => 'Modelo de mensagem enviada quando um ticket é aberto pelo cliente',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByDeppartment',
                        'label'       => 'Controle de Envio por Departamento',
                        'description' => 'Aborta o envio se o id do departamento do ticket for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ]
                ],
                'variables' => [
                    'id'        => 'Id do Ticket',
                    'tid'       => 'Tid do Ticket',
                    'title'     => 'Título do Ticket',
                    'date'      => 'Data da Abertura do Ticket',
                    'hour'      => 'Hora da Abertura do Ticket',
                    'deptname'  => 'Departamento do Ticket',
                ]
            ],
            'ticketadminreply' => [
                'name'        => 'Ticket Respondido',
                'information' => 'Modelo de mensagem enviada quando um ticket é respondido pela equipe',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByDeppartment',
                        'label'       => 'Controle de Envio por Departamento',
                        'description' => 'Aborta o envio se o id do departamento do ticket for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByTeamMemberName',
                        'label'       => 'Controle de Envio por Nome do Membro da Equipe',
                        'description' => 'Aborta o envio se o nome do membro da equipe que responder o ticket for um dos determinados <b>(nome ou uma parte do nome - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'id'        => 'Id do Ticket',
                    'tid'       => 'Tid do Ticket',
                    'title'     => 'Título do Ticket',
                    'date'      => 'Data da Resposta ao Ticket',
                    'hour'      => 'Hora da Resposta ao Ticket',
                    'deptname'  => 'Departamento do Ticket',
                ]
            ],
            'aftermodulecreate' => [
                'name'        => 'Serviço Criado',
                'information' => 'Modelo de mensagem enviada quando um serviço é criado',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByServerId',
                        'label'       => 'Controle de Envio por Id do Servidor',
                        'description' => 'Aborta o envio se o id do servidor for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByProductId',
                        'label'       => 'Controle de Envio por Id do Produto',
                        'description' => 'Aborta o envio se o id do produto for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    4 => [
                        'id'          => 'controlByPartsOfProductName',
                        'label'       => 'Controle de Envio por Nome de Produto',
                        'description' => 'Aborta o envio se o nome do produto conter um dos valores determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'product'  => 'Nome do serviço',
                    'id'       => 'Id do serviço',
                    'duedate'  => 'Data de vencimento (d/m/Y)',
                    'value'    => 'Valor total',
                    'ip'       => 'IP do serviço',
                    'domain'   => 'Domínio do serviço',
                    'user'     => 'Usuário do serviço',
                    'password' => 'Senha do serviço',
                ]
            ],
            'aftermodulesuspend' => [
                'name'        => 'Serviço Suspenso',
                'information' => 'Modelo de mensagem enviada quando um serviço é suspenso',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByServerId',
                        'label'       => 'Controle de Envio por Id do Servidor',
                        'description' => 'Aborta o envio se o id do servidor for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByProductId',
                        'label'       => 'Controle de Envio por Id do Produto',
                        'description' => 'Aborta o envio se o id do produto for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    4 => [
                        'id'          => 'controlByPartsOfProductName',
                        'label'       => 'Controle de Envio por Nome de Produto',
                        'description' => 'Aborta o envio se o nome do produto conter um dos valores determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'product'  => 'Nome do serviço',
                    'id'       => 'Id do serviço',
                    'duedate'  => 'Data de vencimento (d/m/Y)',
                    'value'    => 'Valor total',
                    'ip'       => 'IP do serviço',
                    'domain'   => 'Domínio do serviço',
                    'user'     => 'Usuário do serviço',
                    'password' => 'Senha do serviço',
                ]
            ],
            'aftermoduleunsuspend' => [
                'name'        => 'Serviço Reativado',
                'information' => 'Modelo de mensagem enviada quando um serviço é reativado',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByServerId',
                        'label'       => 'Controle de Envio por Id do Servidor',
                        'description' => 'Aborta o envio se o id do servidor for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByProductId',
                        'label'       => 'Controle de Envio por Id do Produto',
                        'description' => 'Aborta o envio se o id do produto for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    4 => [
                        'id'          => 'controlByPartsOfProductName',
                        'label'       => 'Controle de Envio por Nome de Produto',
                        'description' => 'Aborta o envio se o nome do produto conter um dos valores determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'product'  => 'Nome do serviço',
                    'id'       => 'Id do serviço',
                    'duedate'  => 'Data de vencimento (d/m/Y)',
                    'value'    => 'Valor total',
                    'ip'       => 'IP do serviço',
                    'domain'   => 'Domínio do serviço',
                    'user'     => 'Usuário do serviço',
                    'password' => 'Senha do serviço',
                ]
            ],
            'aftermoduleterminate' => [
                'name'        => 'Serviço Cancelado',
                'information' => 'Modelo de mensagem enviada quando um serviço é cancelado',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByServerId',
                        'label'       => 'Controle de Envio por Id do Servidor',
                        'description' => 'Aborta o envio se o id do servidor for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByProductId',
                        'label'       => 'Controle de Envio por Id do Produto',
                        'description' => 'Aborta o envio se o id do produto for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    4 => [
                        'id'          => 'controlByPartsOfProductName',
                        'label'       => 'Controle de Envio por Nome de Produto',
                        'description' => 'Aborta o envio se o nome do produto conter um dos valores determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'product'  => 'Nome do serviço',
                    'id'       => 'Id do serviço',
                    'duedate'  => 'Data de vencimento (d/m/Y)',
                    'value'    => 'Valor total',
                    'ip'       => 'IP do serviço',
                    'domain'   => 'Domínio do serviço',
                    'user'     => 'Usuário do serviço',
                    'password' => 'Senha do serviço',
                ]
            ],
            'aftermoduleready' => [
                'name'        => 'Serviço Pronto',
                'information' => 'Modelo de mensagem utilizado no botão de serviço pronto (uso manual)',
                'variables' => [
                    'product'  => 'Nome do Serviço',
                    'id'       => 'Id do Serviço',
                    'duedate'  => 'Data de Tencimento (d/m/Y)',
                    'value'    => 'Valor Total',
                    'ip'       => 'IP do Serviço',
                    'domain'   => 'Domínio do Serviço',
                    'user'     => 'Usuário do Serviço',
                    'password' => 'Senha do Serviço',
                ]
            ],
            'clientadd' => [
                'name'        => 'Bem-vindo',
                'information' => 'Modelo de mensagem enviada no cadastrado de um cliente',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByPartsOfEmail',
                        'label'       => 'Controle de Envio por Partes do E-mail',
                        'description' => 'Aborta o envio se partes do e-mail do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                ],
                'variables' => [
                    'website'     => 'Link do Site',
                    'whmcs'       => 'Link do WHMCS',
                    'companyname' => 'Nome da Empresa',
                ]
            ],
            'clientlogin' => [
                'name'        => 'Login de Cliente',
                'information' => 'Modelo de mensagem enviada no login de uma conta de cliente',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByPartsOfEmail',
                        'label'       => 'Controle de Envio por Partes do E-mail',
                        'description' => 'Aborta o envio se partes do e-mail do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByClientStatus',
                        'label'       => 'Controle de Envio por Status do Cliente',
                        'description' => 'Aborta o envio se o status do cliente for igual ao selecionado <b>(ou qualquer para qualquer status)</b>',
                        'field'       => [
                            'type' => 'select',
                            'options' => [
                                'Ativo',
                                'Inativo',
                                'Fechado',
                                'Qualquer'
                            ]
                        ]
                    ],
                ],
                'variables' => [
                    'ipaddr' => 'IP de Acesso',
                    'date'   => 'Data do Registro',
                    'hour'   => 'Hora do Registro',
                ]
            ],
            'clientareapagelogin' => [
                'name'        => 'Falha de Login',
                'information' => 'Modelo de mensagem enviada na falha de login de uma conta de cliente',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByPartsOfEmail',
                        'label'       => 'Controle de Envio por Partes do E-mail',
                        'description' => 'Aborta o envio se partes do e-mail do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByClientStatus',
                        'label'       => 'Controle de Envio por Status do Cliente',
                        'description' => 'Aborta o envio se o status do cliente for igual ao selecionado <b>(ou qualquer para qualquer status)</b>',
                        'field'       => [
                            'type' => 'select',
                            'options' => [
                                'Ativo',
                                'Inativo',
                                'Fechado',
                                'Qualquer'
                            ]
                        ]
                    ],
                ],
                'variables' => [
                    'ipaddr' => 'IP de Acesso',
                    'date'   => 'Data do Registro',
                    'hour'   => 'Hora do Registro',
                ]
            ],
            'clientchangepassword' => [
                'name'        => 'Troca de Senha do Cliente',
                'information' => 'Modelo de mensagem enviada na troca de senha da conta de um cliente',
                'rules'       => [
                    0 => [
                        'id'          => 'controlByClient',
                        'label'       => 'Controle de Envio por ID de Cliente',
                        'description' => 'Aborta o envio se o id do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    1 => [
                        'id'          => 'controlByPartsOfEmail',
                        'label'       => 'Controle de Envio por Partes do E-mail',
                        'description' => 'Aborta o envio se partes do e-mail do cliente for um dos determinados <b>(use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    2 => [
                        'id'          => 'controlByWeekDay',
                        'label'       => 'Controle de Envio por Dia da Semana',
                        'description' => 'Aborta o envio se o dia do envio for um dos determinados <b>(dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 - use virgula para mais de um)</b>',
                        'field'       => [
                            'type' => 'text',
                        ]
                    ],
                    3 => [
                        'id'          => 'controlByClientStatus',
                        'label'       => 'Controle de Envio por Status do Cliente',
                        'description' => 'Aborta o envio se o status do cliente for igual ao selecionado <b>(ou qualquer para qualquer status)</b>',
                        'field'       => [
                            'type' => 'select',
                            'options' => [
                                'Ativo',
                                'Inativo',
                                'Fechado',
                                'Qualquer'
                            ]
                        ]
                    ],
                ],
                'variables' => [
                    'ipaddr' => 'IP de Acesso',
                    'date'   => 'Data do Registro',
                    'hour'   => 'Hora do Registro',
                ]
            ],
        ];

        return $types[$code];
    }
}

if (!function_exists('drawTemplatesVariables')) {
    /**
     * Draw template variables in an alert div
     * 
     * @param string $code
     * @param bool $modulePagHiper
     * 
     * @return string
     */
    function drawTemplatesVariables(string $code, bool $modulePagHiper = false): string
    {
        $alert  = '<div class="alert alert-info text-center">';
        $alert .= '%name% - Nome do cliente (completo)<br>';
        $alert .= '%firstname% - Primeiro nome do cliente<br>';
        $alert .= '%lastname% - Último nome do cliente<br>';
        $alert .= '%email% - E-mail do cliente<br>';
        $alert .= '%company% - Compania do Cliente<br>';
        $alert .= '<hr>';

        $variables = templatesConfigurations($code)['variables'];

        foreach ($variables as $key => $value) {
            if ($modulePagHiper === false && mb_strpos($key, 'paghiper') !== false) {
                continue;
            }
            $alert .= '%' . $key . '% - ' . $value . '<br>';
        }

        $alert .= '</div>';

        return $alert;
    }
}

if (!function_exists('drawInputFromTemplatesRules')) {
    /**
     * Draw inputs from templates rules in a string
     * 
     * @param mixed $configuration
     * @param array $rules
     * 
     * @return string
     */
    function drawInputFromTemplatesRules($configuration, array $rule): string
    {
        $input = '';

        $input .= $rule['field']['type'] !== 'select' ? 'input type="' . $rule['field']['type'] . '"' : 'select';
        $input .= ' class="form-control" name=' . $rule['id'] . ' id=' . $rule['id'] . '';
        $input .= isset($rule['field']['required']) ? ' required' : '';
        $input .= isset($configuration[$rule['id']]) && $rule['field']['type'] !== 'select' ? ' value="' . $configuration[$rule['id']] . '"' : '';
        $input .= isset($rule['field']['maxlength']) && $rule['field']['type'] !== 'select' ? ' maxlength="' . $rule['field']['maxlength'] . '"' : '';
        $input .= isset($rule['field']['minlength']) && $rule['field']['type'] !== 'select' ? ' minlength="' . $rule['field']['minlength'] . '"' : '';
        $input .= isset($rule['field']['min']) && $rule['field']['type'] !== 'select' ? ' min="' . $rule['field']['min'] . '"' : '';
        $input .= isset($rule['field']['max']) && $rule['field']['type'] !== 'select' ? ' max="' . $rule['field']['max'] . '"' : '';
        $input .= isset($rule['field']['placeholder']) && $rule['field']['type'] !== 'select' ? ' placeholder="' . $rule['field']['placeholder'] . '"' : '';

        return $input;
    }
}
