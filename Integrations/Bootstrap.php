<?php

namespace FluentFormEmailOctopus\Integrations;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;


class Bootstrap extends IntegrationManager
{
    private $key = FFEMAILOCTOPUS_INT_KEY;

    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            ucfirst ($this->key),
            $this->key,
            '_fluentform_'.$this->key.'_settings',
            $this->key.'_feeds',
            105
        );

        $this->logo = FFEMAILOCTOPUS_URL . 'assets/emailoctopus.png';

        $this->description = 'Connect EmailOctopus with WP Fluent Forms and subscribe a contact when a form is submitted.';

        $this->registerAdminHooks();

        //add_filter('fluentform_notifying_async_dropbox', '__return_false');
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo'             => $this->logo,
            'menu_title'       => __('EmailOctopus Integration Settings', 'fluentformpro'),
            'menu_description' => __('Copy the EmailOctopus Access Code from other window and paste it here, then click on Verify Code button.','fluentformpro'),
            'valid_message'    => __('Your EmailOctopus API Key is valid', 'fluentformpro'),
            'invalid_message'  => __('Your EmailOctopus API Key is not valid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'fields'           => [
                'apiKey' => [
                    'type'        => 'text',
                    'placeholder' => 'Access Code',
                    'label_tips'  => __("Enter your EmailOctopus Access Key, Copy the Access Code from other window and paste it here, then click on Verify Code button", 'fluentformpro'),
                    'label'       => __('EmailOctopus Access Code', 'fluentformpro'),
                ],
                'button_link' => [
                    'type'  => 'link',
                    'link_text' => 'Get EmailOctopus Access Code',
                    'link'   => 'https://emailoctopus.com/',
                    'target' => '_blank',
                    'tips'   => 'Please click on this link get get Access Code From EmailOctopus'
                ]
            ],
            'hide_on_valid'    => true,
            'discard_settings' => [
                'section_description' => 'Your EmailOctopus API integration is up and running',
                'button_text'         => 'Disconnect EmailOctopus',
                'data'                => [
                    'apiKey' => ''
                ],
                'show_verify'         => true
            ]
        ];
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'apiKey' => '',
            'status' => ''
        ];

        return wp_parse_args($globalSettings, $defaults);
    }
    
    public function saveGlobalSettings($settings)
    {
        if (!$settings['apiKey']) {
            $integrationSettings = [
                'apiKey' => '',
                'status' => false
            ];
            update_option($this->optionKey, $integrationSettings, 'no');

            wp_send_json_success([
                'message' => __('Your settings has been updated and discarded', 'fluentformpro'),
                'status' => false
            ], 200);
        }

        // Verify API key now
        try {
            $integrationSettings = [
                'apiKey' => sanitize_text_field($settings['apiKey']),
                'status' => false
            ];

            update_option($this->optionKey, $integrationSettings, 'no');

            $api = new API($settings['apiKey']);
            $result = $api->auth_test();

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            if (!empty($result['error']['message'])) {
                throw new \Exception($result['error']['message']);
            }

        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }

        // Integration key is verified now, Proceed now

        $integrationSettings = [
            'apiKey' => sanitize_text_field($settings['apiKey']),
            'status' => true
        ];

        // Update the reCaptcha details with siteKey & secretKey.
        update_option($this->optionKey, $integrationSettings, 'no');

        wp_send_json_success([
            'message' => __('Your EmailOctopus api key has been verified and successfully set', 'fluentformpro'),
            'status' => true
        ], 200);
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url'  => admin_url('admin.php?page=fluent_forms_settings#general-'.$this->key.'-settings'),
            'configure_message' => $this->key.' is not configured yet! Please configure your '.$this->key.' api first',
            'configure_button_text' => 'Set '.$this->key
        ];
        return $integrations;
    }
    
    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'email' => '',
            'firstname' => '',
            'lastname' => '',
            'fields' => (object)[],
            'other_fields_mapping' => [
                [
                    'item_value' => '',
                    'label' => ''
                ]
            ],
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Feed Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'EmailOctopus List',
                    'required' => true,
                    'placeholder' => 'Select EmailOctopus Mailing List',
                    'tips' => 'Select the EmailOctopus Mailing List you would like to add your contacts to.',
                    'component' => 'list_ajax_options',
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'fields',
                    'require_list' => true,
                    'label' => 'Primary Fields',
                    'tips' => 'Select which Fluent Form fields pair with their<br /> respective EmailOctopus fields.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'EmailOctopus Field',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'email',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key' => 'firstname',
                            'label' => 'First Name'
                        ],
                        [
                            'key' => 'lastname',
                            'label' => 'Last Name'
                        ],
                    ]
                ],
               
                [
                    'require_list' => true,
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow EmailOctopus integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => true,
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ]
            ],
            'button_require_list' => true,
            'integration_title' => $this->title
        ];
    }

    public function getMergeFields($list, $listId, $formId)
    {
        $api = $this->getApiInstance();
        $fields = $api->getCustomFields($listId);

        $formattedFields = [];

        foreach ($fields['fields'] as $field) {
            
            $formattedFields[$field['tag']] = $field['label'];
        }

        unset($formattedFields['EmailAddress']);
        unset($formattedFields['FirstName']);
        unset($formattedFields['LastName']);

        return $formattedFields;
    }

    protected function getLists()
    {
        $api = $this->getApiInstance();
        $lists = $api->getLists();
        $formattedLists = [];
        
        foreach ($lists as $list) {
            
            $formattedLists[$list['id']] = $list['name'];
        }
        return $formattedLists;
    }
    

    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];

        //dd($feedData['fields']);

        $listId = ArrayHelper::get($feedData, 'list_id');

        if (!$listId) {
            do_action('ff_integration_action_result', $feed, 'failed', 'EmailOctopus API call has been skipped because no valid List available');
        }

        if (!is_email($feedData['email'])) {
            $feedData['email'] = ArrayHelper::get($formData, $feedData['email']);
        }

        if (!is_email($feedData['email'])) {
            do_action('ff_integration_action_result', $feed, 'failed', 'EmailOctopus API call has been skipped because no valid email available');
            return;
        }

        $subscriber = [
            'email_address' => $feedData['email'],
        ];

        $nameFields = [
            'FirstName' => $feedData['firstname'],
            'LastName' => $feedData['lastname'],
        ];

        $nameFields = array_filter($nameFields);
        if ($nameFields) {
            $subscriber['fields'] = $nameFields;
        }

        $mergeFields = $feedData['fields'];

        if ($mergeFields) {
            $subscriber['fields'] = array_merge($subscriber['fields'], $mergeFields); 
        }

        $subscriber = array_filter($subscriber);

        $subscriber = apply_filters('fluentform_integration_data_' . $this->integrationKey, $subscriber, $feed, $entry);

        $api = $this->getApiInstance();

        $response = $api->subscribe($feedData['list_id'], $subscriber);

        if (is_wp_error($response)) {
            do_action('ff_integration_action_result', $feed, 'failed', $response->get_error_message());
        } else {
            do_action('ff_integration_action_result', $feed, 'success', 'EmailOctopus feed has been successfully initialed and pushed data');
        }

    }

    protected function getApiInstance()
    {
        $settings = $this->getGlobalSettings([]);
        return new API($settings['apiKey']);
    }
    
}
