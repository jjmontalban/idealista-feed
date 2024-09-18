<?php

// Evitar acceso directo al archivo
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Generar y enviar el feed de propiedades a Idealista
function pffi_properties_feed_generate() {
    // Obtener todas las entradas de tipo 'inmueble'
    $args = array(
        'post_type' => 'inmueble',
        'posts_per_page' => -1,
    );

    $query = new WP_Query($args);

    // Verificar si se encontraron entradas
    if ($query->have_posts()) {
        // Recuperar los datos del cliente
        $form_values = get_option( 'pffi_customer_data', array() );
        $property_data = array(
            'customerCountry' => 'Spain',
            'customerCode' => sanitize_text_field( $form_values['code'] ),
            'customerReference' => sanitize_text_field( $form_values['reference'] ),
            'customerSendDate' => gmdate('Y/m/d H:i:s'),
            'customerContact' => array(
                'contactName' => sanitize_text_field( $form_values['name'] ),
                'contactEmail' => sanitize_email( $form_values['email'] ),
                'contactPrimaryPhonePrefix' => '34',
                'contactPrimaryPhoneNumber' => sanitize_text_field( $form_values['phone_1'] ),
                'contactSecondaryPhonePrefix' => '34',
                'contactSecondaryPhoneNumber' => sanitize_text_field( $form_values['phone_2'] ),
            ),
            'customerProperties' => array()
        );

        // Iterar sobre las entradas
        while ($query->have_posts()) {
            $query->the_post();
            // Obtener los datos del inmueble
            $post_id = get_the_ID();
            $inmueble_data = pffi_obtener_campos_inmueble_feed($post_id);

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
            $planta = strtolower($inmueble_data['planta']);
            $map = array(
                'sótano' => 'st',
                'bajo' => 'bj',
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
                '6' => '6',
                '7' => '7',
                '8' => '8'
            );

            // Construir el array de datos de la propiedad según el formato de Idealista
            $property = array(
                'propertyCode' => isset($inmueble_data['codigo']) ? strval($inmueble_data['codigo']) : '',
                'propertyReference' => isset($inmueble_data['referencia']) ? strval($inmueble_data['referencia']) : '',
                'propertyVisibility' => 'idealista',
                'propertyOperation' => array(
                    'operationType' => ($inmueble_data['tipo_operacion'] === 'venta') ? 'sale' : 'rent',
                    'operationPrice' => ($inmueble_data['tipo_operacion'] === 'venta') ? floatval( $inmueble_data['precio_venta'] ) : floatval( $inmueble_data['precio_alquiler'] ),
                    'operationPriceCommunity' => isset($inmueble_data['gastos_comunidad']) && $inmueble_data['gastos_comunidad'] >= 1 ? floatval($inmueble_data['gastos_comunidad']) : null,
                ),
                'propertyContact' => array(
                    'contactName' => sanitize_text_field( $form_values['name'] ),
                    'contactEmail' => sanitize_email( $form_values['email'] ),
                    'contactPrimaryPhonePrefix' => '34',
                    'contactPrimaryPhoneNumber' => sanitize_text_field( $form_values['phone_1'] ),
                    'contactSecondaryPhonePrefix' => '34',
                    'contactSecondaryPhoneNumber' => sanitize_text_field( $form_values['phone_2'] ),
                ),
                'propertyAddress' => array(
                    'addressVisibility' => $address_visibility,
                    'addressStreetName' => sanitize_text_field( $inmueble_data['nombre_calle'] ),
                    'addressStreetNumber' => isset($inmueble_data['numero']) ? sanitize_text_field( $inmueble_data['numero'] ) : '1',
                    'addressBlock' => isset($inmueble_data['bloque']) ? sanitize_text_field( $inmueble_data['bloque'] ) : '',
                    'addressFloor' => isset($map[$planta]) ? $map[$planta] : null,
                    'addressStair' => isset($inmueble_data['escalera']) ? sanitize_text_field( $inmueble_data['escalera'] ) : '',
                    'addressDoor' => isset($inmueble_data['escalera']) ? sanitize_text_field( $inmueble_data['escalera'] ) : '',
                    'addressUrbanization' => isset($inmueble_data['urbanizacion']) ? sanitize_text_field( $inmueble_data['urbanizacion'] ) : '',
                    'addressPostalCode' => isset($inmueble_data['cod_postal']) ? sanitize_text_field( $inmueble_data['cod_postal'] ) : '11550',
                    'addressTown' => isset($inmueble_data['localidad']) ? sanitize_text_field( $inmueble_data['localidad'] ) : 'Chipiona',
                    'addressCountry' => isset($inmueble_data['pais']) ? sanitize_text_field( $inmueble_data['pais'] ) : 'Spain',
                    'addressCoordinatesLatitude' => $latitud,
                    'addressCoordinatesLongitude' => $longitud,
                    'addressCoordinatesPrecision' => $address_coordinates_precision,
                ),
                'propertyFeatures' => array(),
                'propertyDescriptions' => array(
                    array(
                        'descriptionLanguage' => 'spanish',
                        'descriptionText' => sanitize_text_field( substr($inmueble_data['descripcion'], 0, 4000) )
                    )
                ),
                'propertyImages' => array_merge(
                    array_map(function($index, $image) {
                        return array(
                            'imageOrder' => $index + 1,
                            'imageUrl' => esc_url($image)
                        );
                    }, array_keys($inmueble_data['galeria_imagenes'] ?? []), $inmueble_data['galeria_imagenes'] ?? []),
                    array_filter(
                        array_map(function($index, $image) use ($inmueble_data) {
                            return array(
                                'imageOrder' => count($inmueble_data['galeria_imagenes'] ?? []) + $index + 1,
                                'imageUrl' => esc_url($image),
                                'imageLabel' => 'plan'
                            );
                        }, array_keys(array_filter([
                            $inmueble_data['plano1'] ?? null,
                            $inmueble_data['plano2'] ?? null,
                            $inmueble_data['plano3'] ?? null,
                            $inmueble_data['plano4'] ?? null
                        ])), array_filter([
                            $inmueble_data['plano1'] ?? null,
                            $inmueble_data['plano2'] ?? null,
                            $inmueble_data['plano3'] ?? null,
                            $inmueble_data['plano4'] ?? null
                        ])),
                        function($image) {
                            return !empty($image['imageUrl']);
                        }
                    )
                ),
                'propertyUrl' => esc_url(get_permalink($post_id)),
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
                        'videoUrl' => esc_url($inmueble_data['video_embed'])
                    )
                );
            }

            // Llenar propertyFeatures dependiendo del tipo de inmueble
            switch ($inmueble_data['tipo_inmueble']) {
                case 'piso':
                    $property['propertyFeatures'] = array(
                        'featuresType' => 'flat',
                        'featuresAreaConstructed' => intval( $inmueble_data['m_construidos'] ),
                        'featuresAreaPlot' => max(1, intval($inmueble_data['m_parcela'])),
                        'featuresAreaUsable' => max(1, intval($inmueble_data['m_utiles'])),
                        'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,
                        'featuresBedroomNumber' => intval( $inmueble_data['num_dormitorios'] ),
                        'featuresRooms' => intval( $inmueble_data['num_banos'] + $inmueble_data['num_dormitorios'] ),
                        'featuresBuiltYear' => intval( $inmueble_data['ano_edificio'] ),
                        'featuresFloorsBuilding' => intval($inmueble_data['planta']) >= 1 ? intval($inmueble_data['planta']) : null,
                        'featuresConservation' => ($inmueble_data['campo_estado_cons'] == 'buen_estado') ? 'good' : (($inmueble_data['campo_estado_cons'] == 'a_reformar') ? 'toRestore' : ''),
                        'featuresLiftAvailable' => ($inmueble_data['ascensor'] == 'si') ? true : false,
                        'featuresWindowsLocation' => sanitize_text_field($inmueble_data['int_ext']),
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
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_heating_type($inmueble_data['calefaccion']));
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_orientation($inmueble_data['orientacion']));
                    $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
                    break;

                    case 'casa_chalet':
                        $tipologia_chalet = $inmueble_data['tipologia_chalet'] ?? '';
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
                            'featuresAreaPlot' => max(1, intval($inmueble_data['m_parcela'])),
                            'featuresAreaUsable' => max(1, intval($inmueble_data['m_utiles'])),
                            'featuresFloorsBuilding' => intval($inmueble_data['num_plantas']) >= 1 ? intval($inmueble_data['num_plantas']) : null,
                            'featuresDuplex' => in_array('duplex', $inmueble_data['caract_inm']),
                            'featuresPenthouse' => in_array('atico', $inmueble_data['caract_inm']),
                            'featuresStudio' => in_array('estudio', $inmueble_data['caract_inm']),
                            'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : 1,
                            'featuresBedroomNumber' => intval( $inmueble_data['num_dormitorios'] ),
                            'featuresConservation' => ($inmueble_data['campo_estado_cons'] == 'buen_estado') ? 'good' : (($inmueble_data['campo_estado_cons'] == 'a_reformar') ? 'toRestore' : ''),
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
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_heating_type($inmueble_data['calefaccion']));
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_orientation($inmueble_data['orientacion']));
                        break;
                    
                    case 'casa_rustica':
                        $tipo_rustica = $inmueble_data['tipo_rustica'] ?? '';
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
                            'featuresAreaPlot' => max(1, intval($inmueble_data['m_parcela'])),
                            'featuresAreaUsable' => max(1, intval($inmueble_data['m_utiles'])),
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
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_heating_type($inmueble_data['calefaccion']));
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_orientation($inmueble_data['orientacion']));

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
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_energy_fields($inmueble_data['calif_consumo'], $inmueble_data['consumo'], $inmueble_data['calif_emis'], $inmueble_data['emisiones']));
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
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_orientation($inmueble_data['orientacion']));
                        $property['propertyFeatures']['featuresRoomsSplitted'] = pffi_map_room_splitted($inmueble_data['distribucion_oficina']);
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_ac($inmueble_data['aire_acond']));
                        break;
                    
                    case 'garaje':
                        $property['propertyFeatures'] = array(
                            'featuresType' => 'garage',
                            'featuresAreaConstructed' => intval( $inmueble_data['m_plaza'] ),
                            'featuresGarageCapacityType' => pffi_map_garage_type($inmueble_data['tipo_plaza']),
                            'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : null
                        );
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_garage_features($inmueble_data['caract_garaje']));
                        break;
                    
                    case 'terreno':
                        $property['propertyFeatures'] = array(
                            'featuresType' => pffi_map_terray_type($inmueble_data['tipo_terreno']),
                            'featuresAreaPlot' => max(1, intval($inmueble_data['superf_terreno'])),
                            'featuresUtilitiesRoadAccess' => $inmueble_data['acceso_rodado'] === 'si_tiene' ? true : false,
                            'featuresBathroomNumber' => isset($inmueble_data['num_banos']) && !is_null($inmueble_data['num_banos']) && $inmueble_data['num_banos'] > 0 ? intval($inmueble_data['num_banos']) : null
                        );
                        $property['propertyFeatures'] = array_merge($property['propertyFeatures'], pffi_map_terrain_class($inmueble_data['calif_terreno']));
                        break;
            }
            
            //vaciar campos nulos
            $property = pffi_remove_empty_fields($property);
            // Agregar la propiedad al array 'customerProperties'
            $property_data['customerProperties'][] = $property;
            // Restaurar datos originales de la consulta de WordPress
            wp_reset_postdata();
        }

        // Generar el nombre del archivo con el customerCode
        $file_name = $form_values['code'] . '.json';
        // Convertir todos los datos a UTF-8
        $property_data = pffi_convert_to_utf8_recursively($property_data);
        // Convertir el array de propiedades a JSON
        $json_data = wp_json_encode($property_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json_data === false) {
            // Algo salió mal
            $test = json_last_error_msg();
            error_log("Error en la conversión JSON" );
            wp_die("Error en la conversión JSON" );
        }

        // Guardar el archivo JSON en el servidor
        // Cargar WP_Filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Acceso al sistema de archivos de WordPress
        if ( WP_Filesystem() ) {
            global $wp_filesystem;

            // Ruta donde guardar el archivo
            $file_path = plugin_dir_path( __FILE__ ) . $file_name;

            // Escribir el contenido en el archivo usando WP_Filesystem
            if ( $wp_filesystem->put_contents( $file_path, $json_data, FS_CHMOD_FILE ) ) {
                error_log( "Archivo JSON guardado exitosamente en: " . $file_path );
            } else {
                error_log( "Error al guardar el archivo JSON en: " . $file_path );
            }
        } else {
            error_log( "No se pudo obtener acceso al sistema de archivos de WordPress." );
        }

        // Realizar la solicitud remota
        $response = wp_remote_get($file_path);

        // Verificar si la solicitud fue exitosa
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Error al obtener el archivo remoto: " . $error_message);
        } else {
            // Obtener el contenido del archivo si la solicitud fue exitosa
            $contents = wp_remote_retrieve_body($response);

            // Verificar si el contenido es válido
            if (!empty($contents)) {
                error_log("Contenido del archivo JSON antes de la transferencia: " . $contents);
            } else {
                error_log("El archivo remoto está vacío o no se pudo obtener correctamente.");
            }
        }

        // Subir el archivo JSON al servidor FTP
        $ftp_server = $form_values['ftp_server'];
        $ftp_user = $form_values['ftp_user'];
        $ftp_pass = $form_values['ftp_pass'];

        if (!empty($ftp_server) && !empty($ftp_user) && !empty($ftp_pass)) {
            // Conexión al servidor FTP
            $ftp_conn = ftp_connect($ftp_server);
            if (!$ftp_conn) {
                error_log("No se pudo conectar al servidor FTP");
                wp_die("No se pudo conectar al servidor FTP");
            }

            $login = ftp_login($ftp_conn, $ftp_user, $ftp_pass);
            if (!$login) {
                error_log("Error de inicio de sesión en el servidor FTP con el usuario dado");
                wp_die("Error de inicio de sesión en el servidor FTP con el usuario dado");
            }

            if ($login) {
                ftp_pasv($ftp_conn, true); // Modo pasivo
                error_log("Conexión FTP y autenticación exitosa al servidor: $ftp_server en modo pasivo");

                // Verificar el directorio de destino
                $remote_dir = '/';
                if (ftp_chdir($ftp_conn, $remote_dir)) {
                    error_log("Directorio actual en el servidor FTP: " . ftp_pwd($ftp_conn));
                } else {
                    error_log( "No se pudo cambiar al directorio: $remote_dir" );
                    wp_die( "No se pudo cambiar al directorio" );
                }

                // Subir el archivo al servidor FTP
                if (ftp_put($ftp_conn, $file_name, $file_path, FTP_BINARY)) {
                    esc_html_e( "File Successfully uploaded in binary mode.", "properties-feed-for-idealista" );
                } else {
                    $last_error = error_get_last();
                    error_log("Error al subir el archivo en modo binario.");
                    error_log("Detalles del último error de PHP: " . print_r($last_error, true));

                    // Intentar obtener más información del servidor FTP
                    $ftp_response = ftp_raw($ftp_conn, 'STAT');
                    if ($ftp_response) {
                        error_log("Respuesta del servidor FTP: " . print_r($ftp_response, true));
                    }

                    $ftp_systype = ftp_systype($ftp_conn);
                    if ($ftp_systype) {
                        error_log("Tipo de sistema del servidor FTP: " . $ftp_systype);
                    }

                    wp_die( "Error uploading file" );
                }
            }

            ftp_close($ftp_conn);
        }
        else {
            // Mostrar un mensaje de error porque faltan datos de conexión FTP
            $redirect_url = add_query_arg( 'feed_status', 'ftp_missing', admin_url('admin.php?page=properties-feed-for-idealista' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Redirigir de vuelta a la página de configuración con un mensaje de éxito
        $redirect_url = add_query_arg( 'feed_status', 'success', admin_url('admin.php?page=properties-feed-for-idealista' ) );
        wp_safe_redirect( $redirect_url );
        exit;
        
    }else{
        wp_die( esc_html__( 'No properties found.', 'properties-feed-for-idealista' ) );
    }

}
add_action( 'admin_post_pffi_properties_feed_generate', 'pffi_properties_feed_generate' );

/**
 * Obtiene todos los campos personalizados del inmueble.
 * @param int $post_id El ID del post actual.
 * @return array Un array con todos los campos personalizados y sus valores.
 */
function pffi_obtener_campos_inmueble_feed($post_id) {
    $meta_values = get_post_meta($post_id);

    // Verificar si algún valor está serializado y deserializarlo
    foreach ($meta_values as $key => $value) {
        $meta_values[$key] = maybe_unserialize($value[0]);
    }

    // Verificación específica para 'tipo_inmueble'
    if (isset($meta_values['tipo_inmueble']) && is_serialized($meta_values['tipo_inmueble'])) {
        $meta_values['tipo_inmueble'] = maybe_unserialize($meta_values['tipo_inmueble']);
    }

    return $meta_values;
}


/**
 * Eliminar campos vacíos de un array.
 */
function pffi_remove_empty_fields( $array ) {
    foreach ( $array as $key => $value ) {
        if ( is_array( $value ) ) {
            $array[$key] = pffi_remove_empty_fields( $value );
        }
        if ( $array[$key] === null || $array[$key] === '' ) {
            unset( $array[$key] );
        }
    }
    return $array;
}


/**
 * Mapear el tipo de terreno
 */
function pffi_map_terray_type($tipo_terreno) {
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


/**
 * Mapear la clase de terreno
 */
function pffi_map_terrain_class($calif_terreno) {
    $classification = [
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


/**
 * Mapear el tipo de calefacción
 */
function pffi_map_ac($airConditioning) {
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

/**
 * Mapear la distribución de las habitaciones
 */
function pffi_map_room_splitted($room_splitted) {
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


/**
 * Mapear la orientación.
 */
function pffi_map_orientation($orientaciones) {
    $mapped_orientation = array(
        'featuresOrientationNorth' => false,
        'featuresOrientationSouth' => false,
        'featuresOrientationEast' => false,
        'featuresOrientationWest' => false,
    );

    foreach ($orientaciones as $orientation) {
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


/**
 * Mapear los campos de energía.
 */
function pffi_map_energy_fields($calif_consumo, $consumo, $calif_emis, $emisiones) {
    $mapped_energy_fields = array(
        'featuresEnergyCertificatePerformance' => max( 0, floatval($consumo) ),
        'featuresEnergyCertificateRating' => pffi_map_energy_certificate_rating($calif_consumo),
        'featuresEnergyCertificateEmissionsRating' => pffi_map_energy_certificate_emissions_rating($calif_emis),
        'featuresEnergyCertificateEmissionsValue' => max( 0, floatval( $emisiones ) ),
    );

    // Filtra los valores null de la matriz
    $mapped_energy_fields = array_filter($mapped_energy_fields, function($value) {
        return !is_null($value);
    });

    return $mapped_energy_fields;
}


/**
 * Mapear la calificación del certificado energético.
 */
function pffi_map_energy_certificate_rating($rating) {
    $allowed_values = array("a", "a+", "a1", "a2", "a3", "a4", "b", "b-", "c", "d", "e", "f", "g");

    if (in_array(strtolower($rating), $allowed_values)) {
        return strtoupper($rating);
    }

    switch (strtolower($rating)) {
        case 'exento':
            return 'exempt';
        case 'tramite':
            return 'inProcess';
        default:
            return 'unknown';
    }
}


/**
 * Mapear la calificación de emisiones del certificado energético.
 */
function pffi_map_energy_certificate_emissions_rating($rating) {
    $allowed_values = array("A", "B", "C", "D", "E", "F", "G");

    return in_array($rating, $allowed_values) ? $rating : null;
}


/**
 * Mapear el tipo de garaje.
 */
function pffi_map_garage_type($tipo_plaza) {
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


/**
 * Mapear las características del garaje.
 */
function pffi_map_garage_features($caract_garaje) {
    return array(
        'featuresLiftAvailable' => (bool) in_array('ascensor_garaje', $caract_garaje),
        'featuresParkingAutomaticDoor' => in_array('puerta_auto', $caract_garaje),
        'featuresParkingPlaceCovered' => in_array('plaza_cubierta', $caract_garaje),
        'featuresSecurityAlarm' => in_array('alarma_cerrada', $caract_garaje),
        'featuresSecurityPersonnel' => in_array('persona_seguridad', $caract_garaje),
    );
}

/**
 * Mapear el tipo de calefacción
 */
function pffi_heating_type($calefaccion) {
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

/**
 * Convertir los datos a UTF-8 de forma recursiva.
 */
function pffi_convert_to_utf8_recursively( $data ) {
    if ( is_string( $data ) ) {
        return mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
    }

    if ( is_array( $data ) ) {
        foreach ( $data as $key => $value ) {
            $data[ $key ] = pffi_convert_to_utf8_recursively( $value );
        }
    }

    return $data;
}
?>