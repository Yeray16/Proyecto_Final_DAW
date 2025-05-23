<?php 
/**
 * Register/enqueue custom scripts and styles
 */
add_action( 'wp_enqueue_scripts', function() {
	// Enqueue your files on the canvas & frontend, not the builder panel. Otherwise custom CSS might affect builder)
	if ( ! bricks_is_builder_main() ) {
		wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'], filemtime( get_stylesheet_directory() . '/style.css' ) );
	}
} );

/**
 * Register custom elements
 */
add_action( 'init', function() {
  $element_files = [
    __DIR__ . '/elements/title.php',
  ];

  foreach ( $element_files as $file ) {
    \Bricks\Elements::register_element( $file );
  }
}, 11 );

/**
 * Add text strings to builder
 */
add_filter( 'bricks/builder/i18n', function( $i18n ) {
  // For element category 'custom'
  $i18n['custom'] = esc_html__( 'Custom', 'bricks' );

  return $i18n;
} );

// Registrar el script de geolocalización y AJAX
function enqueue_geolocation_scripts() {
    if (!bricks_is_builder_main()) {
        wp_enqueue_script('restaurantes-geolocation', get_stylesheet_directory_uri() . '/js/restaurantes-geolocation.js', array('jquery'), '1.1', true);
        wp_localize_script('restaurantes-geolocation', 'restaurantesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('restaurantes_nonce')
        ));
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_geolocation_scripts');

// Función para calcular la distancia usando la fórmula de Haversine
function calcular_distancia($lat1, $lon1, $lat2, $lon2) {
  $earth_radius = 6371; // Radio de la Tierra en km
  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);
  $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
  $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
  return $earth_radius * $c; // Distancia en km
}

// Procesar la solicitud AJAX
function obtener_restaurantes_cercanos() {
  check_ajax_referer('restaurantes_nonce', 'nonce');

  $lat_usuario = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
  $lon_usuario = isset($_POST['lon']) ? floatval($_POST['lon']) : 0;
  $distancia_maxima = isset($_POST['distancia_maxima']) ? floatval($_POST['distancia_maxima']) : 10;

  if ($lat_usuario == 0 || $lon_usuario == 0 || $lat_usuario < -90 || $lat_usuario > 90 || $lon_usuario < -180 || $lon_usuario > 180) {
      wp_send_json_error('Coordenadas inválidas.');
  }

  $args = array(
      'post_type' => 'restaurante',
      'posts_per_page' => 20,
      'meta_query' => array(
          'relation' => 'AND',
          array(
              'key' => 'latitud',
              'value' => '^[0-9.-]+$',
              'compare' => 'REGEXP',
          ),
          array(
              'key' => 'longitud',
              'value' => '^[0-9.-]+$',
              'compare' => 'REGEXP',
          ),
        array(
        'key' => 'restaurante_abierto',
        'value' => '1',
        'compare' => '=',
        ),
      ),
  );

  // Filtrar por mesas disponibles si se solicita
  if (isset($_POST['mesas_disponibles']) && $_POST['mesas_disponibles'] == 1) {
      $args['meta_query'][] = array(
          'key' => 'nº_mesas_disponibles',
          'value' => 0,
          'compare' => '>',
          'type' => 'NUMERIC',
      );
  }

  $restaurantes = new WP_Query($args);
  $resultados = array();

  if ($restaurantes->have_posts()) {
      while ($restaurantes->have_posts()) {
          $restaurantes->the_post();
          $lat_restaurante = get_post_meta(get_the_ID(), 'latitud', true);
          $lon_restaurante = get_post_meta(get_the_ID(), 'longitud', true);

          if (is_numeric($lat_restaurante) && is_numeric($lon_restaurante)) {
              $distancia = calcular_distancia($lat_usuario, $lon_usuario, floatval($lat_restaurante), floatval($lon_restaurante));
              if ($distancia <= $distancia_maxima) {
                  $resultados[] = array(
                      'id' => get_the_ID(),
                      'titulo' => get_the_title(),
                      'distancia' => round($distancia, 2),
                      'enlace' => get_permalink(),
                      'direccion' => get_post_meta(get_the_ID(), 'direccion', true),
                      'mesas_disponibles' => intval(get_post_meta(get_the_ID(), 'nº_mesas_disponibles', true)),
                      'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
                      'latitud' => $lat_restaurante,
                      'longitud' => $lon_restaurante,
                      'horario' => get_post_meta(get_the_ID(), 'horario', true),
                  );
              }
          }
      }
      wp_reset_postdata();
  }

  usort($resultados, function ($a, $b) {
      return $a['distancia'] <=> $b['distancia'];
  });

  wp_send_json_success($resultados);
}
add_action('wp_ajax_obtener_restaurantes_cercanos', 'obtener_restaurantes_cercanos');
add_action('wp_ajax_nopriv_obtener_restaurantes_cercanos', 'obtener_restaurantes_cercanos');
add_action('wp_enqueue_scripts', function () {
    if (is_page('reserva')) {
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

        wp_enqueue_script('reserva-js', get_stylesheet_directory_uri() . '/js/reserva.js', ['jquery', 'leaflet-js'], null, true);
    }
});
add_action('wp_ajax_crear_reserva', 'crear_reserva');
add_action('wp_ajax_nopriv_crear_reserva', 'crear_reserva');
add_action('wp_enqueue_scripts', function () {
    if (is_page('reserva')) {
        // Cargar estilos y scripts de Leaflet solo en la página de reservas
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

        // Cargar el script de reservas
        wp_enqueue_script('reservas-js', get_stylesheet_directory_uri() . '/js/reservas.js', ['jquery', 'leaflet-js'], '1.0', true);

        // Pasar variables de PHP a JS para AJAX
        wp_localize_script('reservas-js', 'reservasAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reservas_nonce'),
        ));
    }
});

function crear_reserva() {
    // Verifica nonce
    check_ajax_referer('reservas_nonce', 'nonce');

    $nombre = sanitize_text_field($_POST['nombre']);
    $telefono = sanitize_text_field($_POST['telefono']);
    $comensales = intval($_POST['comensales']);
    $fecha = sanitize_text_field($_POST['fecha']);
    $restaurante_id = intval($_POST['restaurante_id']);

    // Crear el post tipo 'Datos_reserva'
    $post_id = wp_insert_post([
        'post_type'   => 'datos-reserva',
        'post_status' => 'publish',
        'post_title'  => $nombre . ' - ' . $fecha,
    ]);

    if ($post_id) {
        update_field('Nombre_cliente', $nombre, $post_id);
        update_field('telefono', $telefono, $post_id);
        update_field('comensales', $comensales, $post_id);
        update_field('fecha', $fecha, $post_id);
        update_field('restaurante', $restaurante_id, $post_id);

        wp_send_json_success('Reserva creada correctamente: ');
    } else {
        wp_send_json_error('No se pudo crear la reserva.');
    }
}

function mostrar_reservas_restaurante_usuario() {
    if (!is_user_logged_in()) return 'Debes iniciar sesión.';

    $current_user_id = get_current_user_id();

    $restaurantes = get_posts([
        'post_type' => 'restaurante',
        'meta_query' => [
            [
                'key' => 'usuario_restaurante',
                'value' => $current_user_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);
    if (!$restaurantes) {
        $mensaje = '';
        $mensaje .= '<div style="background-color: #ef9a9a; color: #b71c1c; border: 2px solid #b71c1c; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">';
        $mensaje .= '<p><strong>No tiene ningún restaurante asociado</strong></p>';
        return $mensaje;
    }

    $restaurante_id = $restaurantes[0]->ID;

    $reservas = get_posts([
        'post_type' => 'datos-reserva',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'restaurante',
                'value' => $restaurante_id,
                'compare' => '='
            ]
        ]
    ]);

    if (!$reservas) {
        $mensaje = '';
        $mensaje .= '<div style="background-color: #ef9a9a; color: #b71c1c; border: 2px solid #b71c1c; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">';
        $mensaje .= '<p><strong>No existen reservas actualmente.</strong></p>';
        return $mensaje;
    }
    // Contenedor con barra de desplazamiento
    $output = '<div style="max-height: 600px; overflow-y: auto; padding-right: 10px; ">';

    foreach ($reservas as $reserva) {
        $output .= '<div style="background-color:rgb(164, 252, 255); color:rgb(0, 51, 145); border: 2px solid rgb(0, 51, 145); padding: 1rem; margin-bottom: 2rem; border-radius: 8px;">';
        
        $output .= '<p><strong>Nombre:</strong> ' . esc_html(get_field('nombre_cliente', $reserva->ID)) . '</p>';
        $output .= '<p><strong>Teléfono:</strong> ' . esc_html(get_field('telefono', $reserva->ID)) . '</p>';
        $output .= '<p><strong>Comensales:</strong> ' . esc_html(get_field('comensales', $reserva->ID)) . '</p>';
        $output .= '<p><strong>Fecha:</strong> ' . esc_html(get_field('fecha', $reserva->ID)) . '</p>';
        $output .= '</div>';
    }
    
    return $output;
}
add_shortcode('reservas_restaurante', 'mostrar_reservas_restaurante_usuario');

// Función para mostrar reservas del día actual
function mostrar_reservas_hoy() {
    // Extraer el ID del usuario actual
    $current_user_id = get_current_user_id();

    // Buscar el restaurante asociado al usuario
    $restaurantes = get_posts([
        'post_type' => 'restaurante',
        'meta_query' => [
            [
                'key' => 'usuario_restaurante',
                'value' => $current_user_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    // Si no hay restaurante asociado
    if (!$restaurantes || !is_array($restaurantes)) {
        $mensaje = '<div style="background-color: #ef9a9a; color: #b71c1c; border: 2px solid #b71c1c; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">';
        $mensaje .= '<p><strong>No tiene ningún restaurante asociado</strong></p>';
        $mensaje .= '</div>';
        return $mensaje;
    }

    $restaurante_id = $restaurantes[0]->ID;

    // Obtener la hora actual
    $hora_actual = new DateTime('now', new DateTimeZone('Europe/Lisbon')); // WEST

    // Array para traducir los nombres de los meses al español
    $meses = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
        'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
        'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];

    // Obtener la fecha actual en el formato "d de F de Y"
    $dia = $hora_actual->format('j'); 
    $mes = $meses[$hora_actual->format('F')]; 
    $anio = $hora_actual->format('Y');
    $fecha_actual = "$dia de $mes de $anio";

    // Consulta para reservas del día actual
    $reservas_hoy = get_posts([
        'post_type' => 'datos-reserva',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'restaurante',
                'value' => $restaurante_id,
                'compare' => '='
            ],
            [
                'key' => 'fecha',
                'value' => $fecha_actual,
                'compare' => 'LIKE' // Buscar coincidencia parcial (ignorando la hora)
            ]
        ]
    ]);

    // Filtrar reservas cuya hora sea mayor a la hora actual
    $reservas_filtradas = [];
    if ($reservas_hoy && is_array($reservas_hoy)) {
        foreach ($reservas_hoy as $reserva) {
            if (is_object($reserva) && isset($reserva->ID)) {
                $fecha_reserva_str = get_field('fecha', $reserva->ID);
                if ($fecha_reserva_str) {
                    $fecha_reserva = parse_fecha_reserva($fecha_reserva_str, new DateTimeZone('Europe/Lisbon'));
                    if ($fecha_reserva && $fecha_reserva > $hora_actual) {
                        $reservas_filtradas[] = $reserva;
                    }
                }
            }
        }
    }

    // Contenedor para reservas del día actual
    $output = '<div style="max-height: 600px; overflow-y: auto; padding-right: 10px;">';
    if ($reservas_filtradas) {
        foreach ($reservas_filtradas as $reserva) {
            $output .= '<div style="background-color: #a5d6a7; color: #1b5e20; border: 2px solid #1b5e20; padding: 1rem; margin-bottom: 2rem; border-radius: 8px;">';
            $output .= '<p><strong>Nombre:</strong> ' . esc_html(get_field('nombre_cliente', $reserva->ID)) . '</p>';
            $output .= '<p><strong>Teléfono:</strong> ' . esc_html(get_field('telefono', $reserva->ID)) . '</p>';
            $output .= '<p><strong>Comensales:</strong> ' . esc_html(get_field('comensales', $reserva->ID)) . '</p>';
            $output .= '<p><strong>Fecha:</strong> ' . esc_html(get_field('fecha', $reserva->ID)) . '</p>';
            $output .= '</div>';
        }
    } else {
        $output .= '<p style="color: #b71c1c;"><strong>No hay reservas futuras para hoy.</strong></p>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('reservas_hoy', 'mostrar_reservas_hoy');

// Función auxiliar para parsear la fecha
function parse_fecha_reserva($fecha_str, $timezone) {
    $meses = [
        'Enero' => 'January', 'Febrero' => 'February', 'Marzo' => 'March', 'Abril' => 'April',
        'Mayo' => 'May', 'Junio' => 'June', 'Julio' => 'July', 'Agosto' => 'August',
        'Septiembre' => 'September', 'Octubre' => 'October', 'Noviembre' => 'November', 'Diciembre' => 'December'
    ];

    // Parsear directamente con el formato original (meses en inglés)
    $fecha = DateTime::createFromFormat('j \d\e F \d\e Y H:i', $fecha_str, $timezone);
    if (!$fecha) {
        // Si falla, ajustar el mes a inglés y reintentar
        $fecha_str_adjusted = preg_replace_callback(
            '/(\d{1,2}) de ([A-Za-záéíóúÁÉÍÓÚñÑ]+) de (\d{4}) (\d{2}:\d{2})/',
            function ($matches) use ($meses) {
                $mes_en = $meses[ucfirst(strtolower($matches[2]))] ?? $matches[2];
                return $matches[1] . ' de ' . $mes_en . ' de ' . $matches[3] . ' ' . $matches[4];
            },
            $fecha_str
        );
        $fecha = DateTime::createFromFormat('j \d\e F \d\e Y H:i', $fecha_str_adjusted, $timezone);
    }
    return $fecha;
}

// Función para mostrar reservas atrasadas
function mostrar_reservas_atrasadas() {
    // Extraer el ID del usuario actual
    $current_user_id = get_current_user_id();

    // Buscar el restaurante asociado al usuario
    $restaurantes = get_posts([
        'post_type' => 'restaurante',
        'meta_query' => [
            [
                'key' => 'usuario_restaurante',
                'value' => $current_user_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    // Si no hay restaurante asociado
    if (!$restaurantes || !is_array($restaurantes)) {
        $mensaje = '<div style="background-color: #ef9a9a; color: #b71c1c; border: 2px solid #b71c1c; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">';
        $mensaje .= '<p><strong>No tiene ningún restaurante asociado</strong></p>';
        $mensaje .= '</div>';
        return $mensaje;
    }

    $restaurante_id = $restaurantes[0]->ID;

    // Obtener la hora actual y la hora límite (hace 1 hora)
    $hora_actual = new DateTime('now', new DateTimeZone('Europe/Lisbon')); // WEST
    $hora_limite = clone $hora_actual;
    $hora_limite->modify('-1 hour');

    // Array para traducir los nombres de los meses al español (solo para mostrar)
    $meses = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
        'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
        'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];

    // Formatear la hora actual y la hora límite para mostrar
    $dia_actual = $hora_actual->format('j');
    $mes_actual = $meses[$hora_actual->format('F')];
    $anio_actual = $hora_actual->format('Y');
    $hora_actual_str = "$dia_actual de $mes_actual de $anio_actual " . $hora_actual->format('H:i');
    $hora_limite_str = "$dia_actual de $mes_actual de $anio_actual " . $hora_limite->format('H:i');

    // Consulta para todas las reservas
    $reservas = get_posts([
        'post_type' => 'datos-reserva',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'restaurante',
                'value' => $restaurante_id,
                'compare' => '='
            ]
        ]
    ]);

    // Filtrar reservas atrasadas manualmente
    $reservas_atrasadas = [];
    if ($reservas && is_array($reservas)) {
        foreach ($reservas as $reserva) {
            if (is_object($reserva) && isset($reserva->ID)) {
                $fecha_reserva_str = get_field('fecha', $reserva->ID);
                if ($fecha_reserva_str) {
                    $fecha_reserva = parse_fecha_reserva($fecha_reserva_str, new DateTimeZone('Europe/Lisbon'));
                    if ($fecha_reserva && $fecha_reserva <= $hora_actual && $fecha_reserva >= $hora_limite) {
                        $reservas_atrasadas[] = $reserva;
                    }
                }
            }
        }
    }

    // Contenedor para reservas atrasadas
    $output = '<div style="max-height: 600px; overflow-y: auto; padding-right: 10px; ">';
    if ($reservas_atrasadas) {
        foreach ($reservas_atrasadas as $reserva) {
            $output .= '<div style="background-color: #ffccbc; color: #bf360c; border: 2px solid #bf360c; padding: 1rem; margin-bottom: 2rem; border-radius: 8px;">';
            $output .= '<p><strong>Nombre:</strong> ' . esc_html(get_field('nombre_cliente', $reserva->ID)) . '</p>';
            $output .= '<p><strong>Teléfono:</strong> ' . esc_html(get_field('telefono', $reserva->ID)) . '</p>';
            $output .= '<p><strong>Comensales:</strong> ' . esc_html(get_field('comensales', $reserva->ID)) . '</p>';
            $output .= '<p><strong>Fecha:</strong> ' . esc_html(get_field('fecha', $reserva->ID)) . '</p>';
            $output .= '</div>';
        }
    } else {
        $output .= '<p style="color: #b71c1c;"><strong>No hay reservas atrasadas.</strong></p>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('reservas_atrasadas', 'mostrar_reservas_atrasadas');

add_action('wp_ajax_actualizar_estado_restaurante', 'actualizar_estado_restaurante');
function actualizar_estado_restaurante() {
    check_ajax_referer('restaurantes_nonce', 'nonce');

    $post_id = intval($_POST['post_id']);
    $abierto = isset($_POST['abierto']) && $_POST['abierto'] == '1';

    $relacion = get_field('usuario_restaurante', $post_id, false);
    $current_user_id = get_current_user_id();

    if ((is_array($relacion) && !in_array($current_user_id, array_map('intval', $relacion)))
        || (!is_array($relacion) && intval($relacion) !== $current_user_id)) {
        wp_send_json_error('No tienes permiso para modificar este restaurante.');
    }

    update_field('restaurante_abierto', $abierto, $post_id);
    wp_send_json_success('Estado actualizado.');
}

add_shortcode('estado_restaurante_checkbox', function () {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return '<p>Debes iniciar sesión para cambiar el estado del restaurante.</p>';
    }
    // Buscar restaurante relacionado con el usuario actual a través del campo ACF
    $restaurante = get_posts([
        'post_type' => 'restaurante',
        'meta_query' => [
            [
                'key' => 'usuario_restaurante',
                'value' => $current_user_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    if (!$restaurante) {
        return '<p>No tienes restaurantes asignados.</p>';
    }

    $restaurante_id = $restaurante[0]->ID;
    $abierto = get_field('restaurante_abierto', $restaurante_id);

    ob_start(); ?>
    <style>
    .switch-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 1rem;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #ccc;
        transition: 0.4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #4CAF50;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    #estado-texto {
        font-weight: bold;
        color: #4CAF50;
    }
    #estado-texto.cerrado {
        color: red;
    }
    </style>

    <div class="switch-container">
        <label class="switch">
            <input type="checkbox" id="restaurante-abierto" <?php checked($abierto); ?> />
            <span class="slider"></span>
        </label>
        <span id="estado-texto" class="<?= $abierto ? '' : 'cerrado' ?>">
            <?= $abierto ? 'Abierto' : 'Cerrado' ?>
        </span>
    </div>

    <script>
    jQuery(document).ready(function ($) {
        function actualizarTextoEstado(checked) {
            $('#estado-texto')
                .text(checked ? 'Abierto' : 'Cerrado')
                .toggleClass('cerrado', !checked)
                .css('color', checked ? '#4CAF50' : 'red');
        }

        $('#restaurante-abierto').on('change', function () {
            const abierto = $(this).is(':checked') ? 1 : 0;
            actualizarTextoEstado(abierto);

            $.ajax({
                url: restaurantesAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'actualizar_estado_restaurante',
                    nonce: restaurantesAjax.nonce,
                    post_id: <?= $restaurante_id ?>,
                    abierto: abierto
                },
                success: function (res) {
                    if (res.success) {
                        console.log('Estado del restaurante actualizado correctamente!');
                    } else {
                        alert('Error: ' + res.data);
                    }
                },
                error: function () {
                    alert('Error de conexión');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});

add_filter( 'bricks/active_templates', function( $active_templates, $post_id, $content_type ) {
    // Verifica si el tipo de contenido es 'search' y si la plantilla activa es la de búsqueda de restaurantes
    if ( $content_type === 'search' && isset( $active_templates['search'] ) && $active_templates['search'] === '491' ) {
        // Aplica el filtro para mostrar solo restaurantes abiertos
        add_action( 'pre_get_posts', function( $query ) {
            if ( !is_admin() && $query->is_main_query() && $query->is_search() ) {
                $meta_query = $query->get( 'meta_query' ) ?: [];
                $meta_query[] = [
                    'key'     => 'restaurante_abierto',
                    'value'   => '1',
                    'compare' => '='
                ];
                $query->set( 'meta_query', $meta_query );
            }
        });
    }
    return $active_templates;
}, 10, 3 );
?>