<?php
/*
Plugin Name: Custom Facebook Feed Charlie
Description: Plugin personalizado para mostrar el feed de Facebook en tu sitio de WordPress.
Version: 1.0
Author: CharlieGroup
*/

// Añadir menú de ajustes
// Registrar las opciones de configuración
function my_plugin_register_settings() {
    // Registra una opción para el user-access-token
    register_setting('my_plugin_options', 'my_plugin_user_access_token');

    // Registra una opción para el your-user-id
    register_setting('my_plugin_options', 'my_plugin_your_user_id');

    // Registra una opción para la page_id
    register_setting('my_plugin_options', 'my_plugin_page_id');
    register_setting('my_plugin_options', 'my_plugin_app_id');
}
add_action('admin_init', 'my_plugin_register_settings');

// Agregar una página de configuración
function my_plugin_add_options_page() {
    add_options_page('Configuración de mi Plugin', 'Configuración de mi Plugin', 'manage_options', 'my_plugin_options', 'my_plugin_render_options_page');
}
add_action('admin_menu', 'my_plugin_add_options_page');

// Renderizar la página de configuración
// Renderizar la página de configuración
function my_plugin_render_options_page() {
    ?>
    <div class="wrap">
        <h2>Configuración de mi Plugin</h2>
        <form method="post" action="options.php">
            <?php settings_fields('my_plugin_options'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">User Access Token:</th>
                    <td><input type="text" name="my_plugin_user_access_token" value="<?php echo esc_attr(get_option('my_plugin_user_access_token')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Your User ID:</th>
                    <td><input type="text" name="my_plugin_your_user_id" value="<?php echo esc_attr(get_option('my_plugin_your_user_id')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Page ID:</th>
                    <td><input type="text" name="my_plugin_page_id" value="<?php echo esc_attr(get_option('my_plugin_page_id')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">App ID:</th>
                    <td><input type="text" name="my_plugin_app_id" value="<?php echo esc_attr(get_option('my_plugin_app_id')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Función para agregar scripts al frontend
function agregar_scripts_facebook() {
    // Agregar el SDK de JavaScript de Facebook
    wp_enqueue_script('facebook-sdk', 'https://connect.facebook.net/en_US/sdk.js');

}
add_action('wp_enqueue_scripts', 'agregar_scripts_facebook');
 

// Función de acción para agregar el CSS personalizado
add_action( 'wp_enqueue_scripts', 'agregar_css_personalizado' );

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

// Usar los valores de configuración en la solicitud y retornar el access_token
function my_plugin_make_request() {
    // Obtener los valores de configuración
    $user_access_token = get_option('my_plugin_user_access_token');
    $your_user_id = get_option('my_plugin_your_user_id');

    // Construir la URL para la solicitud
    $url = "https://graph.facebook.com/{$your_user_id}/accounts?access_token={$user_access_token}";

    // Realizar la solicitud
    $response = wp_remote_get($url);

    // Comprobar si la solicitud fue exitosa
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Ocurrió un error: $error_message";
    } else {
        // La solicitud fue exitosa, obtener el cuerpo de la respuesta
        $body = wp_remote_retrieve_body($response);

        // Decodificar el JSON de la respuesta
        $data = json_decode($body, true);

        //debug_to_console('data: ' . $data['access_token']);
        // Verificar si se encontró el access_token en la respuesta
        if (isset($data['data'][0]['access_token'])) {
            // Retornar el access_token
            return $data['data'][0]['access_token'];
        } else {
            return "No se pudo encontrar el access_token en la respuesta.";
        }
    }
}

function custom_facebook_feed_charlie() {
    // Obtener la ID de la página y el token de acceso desde los ajustes
    $page_id = get_option('my_plugin_page_id');
    $access_token = my_plugin_make_request();

    // Comprobar si se han configurado la ID de página y el token de acceso
    if (!$page_id || !$access_token) {
        echo '<div class="notice notice-warning"><p>Por favor, configure la ID de la página y el token de acceso en los ajustes del Custom Facebook Feed.</p></div>';
        return;
    }

    // URL del endpoint de la API de Facebook para obtener el feed
    //$api_url = 'https://graph.facebook.com/v19.0/' . $page_id . '?fields=feed{created_time,attachments,message,from,likes.limit(1).summary(true),comments.limit(1).summary(true)}&access_token=' . $access_token;
    $api_url = 'https://graph.facebook.com/v19.0/' . $page_id . '/feed?fields=permalink_url';
    // Realizar la solicitud GET
    $response = wp_remote_get($api_url);

    // Verificar si la solicitud fue exitosa
    if (!is_wp_error($response)) {
        // Decodificar la respuesta JSON
        $page_data = json_decode(wp_remote_retrieve_body($response), true);

        // Verificar si hay datos disponibles
        if(isset($page_data['data'])) {
            $feed_data = $page_data['data'];

            // Iterar sobre los datos y mostrarlos
            foreach ($feed_data as $post) {
                // Verificar si existe el permalink_url
                if(isset($post['permalink_url'])) {
                    // Construir el enlace del post de Facebook
                    $permalink_url = esc_url($post['permalink_url']);

                    // Generar el elemento HTML del post de Facebook
                    echo '<div class="fb-post" data-href="' . $permalink_url . '" data-width="500"></div>';
                }
            }
        } else {
            // Manejar el caso en que no haya datos disponibles
            echo '<div class="notice notice-warning"><p>No se encontraron publicaciones en el feed de Facebook.</p></div>';
        }
    } else {
        // Manejar errores si la solicitud falla
        echo 'Error al obtener el feed de Facebook.';
    }
}

// Añadir un shortcode para mostrar el feed en las entradas o páginas
add_shortcode('custom_facebook_feed_charlie', 'custom_facebook_feed_charlie');
     