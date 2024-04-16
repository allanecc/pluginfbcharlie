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
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

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
    $api_url = 'https://graph.facebook.com/v19.0/' . $page_id . '?fields=feed{created_time,attachments,message,from,likes.limit(1).summary(true),comments.limit(1).summary(true)}&access_token=' . $access_token;

    // Realizar la solicitud GET
    $response = wp_remote_get($api_url);

    // Verificar si la solicitud fue exitosa
    if (!is_wp_error($response)) {
        // Decodificar la respuesta JSON
        $page_data = json_decode(wp_remote_retrieve_body($response), true);

        // Verificar si hay datos de feed disponibles
        if(isset($page_data['feed']['data'])) {
            $feed_data = $page_data['feed']['data'];

            // Iterar sobre las publicaciones y mostrarlas
            foreach ($feed_data as $post) {
                echo '<div class="post">';
                
                // Verificar si la publicación tiene un mensaje
                if(isset($post['message'])) {
                    echo '<p class="message">' . $post['message'] . '</p>';
                }
                
                // Verificar si la publicación tiene un adjunto
                if(isset($post['attachments'])) {
                    foreach($post['attachments']['data'] as $attachment) {
                        if($attachment['type'] == 'photo') {
                            echo '<img src="' . $attachment['media']['image']['src'] . '" class="attachment">';
                        } elseif ($attachment['type'] == 'link') {
                            echo '<a href="' . $attachment['url'] . '" class="link">' . $attachment['title'] . '</a>';
                        }
                    }
                }

                // Mostrar el número de likes y comentarios
                echo '<div class="likes-comments">';
                echo '<p class="likes">Likes: ' . $post['likes']['summary']['total_count'] . '</p>';
                echo '<p class="comments">Comentarios: ' . $post['comments']['summary']['total_count'] . '</p>';
                echo '</div>'; // Cierre de likes-comments
                
                // Mostrar la información del autor y la fecha
                echo '<div class="author-info">';
                echo '<p class="author">' . $post['from']['name'] . '</p>';
                
                // Calcular el tiempo transcurrido desde la publicación
                $created_time = new DateTime($post['created_time']);
                $current_time = new DateTime();
                $interval = $current_time->diff($created_time);
                $elapsed = '';

                if ($interval->y > 0) {
                    $elapsed = $interval->format('%y años');
                } elseif ($interval->m > 0) {
                    $elapsed = $interval->format('%m meses');
                } elseif ($interval->d > 0) {
                    $elapsed = $interval->format('%d días');
                } elseif ($interval->h > 0) {
                    $elapsed = $interval->format('%h horas');
                } elseif ($interval->i > 0) {
                    $elapsed = $interval->format('%i minutos');
                } else {
                    $elapsed = $interval->format('%s segundos');
                }

                echo '<p class="created-time">Hace ' . $elapsed . '</p>';
                echo '</div>'; // Cierre de author-info
                
                echo '</div>'; // Cierre de post
            }
        } else {
            // Manejar el caso en que no haya datos de feed disponibles
            echo '<div class="notice notice-warning"><p>No se encontraron publicaciones en el feed de Facebook.</p></div>';
        }
    } else {
        // Manejar errores si la solicitud falla
        echo 'Error al obtener el feed de Facebook.';
    }
}

// Añadir un shortcode para mostrar el feed en las entradas o páginas
add_shortcode('custom_facebook_feed_charlie', 'custom_facebook_feed_charlie');
     