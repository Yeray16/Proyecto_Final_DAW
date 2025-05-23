jQuery(document).ready(function ($) {
    $('#brxe-422c60').html('<div style="background-color: #a5d6a7; color: #1b5e20; border: 2px solid #1b5e20; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">Cargando restaurantes...</div>');
    var map;
    try {
        map = L.map('map').setView([0, 0], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
    } catch (e) {
        console.error('Error al inicializar Leaflet:', e);
        $('#map').html('<div class="acf-map-message">Error al cargar el mapa.</div>');
        return;
    }
     // Definir iconos personalizados
    var userIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    var markers = [];
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
            var lat = position.coords.latitude;
            var lon = position.coords.longitude;
            // Añadir marcador para la ubicación del usuario (rojo)
            var userMarker = L.marker([lat, lon], { icon: userIcon }).addTo(map)
                .bindPopup('Tu ubicación');
            markers.push(userMarker);
            $.ajax({
                url: restaurantesAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'obtener_restaurantes_cercanos',
                    nonce: restaurantesAjax.nonce,
                    lat: lat,
                    lon: lon,
                    distancia_maxima: 10,
                    mesas_disponibles: 1 
                },
                success: function (response) {
                    console.log('Respuesta AJAX:', response);
                    $('#brxe-422c60').empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function (i, restaurante) {
                            var distanciaTexto = restaurante.distancia < 1
                                ? (restaurante.distancia * 1000) + ' m'
                                : restaurante.distancia + ' km';
                            let horario = restaurante.horario;

                            const dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

                            // Generar HTML con formato y color para cada línea del horario
                            let horarioFormateado = '';
                            dias.forEach((dia, index) => {
                                const regex = new RegExp(dia + '\\s+(Cerrado|.+)', 'i');
                                const match = horario.match(regex);
                                if (match) {
                                    const contenido = match[1];
                                    const color = contenido.trim().toLowerCase() === 'cerrado' ? 'red' : 'green';
                                    horarioFormateado += (index === 0 ? '' : '<br>') + `<b>${dia} <span style="color:${color}">${contenido}</span></b>`;
                                }
                            });
                            var contenedor ='<div class="brxe-wiesus brxe-div shadow border-radius"><img src="' + restaurante.featured_image + '" class="brxe-spkznk brxe-image size_img css-filter" alt="' + $('<div/>').text(restaurante.titulo).html() + '" width="311.33" height="207.34" style="width: 311.33px; height: 207.34px; object-fit: cover;">' +
                                            '<h3>' + $('<div/>').text(restaurante.titulo).html() + '</h3>' +
                                            '<p><b>Distancia:</b> ' + distanciaTexto + '</p>' +
                                            '<p><b>Dirección:</b> ' + $('<div/>').text(restaurante.direccion).html() + '</p>' +
                                            '<p><b>Horario:<br></b> ' + horarioFormateado + '</p>' +
                                            '<p><b>Mesas disponibles:</b> ' + restaurante.mesas_disponibles + '</p>' +
                                            '<a class="brxe-segkew brxe-button bricks-button bricks-background-secondary" href="https://bookeat.local/reserva/" onclick="guardarRestauranteEnLocalStorage(' 
                                                + restaurante.id + ',' 
                                                + restaurante.latitud + ',' 
                                                + restaurante.longitud + ', \'' 
                                                + encodeURIComponent(restaurante.titulo) + '\')">Reservar</a></div>';
                            $('#brxe-422c60').append(contenedor);
                            
                            // Añadir marcador al mapa
                            if (restaurante.latitud && restaurante.longitud) {
                                console.log('Añadiendo marcador para:', restaurante.titulo, restaurante.latitud, restaurante.longitud);
                                var marker = L.marker([parseFloat(restaurante.latitud), parseFloat(restaurante.longitud)])
                                    .addTo(map)
                                    .bindPopup('<b>' + $('<div/>').text(restaurante.titulo).html() + '</b>');
                                markers.push(marker);
                            } else {
                                console.warn('Coordenadas inválidas para el restaurante:', restaurante.titulo);
                            }
                        });
                        // Centrar el mapa para mostrar todos los marcadores
                        if (markers.length > 0) {
                            var group = L.featureGroup(markers);
                            map.fitBounds(group.getBounds());
                            console.log('Mapa centrado con', markers.length, 'marcadores');
                        } else {
                            console.warn('No se añadieron marcadores al mapa');
                            $('#map').html('<div class="acf-map-message">No se han encontrado restaurantes cerca de tu ubicación.</div>');
                        }
                    } else if (response.success) {
                        $('#brxe-422c60').html('<p>No se encontraron restaurantes cercanos con mesas disponibles.</p>');
                        $('#map').html('<div class="acf-map-message">No se han encontrado restaurantes cerca de tu ubicación.</div>');
                    } else {
                        $('#brxe-422c60').html('<p>Error: ' + response.data + '</p>');
                        $('#map').html('<div class="acf-map-message">No se han encontrado restaurantes cerca de tu ubicación.</div>');
                    }
                },
                error: function () {
                    $('#brxe-422c60').html('<p>Error al cargar los restaurantes.</p>');
                    $('#map').html('<div class="acf-map-message">No se han encontrado restaurantes cerca de tu ubicación.</div>');
                }
            });
        }, function (error) {
            let message = 'Error desconocido.';
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    message = 'Por favor, habilita la geolocalización en tu navegador.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'No se pudo determinar tu ubicación.';
                    break;
                case error.TIMEOUT:
                    message = 'Tiempo de espera agotado al obtener tu ubicación.';
                    break;
            }
            $('#brxe-422c60').html('<p>' + message + '</p>');
            $('#map').html('<div class="acf-map-message">No se han encontrado restaurantes cerca de tu ubicación.</div>');
        }, {
            timeout: 10000,
            maximumAge: 60000,
            enableHighAccuracy: true
        });
    } else {
        $('#brxe-422c60').html('<p>Tu navegador no soporta geolocalización.</p>');
        $('#map').html('<div class="acf-map-message">No se han encontrado restaurantes cerca de tu ubicación.</div>');
    }
});

function guardarRestauranteEnLocalStorage(id, lat, lon, titulo) {
    localStorage.setItem('restaurante_id', id);
    localStorage.setItem('restaurante_lat', lat);
    localStorage.setItem('restaurante_lon', lon);
    localStorage.setItem('restaurante_titulo', decodeURIComponent(titulo));
}