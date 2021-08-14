<?php

namespace FluentFormEmailOctopus\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class API
{
    protected $apiUrl = 'https://emailoctopus.com/api/1.5/';
    
    //protected $apiKey = '528cc388-36c5-4584-ab2c-4e9bd37ab9e9';
    
    protected $optionKey = '_fluentform_'.FFEMAILOCTOPUS_INT_KEY.'_settings';
    
    protected $apiKey = null;

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public function default_options()
    {
        return array(
            'api_key' => $this->apiKey
        );
    }
    
    public function make_request($action, $data = array(), $method = 'GET')
    {
        /* Build request options string. */
        $request_options = $this->default_options();

        $request_options = wp_parse_args($data, $request_options);

        $options_string = http_build_query($request_options);

        /* Build request URL. */
        $request_url = $this->apiUrl . $action . '?' . $options_string;
        
        /* Execute request based on method. */
        switch ($method) {
            case 'POST':
                $args = array(
                    'body' => json_encode($data),
                    'method' => 'POST',
                    'headers' => [
                        'accept'=> 'application/json',
                        'Content-Type' => 'application/json'
                    ]
                );
                $response = wp_remote_post($request_url, $args);
                break;
            case 'GET':
                $response = wp_remote_get($request_url);
                break;
        }

        /* If WP_Error, die. Otherwise, return decoded JSON. */
        if (is_wp_error($response)) {
            return $response;
        } else {
            return json_decode($response['body'], true);
        }

    }

    /**
     * Test the provided API credentials.
     *
     * @access public
     * @return bool
     */
    public function auth_test()
    {
        return $this->make_request('lists', [], 'GET');
    }
    
 
    public function subscribe($listId, $data)
    {

        $response = $this->make_request('lists/' . $listId . '/contacts', $data, 'POST');
        
        if (!empty($response['error'])) {
            return new \WP_Error('api_error', $response['message']);
        }
        return $response;
    }

    /**
     * Get all Lists in the system.
     *
     * @access public
     * @return array
     */
    public function getLists()
    {
        $response = $this->make_request('lists', [], 'GET');

        if (!empty($response['error'])) {
            return [];
        }

        return $response['data'];
    }

    public function getCustomFields($listId)
    {
        $response = $this->make_request('lists/' . $listId . '/', [], 'GET');
        if (!empty($response['error'])) {
            return [];
        }

        return $response;
    }
    
}
