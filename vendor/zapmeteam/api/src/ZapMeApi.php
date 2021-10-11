<?php

namespace ZapMeTeam\Api;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ZapMeApi
{
    /** * @var null|string */
    public $api = null;

    /** * @var null|string */
    public $secret = null;

    /** * @var string */
    public $endpoint = 'https://api.zapme.com.br';

    /** * @var array */
    public $data = [];

    /** * @var array */
    public $result = [];

    public function __construct()
    {
        $this->validateRequirements();
    }

    /**
     * setEndpoint
     *
     * @param string $endpoint
     * 
     * @return void
     */
    public function setEndpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * getEndpoint
     *
     * @return null|string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * setApi
     *
     * @param string $api
     * 
     * @return ZapMeApi
     */
    public function setApi(string $api)
    {
        $this->api = $api;

        return $this;
    }

    /**
     * getApi
     *
     * @return null|string
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * setSecret
     *
     * @param mixed $secret
     * 
     * @return ZapMeApi
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * getSecret
     *
     * @return null|string|int
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * setOwner
     *
     * @param array $params
     * 
     * @return ZapMeApi
     */
    public function setOwner(array $params = [])
    {
        $this->api    = $params['api'] ?? null;

        $this->secret = $params['secret'] ?? null;

        return $this;
    }

    /**
     * getOwner
     *
     * @param boolean $debug
     * 
     * @return mixed
     */
    public function getOwner(bool $debug = false)
    {
        if ($debug === true) {
            header('Content-Type: application/json');
        }

        $owner = [
            'api'    => $this->api,
            'secret' => $this->secret,
        ];

        return $debug === true ? var_dump($owner) : $owner;
    }

    /**
     * sendMessage
     *
     * @param string $phone
     * @param string $message
     * @param array $params
     * 
     * @return ZapMeApi
     */
    public function sendMessage(string $phone, string $message = 'Mensagem de Teste', array $params = [])
    {
        $api    = $this->getApi();
        $secret = $this->getSecret();

        $alloweds = [
            'api',
            'secret',
            'method',
            'phone',
            'message',
            'document',
            'filetype',
        ];

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if (!in_array($key, $alloweds)) {
                    unset($params[$key]);
                }
            }
        }

        if (is_string($phone)) {
            $this->data = $params;

            $this->data += [
                'api'     => $api,
                'secret'  => $secret,
                'method'  => 'sendmessage',
                'phone'   => $phone,
                'message' => $message,
            ];

            $this->cURL();
        }

        if (is_array($phone)) {
            foreach ($phone as $number) {
                $this->data = $params;

                $this->data += [
                    'api'     => $api,
                    'secret'  => $secret,
                    'method'  => 'sendmessage',
                    'phone'   => $number,
                    'message' => $message,
                ];

                $this->cURL();
                $this->data = [];
            }
        }

        return $this;
    }

    /**
     * addContact
     *
     * @param string $phone
     * @param string $name
     * @param mixed $group
     * 
     * @return ZapMeApi
     */
    public function addContact(string $phone, string $name = 'Importado', $group = null)
    {
        $this->data = [
            'api'    => $this->getApi(),
            'secret' => $this->getSecret(),
            'method' => 'addcontact',
            'phone'  => $phone,
            'name'   => $name,
        ];

        if ($group !== null) {
            $this->data += [
                'group' => (int) $group
            ];
        }

        $this->cURL();

        return $this;
    }

    /**
     * listMessages
     *
     * @return ZapMeApi
     */
    public function listMessages()
    {
        $this->data = [
            'api'    => $this->getApi(),
            'secret' => $this->getSecret(),
            'method' => 'listmessages',
        ];

        $this->cURL();

        return $this;
    }

    /**
     * consultMessage
     *
     * @param integer $messageid
     * 
     * @return ZapMeApi
     */
    public function consultMessage(int $messageid)
    {
        $this->data = [
            'api'       => $this->getApi(),
            'secret'    => $this->getSecret(),
            'method'    => 'consultmessage',
            'messageid' => (int) $messageid,
        ];

        $this->cURL();

        return $this;
    }

    /**
     * authApi
     *  
     * @return ZapMeApi
     */
    public function authApi()
    {
        $this->data = [
            'api'       => $this->getApi(),
            'secret'    => $this->getSecret(),
            'method'    => 'authapi',
        ];

        $this->cURL();

        return $this;
    }

    /**
     * getResult
     *
     * @param string $type
     * @param boolean $debug
     * 
     * @return mixed
     */
    public function getResult(string $type = 'all')
    {
        return $type === 'all' ? $this->result : $this->result[$type];
    }

    /**
     * validateRequirements
     *
     * @return mixed
     */
    private function validateRequirements()
    {
        if (phpversion() < '5.6') {
            throw new Exception('PHP version doesn\'t match the minimum requirements: 5.6');
        }

        if (!extension_loaded('curl')) {
            throw new Exception('curl extesion was not loaded on your enviroment');
        }

        if (!extension_loaded('json')) {
            throw new Exception('json extesion was not loaded on your enviroment');
        }

        return true;
    }

    /**
     * validateParameters
     *
     * @return mixed
     */
    private function validateParameters()
    {
        if ($this->getApi() === null) {
            throw new Exception('API is undefined');
        }

        if ($this->getSecret() === null) {
            throw new Exception('Secret Key is undefined');
        }

        if (empty($this->getEndpoint())) {
            throw new Exception('EndPoint is undefined or empty');
        }

        if ($this->data === null || empty($this->data)) {
            throw new Exception('Data prepared for submission is empty');
        }
    }

    /**
     * cURL (don't touch it!)
     *
     * @return mixed
     */
    private function cURL()
    {
        $this->validateParameters();

        try {
            $request = (new Client)->post($this->getEndpoint(), [
                'form_params' => $this->data
            ]);

            $response = json_decode($request->getBody(), true);

            $this->result = $response;
        } catch (ClientException $e) {
            $this->result = $e->getResponse()->getBody()->getContents();
        }

        return true;
    }
}
