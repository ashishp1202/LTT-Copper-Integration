<?php

/**
 * Roots includes
 */
require_once locate_template('/lib/utils.php');           // Utility functions
require_once locate_template('/lib/init.php');            // Initial theme setup and constants
require_once locate_template('/lib/wrapper.php');         // Theme wrapper class
require_once locate_template('/lib/sidebar.php');         // Sidebar class
require_once locate_template('/lib/config.php');          // Configuration
require_once locate_template('/lib/activation.php');      // Theme activation
require_once locate_template('/lib/titles.php');          // Page titles
require_once locate_template('/lib/cleanup.php');         // Cleanup
require_once locate_template('/lib/nav.php');             // Custom nav modifications
require_once locate_template('/lib/gallery.php');         // Custom [gallery] modifications
require_once locate_template('/lib/comments.php');        // Custom comments modifications
require_once locate_template('/lib/relative-urls.php');   // Root relative URLs
require_once locate_template('/lib/widgets.php');         // Sidebars and widgets
require_once locate_template('/lib/scripts.php');         // Scripts and stylesheets
require_once locate_template('/lib/custom.php');          // Custom functions
//ini_set('display_errors', 1);
define('COPPER_API_TOKEN', '184c3091178a82e0dbd04ee9c9c01672');
define('COPPER_API_EMAIL', 'tony@letstalktalent.co.uk');
define('COPPER_API_URL', 'https://api.copper.com/developer_api/v1');
// Load css for specific page template only //
function enqueue_template_specific_css()
{
    if (is_page_template('templates/gravity-form-template.php')) {
        wp_enqueue_style('gravity-form-template', get_template_directory_uri() . '/assets/css/gravity-form-template.css', array(), rand(), 'all');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_template_specific_css');

function letstalktalent_hook_javascript()
{
?>
    <script src="https://forms.copper.com/j/tcy3bXFbfwwKbH1KTd2m6y" type="text/javascript"></script>
<?php
}
add_action('wp_head', 'letstalktalent_hook_javascript');

add_action('gform_after_submission', 'letstalktalent_send_to_copper', 10, 2);

function letstalktalent_send_to_copper($entry, $form)
{
    // Copper API endpoint for creating leads
    $url = COPPER_API_URL . "/leads";

    if ($form['id'] == 6) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['11'];
        $company_name = $entry['10'];
    } elseif ($form['id'] == 9) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['13'];
        $company_name = $entry['10'];
    } elseif ($form['id'] == 33 || $form['id'] == 35 || $form['id'] == 36) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['11'];
        $company_name = $entry['5'];
    } elseif ($form['id'] == 12) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['10'];
        $company_name = $entry['5'];
    } elseif (($form['id'] == 15) || ($form['id'] == 27) || ($form['id'] == 34)) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['10'];
        $company_name = $entry['5'];
    } elseif ($form['id'] == 18) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['12'];
        $company_name = $entry['9'];
    } elseif (($form['id'] == 21) || ($form['id'] == 24) || ($form['id'] == 25) || ($form['id'] == 29)) {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['10'];
        $company_name = $entry['9'];
    } elseif ($form['id'] == 1) {
        $name = $entry['1'] . " " . $entry['2'];
        $email = $entry['6'];
        $company_name = $entry['4'];
    } else {
        $name = $entry['3'] . " " . $entry['4'];
        $email = $entry['9'];
        $company_name = $entry['5'];
    }
    $page_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url();

    $getCopperPersonByEmail = getCopperPersonByEmail($email);

    if (isset($getCopperPersonByEmail['id']) && !empty($getCopperPersonByEmail['id'])) {
        // Log activity for existing customer
        $activity = logActivityInCopper($getCopperPersonByEmail['id'], $page_url);
        /*if (isset($activity['id'])) {
            echo "Activity logged successfully!";
        } else {
            echo "Failed to log activity.";
        } */
    } else {
        $data = [
            'name' => $name,
            'email' => [
                'email' => $email,
                'category' => 'work'
            ],
            'company_name' => $company_name,
            'details' => $page_url,
            'custom_fields' => [
                [
                    'custom_field_definition_id' => '679625',  // Replace with your Copper custom field ID
                    'value' => $page_url
                ]
            ]
        ];
        $body = json_encode($data);
        // API headers for Copper
        $headers = [
            'Content-Type' => 'application/json',
            'X-PW-AccessToken' => COPPER_API_TOKEN,
            'X-PW-Application' => 'developer_api',
            'X-PW-UserEmail' => COPPER_API_EMAIL
        ];

        // Send POST request to Copper
        $response = wp_remote_post($url, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
        ]);
        // Handle the response
        if (is_wp_error($response)) {
            error_log('Copper API error: ' . $response->get_error_message());
        } else {

            $getCopperPersonByEmail = getCopperPersonByEmail($email);
            if (!empty($getCopperPersonByEmail['id'])) {
                $activity = logActivityInCopper($getCopperPersonByEmail['id'], $page_url);
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            if ($status_code != 200) {
                error_log("Copper API error: $status_code - $response_body");
            }
        }
    }
}

function getCopperPersonByEmail($email)
{
    $url = COPPER_API_URL . "/leads/search";
    $data = [
        'emails' => $email,
    ];
    $body = json_encode($data);

    // API headers for Copper
    $headers = [
        'Content-Type' => 'application/json',
        'X-PW-AccessToken' => COPPER_API_TOKEN,
        'X-PW-Application' => 'developer_api',
        'X-PW-UserEmail' => COPPER_API_EMAIL
    ];

    // Send POST request to Copper
    $response = wp_remote_post($url, [
        'method' => 'POST',
        'headers' => $headers,
        'body' => $body,
    ]);

    if (is_wp_error($response)) {
        error_log('Copper API error: ' . $response->get_error_message());
    } else {

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $people = json_decode($response_body, true);
        return $people[0] ?? null;
    }
}
function logActivityInCopper($personId, $pageIdentifier)
{

    $url = COPPER_API_URL . "/activities";
    $data = [
        'parent' =>
        [
            'type' => 'lead',
            'id' => $personId,
        ],
        'type' =>
        [
            'category' => 'user',
            'id' => 0,
        ],
        'details' => "Form submission from page: " . $pageIdentifier
    ];
    $body = json_encode($data);

    // API headers for Copper
    $headers = [
        'Content-Type' => 'application/json',
        'X-PW-AccessToken' => COPPER_API_TOKEN,
        'X-PW-Application' => 'developer_api',
        'X-PW-UserEmail' => COPPER_API_EMAIL
    ];


    // Send POST request to Copper
    $response = wp_remote_post($url, [
        'method' => 'POST',
        'headers' => $headers,
        'body' => $body,
    ]);
    if (is_wp_error($response)) {
        return json_decode($response, true);
    } else {

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $activity = json_decode($response_body, true);
        return $activity ?? null;
    }
}
