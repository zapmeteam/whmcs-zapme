<?php

/**
 * se o cliente a receber a mensagem estiver com o país configurado diferente de Brasil (BR)
 * o sistema tenterá identificar se o template a ser enviado está configurado neste array.
 * dessa forma você pode criar templates em um segundo idioma (sugestão: inglês) para ser enviado
 * aos clientes que não forem do brasil, conforme o exemplo deixado abaixo como guia:
 */

/**
 * as chaves do array deve ser o nome dos templates:
 * invoicecreated
 * invoicepaymentreminder
 * invoicepaid
 * invoicecancelled
 * invoicefirstoverduealert
 * invoicesecondoverduealert
 * invoicethirdoverduealert
 * ticketopen
 * ticketadminreply
 * aftermodulecreate
 * aftermodulesuspend
 * aftermoduleunsuspend
 * aftermoduleterminate
 * aftermoduleready
 * clientadd
 * clientlogin
 * clientareapagelogin
 * clientchangepassword
 */

$language = [
    /**
     * Invoice Created
     */
    'invoicecreated' => 'Hi %name%,

A new invoice has been created on your account at Customer Center. Below you can check some details of this invoice.

Invoice: #%invoiceid%
Due date: %duedate%
Value: U$ %value%

To perform the invoice payment please go to the Customer Center and navigate on Invoices, after click on the invoice related with id: #%invoiceid%',
];
