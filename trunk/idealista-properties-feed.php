<?php
/**
 * Plugin Name: Idealista Properties Feed
 * Plugin URI: https://github.com/jjmontalban/idealista-feed
 * Description: Idealista Properties Feed is a plugin that generates and sends a properties feed to Idealista. With this plugin, you can automate the process of sending your properties to Idealista, saving you time and effort.
 * Version: 1.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: JJMontalban
 * Author URI: https://jjmontalban.github.io/
 * Text Domain: idealista-properties-feed
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

 include 'idealista-feed-generator.php';

// Incluir los archivos de traducción
function idealista_load_textdomain() {
    load_plugin_textdomain( 'idealista-properties-feed', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'idealista_load_textdomain' );

// Agregar la página de configuración al menú de administración
function idealista_add_admin_menu() {
    add_menu_page(
        __( 'Idealista Feed', 'idealista-properties-feed' ),
        __( 'Idealista Feed', 'idealista-properties-feed' ),
        'manage_options',
        'idealista-properties-feed',
        'idealista_render_admin_page',
        'dashicons-admin-generic',
        80
    );
}
add_action( 'admin_menu', 'idealista_add_admin_menu' );

// Almacenar los valores del formulario
function idealista_save_form_values() {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'idealista_store_customer_data' ) {
        // Verificar el nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'idealista_store_customer_data' ) ) {
            wp_die( esc_html__( 'Nonce verification failed.', 'idealista-properties-feed' ) );
        }

        // Guardar los valores del formulario en las opciones del plugin
        $form_values = array(
            'code'    => sanitize_text_field( $_POST['code'] ),
            'reference'    => sanitize_text_field( $_POST['reference'] ),
            'name'    => sanitize_text_field( $_POST['name'] ),
            'email'   => sanitize_email( $_POST['email'] ),
            'phone_1' => sanitize_text_field( $_POST['phone_1'] ),
            'phone_2' => sanitize_text_field( $_POST['phone_2'] ),
            'ftp_server' => sanitize_text_field( $_POST['ftp_server'] ),
            'ftp_user' => sanitize_text_field( $_POST['ftp_user'] ),
            'ftp_pass' => sanitize_text_field( $_POST['ftp_pass'] )
        );

        update_option( 'idealista_customer_data', $form_values );

        // Redirigir de vuelta a la página de configuración con un mensaje de éxito
        $redirect_url = add_query_arg( 'feed_status', 'customer_data_saved', admin_url('admin.php?page=idealista-properties-feed' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
add_action( 'admin_post_idealista_store_customer_data', 'idealista_save_form_values' );

// Renderizar la página de administración
function idealista_render_admin_page() {
    // Recuperar los valores del formulario almacenados
    $form_values = get_option( 'idealista_customer_data', array() );

    // Valores por defecto
    $default_values = array(
        'code' => '',
        'reference'  => '',
        'name'   => '',
        'email'  => '',
        'phone_1' => '',
        'phone_2' => '',
        'ftp_server' => '',
        'ftp_user' => '',
        'ftp_pass' => ''
    );

    // Combinar los valores almacenados con los valores por defecto
    $form_values = wp_parse_args( $form_values, $default_values );
    ?>

    <div class="wrap">
        <h1><?php esc_attr__( 'Idealista Feed', 'idealista-properties-feed' ); ?></h1>

        <?php
        if ( isset( $_GET['feed_status'] ) && $_GET['feed_status'] === 'customer_data_saved' ) {
            $message = __( 'Customer data successfully stored', 'idealista-properties-feed' );
            echo '<div id="message" class="updated"><p>' . esc_html( $message ) . '</p></div>';
        }
        elseif ( isset( $_GET['feed_status'] ) && $_GET['feed_status'] === 'success' ) {
            $file_name = 'properties_' . $form_values['code'] . '.json';
            $file_path = plugin_dir_path( __FILE__ ) . $file_name;
            $message = sprintf( __( 'JSON file generated and sended successfully', 'idealista-properties-feed' ), $file_path );
            echo '<div id="message" class="updated"><p>' . esc_html( $message ) . '</p></div>';
        }
        elseif ( isset( $_GET['feed_status'] ) && $_GET['feed_status'] === 'ftp_missing' ) {
            $message = __( 'The JSON file was generated but not sent due to missing FTP settings.', 'idealista-properties-feed' );
            echo '<div id="message" class="error"><p>' . esc_html( $message ) . '</p></div>';
        }
        ?>

        <form method="post" action="<?php echo  esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="idealista_store_customer_data">
            <?php wp_nonce_field( 'idealista_store_customer_data' ); ?>

            <table class="form-table">

                <tr>
                    <th scope="row">
                        <label for="code"><?php echo esc_attr__( 'Code:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="code" id="code" class="regular-text" value="<?php echo esc_attr( $form_values['code'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="reference"><?php echo esc_attr__( 'Reference:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="reference" id="reference" class="regular-text" value="<?php echo esc_attr( $form_values['reference'] ); ?>" required>
                    </td>
                </tr>
                <p><?php echo esc_attr__( 'Idealista customer data.', 'idealista-properties-feed' ); ?></p>
                <tr>
                    <th scope="row">
                        <label for="name"><?php echo esc_attr__( 'Contact Name:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $form_values['name'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email"><?php echo esc_attr__( 'Email:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $form_values['email'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="phone_1"><?php echo esc_attr__( 'Main Phone:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="tel" name="phone_1" id="phone_1" class="regular-text" pattern="[0-9]+" value="<?php echo esc_attr( $form_values['phone_1'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="phone_2"><?php echo esc_attr__( 'Second Phone:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="tel" name="phone_2" id="phone_2" class="regular-text" pattern="[0-9]+" value="<?php echo esc_attr( $form_values['phone_2'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ftp_server"><?php echo esc_attr__( 'FTP Server:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="ftp_server" id="ftp_server" class="regular-text" value="<?php echo esc_attr( $form_values['ftp_server'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ftp_user"><?php echo esc_attr__( 'FTP User:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="ftp_user" id="ftp_user" class="regular-text" value="<?php echo esc_attr( $form_values['ftp_user'] ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ftp_pass"><?php echo esc_attr__( 'FTP Password:', 'idealista-properties-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="ftp_pass" id="ftp_pass" class="regular-text" value="<?php echo esc_attr( $form_values['ftp_pass'] ); ?>" required>
                    </td>
                </tr>

            </table>
        
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Store data', 'idealista-properties-feed' ); ?>">
            </p>
        </form> 

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

            <input type="hidden" name="action" value="idealista_properties_feed_generate">
            <?php wp_nonce_field( 'idealista_properties_feed_generate' ); ?>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Generate file & send', 'idealista-properties-feed' ); ?>">
            </p>
        </form>


    </div>
    <?php
}

// almacenar los datos del cliente
function idealista_store_customer_data() {
    // Verificar el nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'idealista_store_customer_data' ) ) {
        wp_die( esc_html__( 'Nonce verification failed.', 'idealista-properties-feed' ) );
    }

    // Los datos del cliente obtenidos en la página de configuración
    $customer_data = array(
        'code' => sanitize_text_field( $_POST['code'] ),
        'reference' => sanitize_text_field( $_POST['reference'] ),
        'name' => sanitize_text_field( $_POST['name'] ),
        'email' => sanitize_email( $_POST['email'] ),
        'phone_1' => sanitize_text_field( $_POST['phone_1'] ),
        'phone_2' => sanitize_text_field( $_POST['phone_2'] )
    );

    // Almacenar los datos del cliente en la base de datos
    update_option( 'idealista_customer_data', $customer_data );

    // Redirigir de vuelta a la página de configuración con un mensaje de éxito
    $redirect_url = add_query_arg( 'feed_status', 'success', admin_url('admin.php?page=idealista-properties-feed' ) );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_idealista_store_customer_data', 'idealista_store_customer_data' );


?>
