<?php
return [
    'backend' => [
        'frontName' => 'admin'
    ],
    'crypt' => [
        'key' => 'ed2290c9759f535be529ee5c37e7578b'
    ],
    'db' => [
        'table_prefix' => '',
        'connection' => [
            'default' => [
                'host' => 'localhost',
                'dbname' => 'u434561653_senisstores',
                'username' => 'u434561653_senisstores',
                'password' => 'haerriz97Q@',
                'active' => '1',
                'driver_options' => [

                ]
            ]
        ]
    ],
    'resource' => [
        'default_setup' => [
            'connection' => 'default'
        ]
    ],
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'production',
    'session' => [
        'save' => 'files'
    ],
    'cache' => [
        'frontend' => [
            'default' => [
                'id_prefix' => '68d_'
            ],
            'page_cache' => [
                'id_prefix' => '68d_'
            ]
        ],
        'graphql' => [
            'id_salt' => 'N8ym0RzDvvL92QHbKNUzShV1UIaUmBYL'
        ]
    ],
    'lock' => [
        'provider' => 'db',
        'config' => [
            'prefix' => null
        ]
    ],
    'cache_types' => [
        'config' => 1,
        'layout' => 1,
        'block_html' => 1,
        'collections' => 1,
        'reflection' => 1,
        'db_ddl' => 1,
        'compiled_config' => 1,
        'eav' => 1,
        'customer_notification' => 1,
        'config_integration' => 1,
        'config_integration_api' => 1,
        'full_page' => 1,
        'config_webservice' => 1,
        'translate' => 1,
        'vertex' => 1
    ],
    'downloadable_domains' => [
        'localhost'
    ],
    'install' => [
        'date' => 'Mon, 18 Jul 2022 11:43:31 +0000'
    ],
    'system' => [
        'default' => [
            'payment' => [
                'payflowpro' => [
                    'partner' => null,
                    'user' => null,
                    'pwd' => null,
                    'sandbox_flag' => null,
                    'proxy_host' => null,
                    'proxy_port' => null,
                    'debug' => null
                ],
                'payflow_link' => [
                    'pwd' => null,
                    'sandbox_flag' => null,
                    'use_proxy' => null,
                    'proxy_host' => null,
                    'proxy_port' => null,
                    'debug' => null,
                    'url_method' => 'GET'
                ],
                'payflow_express' => [
                    'debug' => null
                ],
                'paypal_express_bml' => [
                    'publisher_id' => null
                ],
                'paypal_express' => [
                    'debug' => '0',
                    'merchant_id' => null
                ],
                'hosted_pro' => [
                    'debug' => null
                ],
                'paypal_billing_agreement' => [
                    'debug' => '0'
                ],
                'braintree' => [
                    'merchant_id' => null,
                    'public_key' => null,
                    'private_key' => null,
                    'merchant_account_id' => null
                ],
                'braintree_paypal' => [
                    'merchant_name_override' => null
                ],
                'checkmo' => [
                    'mailing_address' => null
                ],
                'payflow_advanced' => [
                    'user' => null,
                    'pwd' => null,
                    'sandbox_flag' => null,
                    'proxy_host' => null,
                    'proxy_port' => null,
                    'debug' => null,
                    'url_method' => 'GET'
                ]
            ],
            'payment_all_paypal' => [
                'paypal_payflowpro' => [
                    'settings_paypal_payflow' => [
                        'heading_cc' => null,
                        'settings_paypal_payflow_advanced' => [
                            'paypal_payflow_settlement_report' => [
                                'heading_sftp' => null
                            ]
                        ]
                    ]
                ],
                'payflow_link' => [
                    'settings_payflow_link' => [
                        'settings_payflow_link_advanced' => [
                            'payflow_link_settlement_report' => [
                                'heading_sftp' => null
                            ]
                        ]
                    ]
                ],
                'payments_pro_hosted_solution' => [
                    'pphs_settings' => [
                        'pphs_settings_advanced' => [
                            'pphs_settlement_report' => [
                                'heading_sftp' => null
                            ]
                        ]
                    ]
                ],
                'express_checkout' => [
                    'settings_ec' => [
                        'settings_ec_advanced' => [
                            'express_checkout_settlement_report' => [
                                'heading_sftp' => null
                            ]
                        ]
                    ]
                ]
            ],
            'paypal' => [
                'fetch_reports' => [
                    'ftp_login' => null,
                    'ftp_password' => null,
                    'ftp_sandbox' => '0',
                    'ftp_ip' => null,
                    'ftp_path' => null
                ],
                'general' => [
                    'business_account' => null,
                    'merchant_country' => null
                ],
                'wpp' => [
                    'api_username' => null,
                    'api_password' => null,
                    'api_signature' => null,
                    'api_cert' => null,
                    'sandbox_flag' => '0',
                    'proxy_host' => null,
                    'proxy_port' => null
                ]
            ],
            'admin' => [
                'url' => [
                    'custom' => null,
                    'custom_path' => null
                ]
            ],
            'web' => [
                'unsecure' => [
                    'base_url' => 'https://senisstores.com/',
                    'base_link_url' => '{{unsecure_base_url}}',
                    'base_static_url' => null,
                    'base_media_url' => null
                ],
                'secure' => [
                    'base_url' => 'https://senisstores.com/',
                    'base_link_url' => '{{secure_base_url}}',
                    'base_static_url' => null,
                    'base_media_url' => null
                ],
                'default' => [
                    'front' => 'cms'
                ],
                'cookie' => [
                    'cookie_path' => null,
                    'cookie_domain' => null
                ]
            ],
            'catalog' => [
                'productalert_cron' => [
                    'error_email' => null
                ],
                'product_video' => [
                    'youtube_api_key' => null
                ],
                'search' => [
                    'opensearch_server_hostname' => 'https://honest-fir-1qvffda1.us-east-1.bonsaisearch.net',
                    'elasticsearch7_server_hostname' => 'https://honest-fir-1qvffda1.us-east-1.bonsaisearch.net',
                    'opensearch_server_port' => '443',
                    'elasticsearch7_server_port' => '443',
                    'opensearch_index_prefix' => 'magento2',
                    'elasticsearch7_index_prefix' => 'magento2',
                    'opensearch_enable_auth' => '1',
                    'elasticsearch7_enable_auth' => '1',
                    'opensearch_username' => '6f1e6dd6e0',
                    'elasticsearch7_username' => '6f1e6dd6e0',
                    'opensearch_password' => 'fcd5dcc20fe07f1bbc58',
                    'elasticsearch7_password' => 'fcd5dcc20fe07f1bbc58',
                    'opensearch_server_timeout' => '15',
                    'elasticsearch7_server_timeout' => '15'
                ],
                'seo' => [
                    'category_canonical_tag' => '1',
                    'product_canonical_tag' => '1'
                ]
            ],
            'cataloginventory' => [
                'source_selection_distance_based_google' => [
                    'api_key' => null
                ],
                'options' => [
                    'show_out_of_stock' => '1'
                ]
            ],
            'currency' => [
                'import' => [
                    'error_email' => null
                ]
            ],
            'sitemap' => [
                'generate' => [
                    'error_email' => null
                ]
            ],
            'trans_email' => [
                'ident_general' => [
                    'name' => 'Senis Stores',
                    'email' => 'admin@senisstores.com'
                ],
                'ident_sales' => [
                    'name' => 'Senis Stores Orders',
                    'email' => 'orders@senisstores.com'
                ],
                'ident_support' => [
                    'name' => 'Seni S Stores',
                    'email' => 'admin@senisstores.com'
                ],
                'ident_custom1' => [
                    'name' => 'Senis Stores Newsletter',
                    'email' => 'newsletter@senisstores.com'
                ],
                'ident_custom2' => [
                    'name' => 'Seni S Stores',
                    'email' => 'admin@senisstores.com'
                ]
            ],
            'contact' => [
                'email' => [
                    'recipient_email' => 'haerriz@gmail.com'
                ]
            ],
            'sales_email' => [
                'order' => [
                    'copy_to' => 'ravikkumar71@gmail.com,haerriz@gmail.com'
                ],
                'order_comment' => [
                    'copy_to' => null
                ],
                'invoice' => [
                    'copy_to' => null
                ],
                'invoice_comment' => [
                    'copy_to' => null
                ],
                'shipment' => [
                    'copy_to' => null
                ],
                'shipment_comment' => [
                    'copy_to' => null
                ],
                'creditmemo' => [
                    'copy_to' => null
                ],
                'creditmemo_comment' => [
                    'copy_to' => null
                ]
            ],
            'checkout' => [
                'payment_failed' => [
                    'copy_to' => null
                ]
            ],
            'carriers' => [
                'ups' => [
                    'is_account_live' => '0',
                    'access_license_number' => null,
                    'gateway_xml_url' => 'https://onlinetools.ups.com/ups.app/xml/Rate',
                    'gateway_rest_url' => 'https://wwwcie.ups.com/api/rating/',
                    'password' => null,
                    'username' => null,
                    'gateway_url' => 'https://www.ups.com/using/services/rave/qcostcgi.cgi',
                    'shipper_number' => null,
                    'tracking_url' => 'https://onlinetools.ups.com/ups.app/xml/Track',
                    'tracking_rest_url' => 'https://wwwcie.ups.com/api/track/',
                    'debug' => '0'
                ],
                'usps' => [
                    'gateway_url' => 'http://production.shippingapis.com/ShippingAPI.dll',
                    'gateway_secure_url' => 'https://secure.shippingapis.com/ShippingAPI.dll',
                    'userid' => null,
                    'password' => null
                ],
                'fedex' => [
                    'account' => null,
                    'api_key' => null,
                    'secret_key' => null,
                    'sandbox_mode' => '0',
                    'production_webservices_url' => 'https://ws.fedex.com:443/web-services/',
                    'sandbox_webservices_url' => 'https://wsbeta.fedex.com:443/web-services/',
                    'smartpost_hubid' => null
                ],
                'dhl' => [
                    'id' => null,
                    'password' => null,
                    'account' => null,
                    'debug' => '0',
                    'gateway_url' => 'https://xmlpi-ea.dhl.com/XMLShippingServlet'
                ]
            ],
            'google' => [
                'analytics' => [
                    'account' => null
                ],
                'gtag' => [
                    'analytics4' => [
                        'measurement_id' => null
                    ],
                    'adwords' => [
                        'conversion_id' => null
                    ]
                ]
            ],
            'recaptcha_backend' => [
                'type_recaptcha' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_invisible' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_recaptcha_v3' => [
                    'public_key' => null,
                    'private_key' => null
                ]
            ],
            'recaptcha_frontend' => [
                'type_recaptcha' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_invisible' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_recaptcha_v3' => [
                    'public_key' => null,
                    'private_key' => null
                ]
            ],
            'system' => [
                'smtp' => [
                    'host' => 'smtp.hostinger.com',
                    'port' => '465',
                    'transport' => 'smtp',
                    'auth' => 'login',
                    'username' => 'admin@senisstores.com',
                    'ssl' => 'ssl',
                    'password' => '0:3:uXxoEgMQS0LjRPE+makGgsIG9rOoJcn0uTMlHzB6A5Y5nLQ/od6nMpo='
                ],
                'full_page_cache' => [
                    'varnish' => [
                        'access_list' => null,
                        'backend_host' => null,
                        'backend_port' => null
                    ]
                ],
                'release_notification' => [
                    'content_url' => null,
                    'use_https' => '1'
                ]
            ],
            'adobe_ims' => [
                'integration' => [
                    'api_key' => null,
                    'private_key' => null
                ]
            ],
            'dev' => [
                'restrict' => [
                    'allow_ips' => null
                ],
                'js' => [
                    'session_storage_key' => 'collected_errors'
                ]
            ],
            'newrelicreporting' => [
                'general' => [
                    'api_url' => 'https://api.newrelic.com/deployments.xml',
                    'insights_api_url' => 'https://insights-collector.newrelic.com/v1/accounts/%s/events',
                    'account_id' => null,
                    'app_id' => null,
                    'api' => null,
                    'insights_insert_key' => null
                ]
            ],
            'analytics' => [
                'general' => [
                    'token' => null
                ],
                'url' => [
                    'signup' => 'https://advancedreporting.rjmetrics.com/signup',
                    'update' => 'https://advancedreporting.rjmetrics.com/update',
                    'bi_essentials' => 'https://dashboard.rjmetrics.com/v2/magento/signup',
                    'otp' => 'https://advancedreporting.rjmetrics.com/otp',
                    'report' => 'https://advancedreporting.rjmetrics.com/report',
                    'notify_data_changed' => 'https://advancedreporting.rjmetrics.com/report'
                ]
            ],
            'crontab' => [
                'default' => [
                    'jobs' => [
                        'analytics_subscribe' => [
                            'schedule' => [
                                'cron_expr' => '0 * * * *'
                            ]
                        ],
                        'analytics_collect_data' => [
                            'schedule' => [
                                'cron_expr' => '00 02 * * *'
                            ]
                        ]
                    ]
                ]
            ],
            'general' => [
                'single_store_mode' => [
                    'enabled' => '0'
                ]
            ]
        ]
    ],
    'smtp_mapping' => [
        'orders@senisstores.com' => 'Whatsapp@2027',
        'newsletter@senisstores.com' => 'Whatsapp@2027',
        'newsletter1@senisstores.com' => 'Whatsapp@2027'
    ]
];
