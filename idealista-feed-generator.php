<?php

// Generar y enviar el feed de propiedades a Idealista
function idealista_properties_feed_generate() {
    // Obtener todas las entradas de tipo 'inmueble'
    $args = array(
        'post_type' => 'inmueble',
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);

    // Verificar si se encontraron entradas
    if ($query->have_posts()) {
        // Recuperar los datos del cliente
        $form_values = get_option( 'idealista_customer_data', array() );
        $property_data = array(
            'customerCountry' => "Spain",
            'customerCode' => sanitize_text_field( $form_values['code'] ),
            'customerReference' => sanitize_text_field( $form_values['reference'] ),
            'customerSendDate' => date("Y/m/d H:i:s"),
            'customerContact' => array(
                'contactName' => sanitize_text_field( $form_values['name'] ),
                'contactEmail' => sanitize_email( $form_values['email'] ),
                'contactPrimaryPhonePrefix' => "34",
                'contactPrimaryPhoneNumber' => sanitize_text_field( $form_values['phone_1'] ),
                'contactSecondaryPhonePrefix' => "34",
                'contactSecondaryPhoneNumber' => sanitize_text_field( $form_values['phone_2'] )
            ),
            'customerProperties' => array()
        );

        // Iterar sobre las entradas
        while ($query->have_posts()) {
            $query->the_post();
            // Obtener los datos del inmueble
            $post_id = get_the_ID();
            $inmueble_data = obtener_campos_inmueble($post_id);
            // Visibilidad direccion
            $address_visibility_options = array(
                'direccion_exacta' => 'full',
                'solo_calle' => 'street',
                'ocultar_direccion' => 'hidden'
            );
            $selected_visibility = $inmueble_data['visibilidad_direccion'];
            $address_visibility = isset($address_visibility_options[$selected_visibility]) ? $address_visibility_options[$selected_visibility] : 'hidden';
            // Coordenadas
            $coordenadas = get_post_meta( $post_id, 'campo_mapa', true );
            if ($coordenadas) {
                list($latitud, $longitud) = explode(',', $coordenadas);
                $latitud = floatval($latitud);
                $longitud = floatval($longitud);
            } else {
                $latitud = 36.7372; // Latitud de Chipiona
                $longitud = -6.4419; // Longitud de Chipiona
            }     
            $address_coordinates_precision_options = array(
                'full' => 'exact',
                'street' => 'moved',
                'hidden' => 'moved'
            );
            $address_coordinates_precision = isset($address_coordinates_precision_options[$address_visibility]) ? $address_coordinates_precision_options[$address_visibility] : 'moved';
            // Mapear la planta
            $planta = $inmueble_data['planta'];
            $planta = strtolower($planta);
            $map = [
                'sótano' => 'st',
                'bajo' => 'bj',
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
                '6' => '6',
                '7' => '7',
                '8' => '8',
            ];
            // Construir el array de datos de la propiedad según el formato de Idealista
            $property = array(
                'propertyCode' => isset($inmueble_data['codigo']) ? strval($inmueble_data['codigo']) : '',
                'propertyReference' => isset($inmueble_data['referencia']) ? strval($inmueble_data['referencia']) : '',
                'propertyVisibility' => 'idealista',
                'propertyOperation' => array(
                    'operationType' => ($inmueble_data['tipo_operacion'] === 'venta') ? 'sale' : 'rent',
                    'operationPrice' => ($inmueble_data['tipo_operacion'] === 'venta') ? floatval( $inmueble_data['precio_venta'] ) : floatval( $inmueble_data['precio_alquiler'] ),
                    'operationPriceCommunity' => isset($inmueble_data['gastos_comunidad']) && $inmueble_data['gastos_comunidad'] >= 1 ? floatval($inmueble_data['gastos_comunidad']) : null ),
                'propertyContact' => array(
                    'contactName' => $form_values['name'],
                    'contactEmail' => $form_values['email'],
                    'contactPrimaryPhonePrefix' => '34',
                    'contactPrimaryPhoneNumber' => $form_values['phone_1'],
                    'contactSecondaryPhonePrefix' => '34',
                    'contactSecondaryPhoneNumber' => $form_values['phone_2'],
                ),
                'propertyAddress' => array(
                    'addressVisibility' => $address_visibility,
                    'addressStreetName' => $inmueble_data['nombre_calle'],
                    'addressStreetNumber' => isset($inmueble_data['numero']) ? $inmueble_data['numero'] : '1',
                    "addressBlock" => isset($inmueble_data['bloque']) ? $inmueble_data['bloque'] : '',
                    'addressFloor' => isset($map[$planta]) ? $map[$planta] : null,
                    "addressStair" => isset($inmueble_data['escalera']) ? $inmueble_data['escalera'] : '',
                    "addressDoor" => isset($inmueble_data['escalera']) ? $inmueble_data['escalera'] : '',
                    'addressUrbanization' => isset($inmueble_data['urbanizacion']) ? $inmueble_data['urbanizacion'] : '',
                    'addressPostalCode' => isset($inmueble_data['cod_postal']) ? $inmueble_data['cod_postal'] : '11550', 
                    'addressTown' => isset($inmueble_data['localidad']) ? $inmueble_data['localidad'] : 'Chipiona',
                    'addressCountry' => isset($inmueble_data['pais']) ? $inmueble_data['pais'] : 'Spain',
                    'addressCoordinatesLatitude' => $latitud,
                    'addressCoordinatesLongitude' => $longitud,
                    'addressCoordinatesPrecision' => $address_coordinates_precision,
                ),
                'propertyFeatures' => array(),
                'propertyDescriptions' => array(
                    array(
                        'descriptionLanguage' => 'spanish',
                        'descriptionText' => substr($inmueble_data['descripcion'], 0, 4000) 
                    )
                ),
                'propertyImages' => array_map(function($index, $image) {
                    return array(
                        'imageOrder' => $index + 1,
                        'imageUrl' => $image
                    );
                }, array_keys($inmueble_data['galeria_imagenes'] ?? []), $inmueble_data['galeria_imagenes'] ?? []),
                'propertyUrl' => get_permalink($post_id),
            );

            // Verificar si los campos especiales código y referencia están vacíos
            if (empty($property['propertyCode']) || empty($property['propertyReference'])) {
                continue; // Saltar esta propiedad y continuar con la siguiente iteración
            }

            // Agregar el video solo si no está vacío
            if (!empty($inmueble_data['video_embed'])) {
                $property['propertyVideos'] = array(
                    array(
                        'videoOrder' => 1,
                        'videoUrl' => $inmueble_data['video_embed']
                    )
                );
            }

            // Llenar propertyFeatures dependiendo del tipo de inmueble
            switch ($inmueble_data['tipo_inmueble']) {
                case 'piso':
                    $property['propertyFeatures'] = array(
                        'featuresType' => 'flat',
                        'featuresAreaConstructed' => intval( $inmueble_data['m_construidos'] ),
                        'featuresAreaPlot' => max(1, intval($inmueble_data['m_utiles'])),                        
                        'featuresAreaUsable' => max(1, intval($inmueble_data['m_parcela']) == 0 ? intval($inmueble_data['m_utiles']) : intval($inmueble_data['m_parcela'])),                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,                        
                        'featuresBedroomNumber' => intval( $inmueble_data['num_dormitorios'] ),
                        'featuresRooms' => intval( $inmueble_data['num_banos'] + $inmueble_data['num_dormitorios'] ),
                        'featuresBuiltYear' => intval( $inmueble_data['ano_edificio'] ),
                        'featuresFloorsBuilding' => intval($inmueble_data['planta']) >= 1 ? intval($inmueble_data['planta']) : null,                        
                        'featuresConservation' => $inmueble_data['campo_estado_cons'] == 'buen_estado' ? 'good' : ($inmueble_data['campo_estado_cons'] == 'a_reformar' ? 'toRestore' : ''),
                        'featuresLiftAvailable' => $inmueble_data['ascensor'] == 'si' ? true : false,
                        'featuresWindowsLocation' => $inmueble_data['int_ext'],
                        
                        'featuresBalcony' => in_array('balcon', $inmueble_data['otra_caract_inm']),
                        'featuresConditionedAir' => in_array('aire', $inmueble_data['otra_caract_inm']),
                        'featuresChimney' => in_array('chimenea', $inmueble_data['otra_caract_inm']),
                        'featuresGarden' => in_array('jardin', $inmueble_data['otra_caract_inm']),
                        'featuresParkingAvailable' => in_array('garaje', $inmueble_data['otra_caract_inm']),
                        'featuresPool' => in_array('piscina', $inmueble_data['otra_caract_inm']),
                        'featuresStorage' => in_array('trastero', $inmueble_data['otra_caract_inm']),
                        'featuresTerrace' => in_array('terraza', $inmueble_data['otra_caract_inm']),
                        'featuresWardrobes' => in_array('armario', $inmueble_data['otra_caract_inm']),
                        
                        'featuresDuplex' => in_array('duplex', $inmueble_data['caract_inm']),
                        'featuresPenthouse' => in_array('atico', $inmueble_data['caract_inm']),
                        'featuresStudio' => in_array('estudio', $inmueble_data['caract_inm']),
                    );
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_heating_type($inmueble_data['calefaccion']));
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_orientation($inmueble_data['orientacion']));
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
                    break;

                case 'casa_chalet':
                    $tipologia_chalet = $campos['tipologia_chalet'] ?? '';
                    switch ($tipologia_chalet) {
                        case 'adosado':
                            $featuresType = 'house_terraced';
                            break;
                        case 'pareado':
                            $featuresType = 'house_semidetached';
                            break;
                        case 'independiente':
                            $featuresType = 'house_independent';
                            break;
                        default:
                            $featuresType = 'house';
                            break;
                    }
                    $property['propertyFeatures'] = array(
                        'featuresType' => $featuresType,
                        'featuresAreaConstructed' => intval( $inmueble_data['m_construidos'] ),
                        'featuresAreaPlot' => max(1, intval($inmueble_data['m_utiles'])),
                        'featuresAreaUsable' => max(1, intval($inmueble_data['m_parcela']) == 0 ? intval($inmueble_data['m_utiles']) : intval($inmueble_data['m_parcela'])),
                        'featuresFloorsBuilding' => intval($inmueble_data['num_plantas']) >= 1 ? intval($inmueble_data['num_plantas']) : null,
                        'featuresDuplex' => in_array('duplex', $inmueble_data['caract_inm']),
                        'featuresPenthouse' => in_array('atico', $inmueble_data['caract_inm']),
                        'featuresStudio' => in_array('estudio', $inmueble_data['caract_inm']),

                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,                        
                        'featuresBedroomNumber' => intval( $inmueble_data['num_dormitorios'] ),
                        'featuresConservation' => $inmueble_data['campo_estado_cons'] == 'buen_estado' ? 'good' : ($inmueble_data['campo_estado_cons'] == 'a_reformar' ? 'toRestore' : ''),
                        'featuresBuiltYear' => intval( $inmueble_data['ano_edificio'] ),

                        'featuresBalcony' => in_array('balcon', $inmueble_data['otra_caract_inm']),
                        'featuresConditionedAir' => in_array('aire', $inmueble_data['otra_caract_inm']),
                        'featuresChimney' => in_array('chimenea', $inmueble_data['otra_caract_inm']),
                        'featuresGarden' => in_array('jardin', $inmueble_data['otra_caract_inm']),
                        'featuresParkingAvailable' => in_array('garaje', $inmueble_data['otra_caract_inm']),
                        'featuresPool' => in_array('piscina', $inmueble_data['otra_caract_inm']),
                        'featuresStorage' => in_array('trastero', $inmueble_data['otra_caract_inm']),
                        'featuresTerrace' => in_array('terraza', $inmueble_data['otra_caract_inm']),
                        'featuresWardrobes' => in_array('armario', $inmueble_data['otra_caract_inm']),
                    );
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_heating_type($inmueble_data['calefaccion']));
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_orientation($inmueble_data['orientacion']));

                    break;

                case 'casa_rustica':
                    $tipo_rustica = $campos['tipo_rustica'] ?? '';
                    switch ($tipo_rustica) {
                        case 'finca':
                            $featuresType = 'rustic_terrera';
                            break;
                        case 'castillo':
                            $featuresType = 'rustic_torre';
                            break;
                        case 'casa_rural':
                            $featuresType = 'rustic_rural';
                            break;
                        case 'casa_pueblo':
                            $featuresType = 'rustic_caseron';
                            break;
                        case 'cortijo':
                            $featuresType = 'rustic_cortijo';
                            break;
                        default:
                            $featuresType = 'rustic_house';
                            break;
                    }
                    $property['propertyFeatures'] = array(
                        'featuresType' => $featuresType,
                        'featuresAreaConstructed' => intval( $inmueble_data['m_construidos'] ),
                        'featuresAreaPlot' => max(1, intval($inmueble_data['m_utiles'])),
                        'featuresAreaUsable' => max(1, intval($inmueble_data['m_parcela']) == 0 ? intval($inmueble_data['m_utiles']) : intval($inmueble_data['m_parcela'])),                        
                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,                        
                        'featuresBedroomNumber' => intval( $inmueble_data['num_dormitorios'] ),
                        'featuresRooms' => intval( $inmueble_data['num_banos'] + $inmueble_data['num_dormitorios'] ),
                        'featuresBuiltYear' => intval( $inmueble_data['ano_edificio'] ),
                        'featuresFloorsBuilding' => intval($inmueble_data['planta']) >= 1 ? intval($inmueble_data['planta']) : null,                        
                        'featuresConservation' => $inmueble_data['campo_estado_cons'] == 'buen_estado' ? 'good' : ($inmueble_data['campo_estado_cons'] == 'a_reformar' ? 'toRestore' : ''),
                        'featuresWindowsLocation' => $inmueble_data['int_ext'],
                        'featuresBalcony' => in_array('balcon', $inmueble_data['otra_caract_inm']),
                        'featuresConditionedAir' => in_array('aire', $inmueble_data['otra_caract_inm']),
                        'featuresChimney' => in_array('chimenea', $inmueble_data['otra_caract_inm']),
                        'featuresGarden' => in_array('jardin', $inmueble_data['otra_caract_inm']),
                        'featuresParkingAvailable' => in_array('garaje', $inmueble_data['otra_caract_inm']),
                        'featuresPool' => in_array('piscina', $inmueble_data['otra_caract_inm']),
                        'featuresStorage' => in_array('trastero', $inmueble_data['otra_caract_inm']),
                        'featuresTerrace' => in_array('terraza', $inmueble_data['otra_caract_inm']),
                        'featuresWardrobes' => in_array('armario', $inmueble_data['otra_caract_inm']),
                        
                        'featuresDuplex' => in_array('duplex', $inmueble_data['caract_inm']),
                        'featuresPenthouse' => in_array('atico', $inmueble_data['caract_inm']),
                        'featuresStudio' => in_array('estudio', $inmueble_data['caract_inm']),
                    );
                    break;

                case 'local':
                    $property['propertyFeatures'] = array(
                        'featuresType' => 'premises',
                        'featuresAreaConstructed' => intval( $inmueble_data['m_construidos'] ),
                        'featuresFacadeArea' => max(1, intval($inmueble_data['m_lineales']) == 0 ? intval($inmueble_data['m_utiles']) : intval($inmueble_data['m_lineales'])),                        
                        'featuresRooms' => intval( $inmueble_data['num_estancias'] ),
                        'featuresFloorsProperty' => intval( $inmueble_data['num_plantas'] ),
                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,                        
                        'featuresEquippedKitchen' => in_array('cocina_equipada', $inmueble_data['caract_local']),
                        'featuresHeating' => in_array('calefaccion', $inmueble_data['caract_local']),
                        'featuresSecurityAlarm' => in_array('alarma', $inmueble_data['caract_local']),
                        'featuresLocatedAtCorner' => in_array('esquina', $inmueble_data['caract_local']),
                        'featuresSecurityDoor' => in_array('puerta_seguridad', $inmueble_data['caract_local']),
                        'featuresStorage' => in_array('almacen', $inmueble_data['caract_local']),
                        'featuresSecuritySystem' => in_array('circuito', $inmueble_data['caract_local']),
                        'featuresSmokeExtraction' => in_array('humos', $inmueble_data['caract_local']),

                    );
                    $ubication_map = array(
                        'pie_calle' => 'street',
                        'centro_com' => 'shopping',
                        'entreplanta' => 'mezzanine',
                        'subterraneo' => 'belowGround',
                    );
                    
                    $property['propertyFeatures']['featuresUbication'] = $ubication_map[$inmueble_data['ubicacion_local']] ?? 'unknown';
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
                    break;

                case 'oficina':
                    $property['propertyFeatures'] = array(
                        'featuresType' => 'office',
                        'featuresFloorsProperty' => intval( $inmueble_data['planta'] ),
                        'featuresBuiltYear' => intval( $inmueble_data['ano_edificio'] ),
                        'featuresAreaConstructed' => intval( $inmueble_data['m_construidos'] ),
                        'featuresConservation' => $inmueble_data['campo_estado_cons'] == 'buen_estado' ? 'good' : ($inmueble_data['campo_estado_cons'] == 'a_reformar' ? 'toRestore' : ''),
                        'featuresWindowsLocation' => isset($inmueble_data['int_ext']) ? $inmueble_data['int_ext'] : '',
                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,
                        'featuresAreaUsable' => max(1, intval($inmueble_data['m_parcela']) == 0 ? intval($inmueble_data['m_utiles']) : intval($inmueble_data['m_parcela'])),                        
                        'featuresLiftNumber' => intval( $inmueble_data['num_ascensores'] ),
                        'featuresParkingSpacesNumber' => intval( $inmueble_data['num_plazas'] ),
                        'featuresFloorsBuilding' => intval($inmueble_data['num_plantas']) >= 1 ? intval($inmueble_data['num_plantas']) : null,                        
                    );
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_orientation($inmueble_data['orientacion']));
                    $property['propertyFeatures']['featuresRoomsSplitted'] = idealista_map_room_splitted($inmueble_data['distribucion_oficina']);
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_ac($inmueble_data['aire_acond']));
                    break;

                case 'garaje':
                    $property['propertyFeatures'] = array(
                        'featuresType' => 'garage',
                        'featuresAreaConstructed' => intval( $inmueble_data['m_plaza'] ),
                        'featuresGarageCapacityType' => idealista_map_garage_type($inmueble_data['tipo_plaza']),
                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : null );
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], idealista_map_garage_features($inmueble_data['caract_garaje']));
                    break;

                case 'terreno':
                    $property['propertyFeatures'] = array(
                        'featuresType' => idealista_map_terray_type($inmueble_data['tipo_terreno']),
                        'featuresAreaPlot' => max(1, intval($inmueble_data['superf_terreno'])),
                        'featuresUtilitiesRoadAccess' => $inmueble_data['acceso_rodado'] === 'si_tiene' ? true : false,
                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : null) + idealista_map_terrain_class($inmueble_data['calif_terreno']);
                    break;
            }
            
            //vaciar campos nulos
            $property = idealista_remove_empty_fields($property);
            // Agregar la propiedad al array 'customerProperties'
            $property_data['customerProperties'][] = $property;
            // Restaurar datos originales de la consulta de WordPress
            wp_reset_postdata();
        }

        // Generar el nombre del archivo con el customerCode
        $file_name = $form_values['code'] . '.json';
        // Convertir todos los datos a UTF-8
        $property_data = convert_to_utf8_recursively($property_data);
        // Convertir el array de propiedades a JSON
        $json_data = json_encode($property_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json_data === false) {
            // Algo salió mal
            $test = json_last_error_msg();
        }

        // Guardar el archivo JSON en el servidor
        $file_path = plugin_dir_path( __FILE__ ) . $file_name;
        file_put_contents( $file_path, $json_data );

        // Subir el archivo JSON al servidor FTP
        $ftp_server = $form_values['ftp_server'];
        $ftp_user = $form_values['ftp_user'];
        $ftp_pass = $form_values['ftp_pass'];


        if ( ! empty($ftp_server) && ! empty($ftp_user) && ! empty($ftp_pass) ) {
            // Conexión al servidor FTP
            $ftp_conn = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
            $login = ftp_login($ftp_conn, $ftp_user, $ftp_pass);
        
            if ($login) {
                // Subir el archivo al servidor FTP
                if (ftp_put($ftp_conn, $file_name, $file_path, FTP_ASCII)) {
                    _e("Successfully uploaded $file_name.", "idealista-properties-feed");
                } else {
                    _e("Error uploading $file_name.", "idealista-properties-feed");
                }
            } else {
                _e("FTP login failed.", "idealista-properties-feed");
            }
        
            // Cerrar la conexión FTP
            ftp_close($ftp_conn);
        }
        else {
            // Mostrar un mensaje de error porque faltan datos de conexión FTP
            $redirect_url = add_query_arg( 'feed_status', 'ftp_missing', admin_url('admin.php?page=idealista-properties-feed' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Redirigir de vuelta a la página de configuración con un mensaje de éxito
        $redirect_url = add_query_arg( 'feed_status', 'success', admin_url('admin.php?page=idealista-properties-feed' ) );
        wp_safe_redirect( $redirect_url );
        exit;
        
    }else{
        wp_die( __( 'No properties found.', 'idealista-properties-feed' ) );
    }

}
add_action( 'admin_post_idealista_properties_feed_generate', 'idealista_properties_feed_generate' );

// Eliminar campos vacíos de un array
function idealista_remove_empty_fields( $array ) {
    foreach ( $array as $key => $value ) {
        if ( is_array( $value ) ) {
            $array[$key] = idealista_remove_empty_fields( $value );
        }
        if ( $array[$key] === null || $array[$key] === '' ) {
            unset( $array[$key] );
        }
    }
    return $array;
}

// Mapear el tipo de calefacción
function idealista_map_terray_type($tipo_terreno) {
    switch ($tipo_terreno) {
        case 'urbanizable':
            return 'land_countrybuildable';
        case 'no_urbanizable':
            return 'land_countrynonbuildable';
        case 'urbano':
            return 'land_urban';
        default:
            return 'land';
    }
}

// Mapear el tipo de garaje
function idealista_map_terrain_class($calif_terreno) {
    $classification = [
        'featuresClassificationBlocks' => false,
        'featuresClassificationBlocks' => false,
        'featuresClassificationChalet' => false,
        'featuresClassificationCommercial' => false,
        'featuresClassificationHotel' => false,
        'featuresClassificationIndustrial' => false,
        'featuresClassificationOffice' => false,
        'featuresClassificationOther' => false,
        'featuresClassificationPublic' => false,
    ];

    switch ($calif_terreno) {
        case 'residencial_altura':
            $classification['featuresClassificationBlocks'] = true;
            break;
        case 'residencial_unif':
            $classification['featuresClassificationChalet'] = true;
            break;
        case 'terciario_ofi':
            $classification['featuresClassificationOffice'] = true;
            break;
        case 'terciario_com':
            $classification['featuresClassificationCommercial'] = true;
            break;
        case 'terciario_hotel':
            $classification['featuresClassificationHotel'] = true;
            break;
        case 'industrial':
            $classification['featuresClassificationIndustrial'] = true;
            break;
        case 'dotaciones':
            $classification['featuresClassificationPublic'] = true;
            break;
        case 'otra':
            $classification['featuresClassificationOther'] = true;
            break;
    }

    return $classification;
}

// Mapear el tipo de calefacción
function idealista_map_ac($airConditioning) {
    $conditionedAirType = '';
    $conditionedAir = false;
    $heating = false;

    switch ($airConditioning) {
        case 'no_disponible':
            $conditionedAirType = 'notAvailable';
            break;
        case 'frio':
            $conditionedAirType = 'cold';
            $conditionedAir = true;
            break;
        case 'frio_calor':
            $conditionedAirType = 'cold/heat';
            $conditionedAir = true;
            $heating = true;
            break;
        case 'preinstalado':
            $conditionedAirType = 'preInstallation';
            $conditionedAir = true;
            break;
    }

    return array(
        'featuresConditionedAirType' => $conditionedAirType,
        'featuresConditionedAir' => $conditionedAir,
        'featuresHeating' => $heating,
    );
}

// Mapear la distribución de las habitaciones
function idealista_map_room_splitted($room_splitted) {
    $value_mapped = 'unknown';

    switch ($room_splitted) {
        case 'diafana':
            $value_mapped = 'openPlan';
            break;
        case 'mamparas':
            $value_mapped = 'withScreens';
            break;
        case 'tabiques':
            $value_mapped = 'withWalls';
            break;
    }

    return $value_mapped;
}

// Mapear la orientación
function idealista_map_orientation($idealista_map_orientation_array) {
    $mapped_orientation = array(
        'featuresOrientationNorth' => false,
        'featuresOrientationSouth' => false,
        'featuresOrientationEast' => false,
        'featuresOrientationWest' => false,
    );

    foreach ($idealista_map_orientation_array as $orientation) {
        switch ($orientation) {
            case 'norte':
                $mapped_orientation['featuresOrientationNorth'] = true;
                break;
            case 'sur':
                $mapped_orientation['featuresOrientationSouth'] = true;
                break;
            case 'este':
                $mapped_orientation['featuresOrientationEast'] = true;
                break;
            case 'oeste':
                $mapped_orientation['featuresOrientationWest'] = true;
                break;
        }
    }

    return $mapped_orientation;
}

// Mapear los campos de energía
function idealista_map_energy_fields($calif_consumo, $consumo, $calif_emis, $emisiones) {
    $mapped_energy_fields = array(
        'featuresEnergyCertificatePerformance' => max( 0, floatval($consumo) ),
        'featuresEnergyCertificateRating' => idealista_map_energy_certificate_rating($calif_consumo),
        'featuresEnergyCertificateEmissionsRating' => idealista_map_energy_certificate_emissions_rating($calif_emis),
        'featuresEnergyCertificateEmissionsValue' => max( 0, floatval( $emisiones ) ),
    );

    // Filtra los valores null de la matriz
    $mapped_energy_fields = array_filter($mapped_energy_fields, function($value) {
        return !is_null($value);
    });

    return $mapped_energy_fields;
}

// Mapear la calificación del certificado energético
function idealista_map_energy_certificate_rating($rating) {
    $rating = strtolower($rating);

    $allowed_values = array("a", "a+", "a1", "a2", "a3", "a4", "b", "b-", "c", "d", "e", "f", "g");

    if (in_array($rating, $allowed_values)) {
        return strtoupper($rating);
    }

    switch ($rating) {
        case 'exento':
            return 'exempt';
        case 'tramite':
            return 'inProcess';
        default:
            return 'unknown';
    }
}

// Mapear la calificación de emisiones del certificado energético
function idealista_map_energy_certificate_emissions_rating($rating) {
    $allowed_values = array("A", "B", "C", "D", "E", "F", "G");

    if (in_array($rating, $allowed_values)) {
        return $rating;
    } else {
        return null;
    }
}

// Mapear el tipo de garaje
function idealista_map_garage_type($tipo_plaza) {
    switch ($tipo_plaza) {
        case 'coche_peq':
            return 'car_compact';
        case 'coche_grande':
            return 'car_sedan';
        case 'moto':
            return 'motorcycle';
        case 'coche_moto':
            return 'car_and_motorcycle';
        case 'mas_coches':
            return 'two_cars_and_more';
        default:
            return 'unknown';
    }
}

// Mapear las características del garaje
function idealista_map_garage_features($caract_garaje) {
    return array(
        'featuresLiftAvailable' => (bool) in_array('ascensor_garaje', $caract_garaje),
        'featuresParkingAutomaticDoor' => in_array('puerta_auto', $caract_garaje),
        'featuresParkingPlaceCovered' => in_array('plaza_cubierta', $caract_garaje),
        'featuresSecurityAlarm' => in_array('alarma_cerrada', $caract_garaje),
        'featuresSecurityPersonnel' => in_array('persona_seguridad', $caract_garaje),
    );
}

// Mapear el tipo de calefacción
function idealista_heating_type($calefaccion) {
    switch ($calefaccion) {
        case 'individual':
            return array('featuresHeatingType' => 'individualGas');

        case 'centralizada':
            return array('featuresHeatingType' => 'centralGas');

        case 'no_dispone':
            return array('featuresHeatingType' => 'noHeating');

        default:
            return array();
    }
}

// codificar datos
function convert_to_utf8_recursively($data) {
    if (is_string($data)) {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = convert_to_utf8_recursively($value);
        }
    }

    return $data;
}
?>