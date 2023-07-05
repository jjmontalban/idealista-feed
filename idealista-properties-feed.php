<?php
/**
 * Plugin Name: Idealista Properties Feed
 * Plugin URI: https://jjmontalban.github.io/
 * Description: Generates and sends a properties feed to Idealista.
 * Version: 1.0.0
 * Author: JJMontalban
 * Author URI: https://jjmontalban.github.io/
 * Text Domain: idealista-properties-feed
 * Domain Path: /languages
 */

// Incluir los archivos de traducción
add_action( 'plugins_loaded', 'idealista_properties_feed_load_textdomain' );
function idealista_properties_feed_load_textdomain() {
    load_plugin_textdomain( 'idealista-properties-feed', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}

// Agregar la página de configuración al menú de administración
add_action( 'admin_menu', 'idealista_properties_feed_add_admin_menu' );
function idealista_properties_feed_add_admin_menu() {
    add_menu_page(
        __( 'Idealista Feed', 'idealista-properties-feed' ),
        __( 'Idealista Feed', 'idealista-properties-feed' ),
        'manage_options',
        'idealista-properties-feed',
        'idealista_properties_feed_render_admin_page',
        'dashicons-admin-generic',
        80
    );
}

// Almacenar los valores del formulario
function idealista_properties_feed_save_form_values() {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'idealista_properties_feed_generate_and_send' ) {
        // Verificar el nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'idealista_properties_feed_generate_and_send' ) ) {
            wp_die( __( 'Nonce verification failed.', 'idealista-properties-feed' ) );
        }

        // Guardar los valores del formulario en las opciones del plugin
        $form_values = array(
            'code'    => sanitize_text_field( $_POST['code'] ),
            'reference'    => sanitize_text_field( $_POST['reference'] ),
            'name'    => sanitize_text_field( $_POST['name'] ),
            'email'   => sanitize_email( $_POST['email'] ),
            'phone_1' => sanitize_text_field( $_POST['phone_1'] ),
            'phone_2' => sanitize_text_field( $_POST['phone_2'] ),
        );

        update_option( 'idealista_properties_feed_form_values', $form_values );
    }
}
add_action( 'admin_post_idealista_properties_feed_generate_and_send', 'idealista_properties_feed_save_form_values' );


// Renderizar la página de administración
function idealista_properties_feed_render_admin_page() {

    // Recuperar los valores del formulario almacenados
    $form_values = get_option( 'idealista_properties_feed_form_values', array() );

    // Valores por defecto
    $default_values = array(
        'code' => '',
        'reference'  => '',
        'name'   => '',
        'email'  => '',
        'phone_1' => '',
        'phone_2' => '',
    );

    // Combinar los valores almacenados con los valores por defecto
    $form_values = wp_parse_args( $form_values, $default_values );
    ?>

    <div class="wrap">
        <h1><?php _e( 'Idealista Feed', 'idealista-properties-feed' ); ?></h1>
        <?php
        if ( isset( $_GET['feed_status'] ) && $_GET['feed_status'] === 'success' ) {
            $file_path = 'plugins/properties-feed/properties.json'; //plugin_dir_path( __FILE__ ) . 'properties.json';
            $message = sprintf( __( 'Archivo JSON generado con éxito y almacenado en: %s', 'idealista-properties-feed' ), $file_path );
            echo '<div id="message" class="updated"><p>' . esc_html( $message ) . '</p></div>';
        }
        ?>

<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
    <input type="hidden" name="action" value="idealista_properties_feed_generate_and_send">
    <?php wp_nonce_field( 'idealista_properties_feed_generate_and_send' ); ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="code"><?php _e( 'Código:', 'idealista-properties-feed' ); ?></label>
            </th>
            <td>
                <input type="text" name="code" id="code" class="regular-text" value="<?php echo esc_attr( $form_values['code'] ); ?>" required>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="reference"><?php _e( 'Referencia:', 'idealista-properties-feed' ); ?></label>
            </th>
            <td>
                <input type="text" name="reference" id="reference" class="regular-text" value="<?php echo esc_attr( $form_values['reference'] ); ?>" required>
            </td>
        </tr>
        <p><?php _e( 'Datos de cliente Idealista.', 'idealista-properties-feed' ); ?></p>
        <tr>
            <th scope="row">
                <label for="name"><?php _e( 'Nombre contacto:', 'idealista-properties-feed' ); ?></label>
            </th>
            <td>
                <input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $form_values['name'] ); ?>" required>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="email"><?php _e( 'Email:', 'idealista-properties-feed' ); ?></label>
            </th>
            <td>
                <input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $form_values['email'] ); ?>" required>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="phone_1"><?php _e( 'Tlfno. Principal:', 'idealista-properties-feed' ); ?></label>
            </th>
            <td>
                <input type="tel" name="phone_1" id="phone_1" class="regular-text" pattern="[0-9]+" value="<?php echo esc_attr( $form_values['phone_1'] ); ?>" required>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="phone_2"><?php _e( 'Tlfno. Secundario:', 'idealista-properties-feed' ); ?></label>
            </th>
            <td>
                <input type="tel" name="phone_2" id="phone_2" class="regular-text" pattern="[0-9]+" value="<?php echo esc_attr( $form_values['phone_2'] ); ?>" required>
            </td>
        </tr>
    </table>
    
            <p><?php _e( 'Haga clic en el botón para generar y enviar el feed de propiedades a Idealista.', 'idealista-properties-feed' ); ?></p>
            <?php submit_button( __( 'Enviar', 'idealista-properties-feed' ), 'primary', 'submit', false ); ?>
        </form> 

    </div>
    <?php
}

// Funcion auxiliar para dividir la direccion
function splitAddress($address) {
    $parts = explode(', ', $address); // Dividir la dirección por la coma y el espacio
    
    // Obtener el nombre de la calle
    $streetParts = explode(' ', $parts[0]);
    
    $addressStreetNumber = null;
    if (count($streetParts) > 1 && is_numeric(end($streetParts))) {
        $addressStreetNumber = end($streetParts);
        $addressStreetName = implode(' ', array_slice($streetParts, 0, -1));
    } else {
        $addressStreetName = $parts[0];
    }
    
    // Obtener el código postal y la ciudad
    $addressPostalCodeTownParts = explode(' ', $parts[1]);
    $addressPostalCode = $addressPostalCodeTownParts[0];
    //Si hay fallos en desglosar la dirección aplicamos 11550 al codigo postal que es un campo obligatorio
    if (!is_numeric($addressPostalCode))
        $addressPostalCode = '11550';
    $addressTown = implode(' ', array_slice($addressPostalCodeTownParts, 1));
    
    return [
        'addressStreetName' => $addressStreetName,
        'addressStreetNumber' => $addressStreetNumber,
        'addressPostalCode' => $addressPostalCode,
        'addressTown' => $addressTown
    ];
}

// Funcion auxiliar para no insertar en el json los campos con valores null o false
function filterNestedArray($array) {
    if (is_array($array)) {
        $filteredArray = [];

        foreach ($array as $key => $value) {
            if ($value !== null && $value !== false) {
                if (is_array($value)) {
                    $filteredValue = filterNestedArray($value);
                    if (!empty($filteredValue)) {
                        $filteredArray[$key] = $filteredValue;
                    }
                } else {
                    $filteredArray[$key] = $value;
                }
            }
        }

        return $filteredArray;
    }

    return $array;
}


// Generar y enviar el feed de propiedades a Idealista
add_action( 'admin_post_idealista_properties_feed_generate_and_send', 'idealista_properties_feed_generate_and_send' );

function idealista_properties_feed_generate_and_send() {

    // Obtener las propiedades desde la URL de WordPress
    $origen_url = 'https://chipicasa.com/wp-json/wp/v2/properties';
    $response = wp_remote_get( $origen_url );

    if ( is_wp_error( $response ) ) {
        wp_die( __( 'Error retrieving properties from WordPress.', 'idealista-properties-feed' ) );
    }

    $origen = json_decode( wp_remote_retrieve_body( $response ), true );

    // Verificar si se obtuvieron propiedades
    if ( empty( $origen ) ) {
        wp_die( __( 'No properties found.', 'idealista-properties-feed' ) );
    }

    // Recorrer las propiedades y generar los datos en el formato requerido por Idealista
    $destino = [
        'customerCountry' => "Spain",
        'customerCode' => $_POST['code'],
        'customerReference' => $_POST['reference'],
        'customerSendDate' => date("Y/m/d H:i:s"),
        'customerContact' => [
            'contactName' => $_POST['name'],
            'contactEmail' => $_POST['email'],
            'contactPrimaryPhonePrefix' => "34",
            'contactPrimaryPhoneNumber' => $_POST['phone_1'],
            'contactSecondaryPhonePrefix' => "34",
            'contactSecondaryPhoneNumber' => $_POST['phone_2']
        ]
    ];
    
    //Llamadas previas a la API

    //obtengo el json que muestra los datos de los status de las propiedades
    $status_url = 'https://chipicasa.com/wp-json/wp/v2/property-statuses';
    $response_status = wp_remote_get( $status_url );
    if ( is_wp_error( $response_status ) ) {
        wp_die( __( 'Error retrieving property status.', 'idealista-properties-feed' ) );
    }
    $statuses = json_decode( wp_remote_retrieve_body( $response_status ), true );
    
    //obtengo el json que muestra los datos de la ciudades de las propiedades
    $city_url = 'https://chipicasa.com/wp-json/wp/v2/property-cities';
    $response_city = wp_remote_get( $city_url );
    if ( is_wp_error( $response_city ) ) {
        wp_die( __( 'Error retrieving property cities.', 'idealista-properties-feed' ) );
    }
    $cities = json_decode( wp_remote_retrieve_body( $response_city ), true );    
    
    //obtengo el json que muestra los datos de los tipos de propiedades
    $type_url = 'https://chipicasa.com/wp-json/wp/v2/property-types';
    $response_type = wp_remote_get( $type_url );
    if ( is_wp_error( $response_type ) ) {
        wp_die( __( 'Error retrieving property types.', 'idealista-properties-feed' ) );
    }
    $types = json_decode( wp_remote_retrieve_body( $response_type ), true );

    //obtengo el json que muestra los datos de las caracteristicas de propiedades
    $feature_url = 'https://chipicasa.com/wp-json/wp/v2/property-features';
    $response_feature = wp_remote_get( $feature_url );
    if ( is_wp_error( $response_feature ) ) {
        wp_die( __( 'Error retrieving property features.', 'idealista-properties-feed' ) );
    }
    $features = json_decode( wp_remote_retrieve_body( $response_feature ), true );    
    

    foreach ( $origen as $property ) {
        
    
        //Desglose de dirección
        $address = splitAddress( $property['property_meta']['REAL_HOMES_property_address'] );

        //obtener status. En venta o alquileres
        $status = null;
        foreach ($property['property-statuses'] as $status_id) {
            foreach ($statuses as $status_data) {
                if ($status_data['id'] === $status_id) {
                    if($status_data['name'] == "En Venta")
                        $status = "sale";
                    else    
                        $status = "rent";

                    break 2; // Salir de ambos bucles cuando se encuentra la coincidencia
                }
            }
        }

        //obtener tipo de inmueble
        $type = null;
        foreach ($property['property-types'] as $type_id) {
            foreach ($types as $type_data) {
                if ($type_data['id'] === $type_id) {
                    $type = $type_data['name'];
                    break 2; // Salir de ambos bucles cuando se encuentra la coincidencia
                }
            }
        }

        $customerProperties = [
            'propertyCode' => strval($property['id']),
            'propertyReference' => $property['property_meta']['REAL_HOMES_property_id'],
            'propertyOperation' => [
                'operationType' => $status,
                'operationPrice' => intval($property['property_meta']['REAL_HOMES_property_price']),
            ],
            'propertyAddress' => [
                'addressVisibility' => 'street',
                'addressStreetName' => $address['addressStreetName'],
                'addressStreetNumber' => $address['addressStreetNumber'],
                'addressPostalCode' => $address['addressPostalCode'],
                'addressTown' => $address['addressTown'],
                'addressCountry' => 'Spain',
                'addressCoordinatesPrecision' => 'moved',
                'addressCoordinatesLatitude' => intval($property['property_meta']['REAL_HOMES_property_location']['latitude']),
                'addressCoordinatesLongitude' => intval($property['property_meta']['REAL_HOMES_property_location']['longitude']),
            ],
            'propertyFeatures' => [
                'featuresType' => $type,
                'featuresAreaConstructed' => intval($property['property_meta']['REAL_HOMES_property_size']),
                'featuresAreaUsable' => intval($property['property_meta']['REAL_HOMES_property_lot_size']),
                'featuresBathroomNumber' => intval($property['property_meta']['REAL_HOMES_property_bathrooms']),
                'featuresBedroomNumber' => intval($property['property_meta']['REAL_HOMES_property_bedrooms']),
                'featuresBuiltYear' => intval($property['property_meta']['REAL_HOMES_property_year_built']),
                'featuresConditionedAir' => in_array(75, $property['property-features']) ? true : false,
                'featuresConservation' => in_array(73, $property['property-features']) ? 'Good' : 'toRestore',
                'featuresEnergyCertificateRating' => $property['REAL_HOMES_energy_class'],
                'featuresGarden' => in_array(75, $property['property-features']) ? true : false,
                'featuresOrientationEast' => in_array(75, $property['property-features']) ? true : false,
                'featuresOrientationWest' => in_array(75, $property['property-features']) ? true : false,
                'featuresOrientationNorth' => in_array(75, $property['property-features']) ? true : false,
                'featuresOrientationSouth' => in_array(75, $property['property-features']) ? true : false,
                'featuresPenthouse' => in_array(81, $property['property-features']) ? true : false,
                'featuresPool' => in_array(75, $property['property-features']) ? true : false,
                'featuresStorage' => in_array(75, $property['property-features']) ? true : false,
                'featuresStudio' => in_array(75, $property['property-features']) ? true : false,
                'featuresTerrace' => in_array(75, $property['property-features']) ? true : false,
                'featuresWardrobes' => in_array(75, $property['property-features']) ? true : false,
                'featuresParkingAvailable' => in_array(85, $property['property-features']) ? true : false,
                'featuresHeatingType' => in_array(20, $property['property-features']) ? true : false
            ],
            'propertyDescriptions' => [
                'descriptionLanguage' => 'spanish',
                'descriptionText' => $property['property_meta']['REAL_HOMES_additional_details_list'],
            ],
            'propertyVideos' => [],
            'propertyVirtualTours' => [
                'virtualTour3D' => [
                    'virtualTour3DType' => $property['virtualTours']['virtualTour3D']['type'],
                    'virtualTourUrl' => $property['virtualTours']['virtualTour3D']['url'],
                ],
                'virtualTour' => [
                    'virtualTourType' => $property['virtualTours']['virtualTour']['type'],
                    'virtualTourUrl' => $property['virtualTours']['virtualTour']['url'],
                ],
            ],
            'propertyUrl' => $property['url'],
        ];
    
        $imageOrder = 1; // Inicializar el contador en 1
        foreach ($property['property_meta']['REAL_HOMES_property_images'] as $image) {
            $customerProperties['propertyImages'][] = [
                'imageOrder' => $imageOrder,
                'imageLabel' => $property['title']['rendered'],
                'imageUrl' => $image['full_url'],
            ];
            $imageOrder++; // Incrementar el contador en cada iteración
        }
    
       /*  foreach ($property['videos'] as $video) {
            $customerProperties['propertyVideos'][] = [
                'videoOrder' => $video['order'],
                'videoUrl' => $video['url'],
            ];
        } */
    
        $destino['customerProperties'][] = filterNestedArray($customerProperties);
        
    }
    // Crear el archivo JSON y almacenarlo en el directorio del plugin
    $file_path = plugin_dir_path( __FILE__ ) . $destino['customerCode'] . '.json';

    $json_data = json_encode( $destino, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    if ( ! file_put_contents( $file_path, $json_data ) ) {
        wp_die( __( 'Error creating JSON file.', 'idealista-properties-feed' ) );
    } else {
        $redirect_url = add_query_arg( 'feed_status', 'success', admin_url( 'admin.php?page=idealista-properties-feed' ) );
        wp_redirect( $redirect_url );
        exit;
    }

    // Enviar el archivo JSON a Idealista

}
