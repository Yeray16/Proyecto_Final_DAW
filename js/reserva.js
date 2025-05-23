jQuery(document).ready(function ($) {
    // Recuperamos la información del restaurante del localStorage
    const urlParams = new URLSearchParams(window.location.search);
    var id = urlParams.get('id') ?? parseFloat(localStorage.getItem('restaurante_id'));
    var lat = urlParams.get('lat') ?? parseFloat(localStorage.getItem('restaurante_lat'));
    var lon = urlParams.get('lon') ?? parseFloat(localStorage.getItem('restaurante_lon'));
    var titulo = nombre = urlParams.get('nombre') ?? localStorage.getItem('restaurante_titulo');
    console.log("holaaaa");
    console.log(lat)
    console.log(lon)
    console.log(titulo)
    guardarRestauranteEnLocalStorage(id, horario, lat, lon, titulo);
    if (lat && lon) {
        // Inicializar el mapa de Leaflet
        var map = L.map('mapa-reserva').setView([lat, lon], 16);

        // Añadir la capa de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Añadir un marcador en la ubicación del restaurante
        L.marker([lat, lon]).addTo(map)
            .bindPopup("<b>" + titulo + "</b>")
            .openPopup();
    } else {
        // Si no hay información en el localStorage, muestra un mensaje
        $('#mapa-reserva').html('<p>No se encontró información del restaurante.</p>');
    }
    $('#form-reserva').on('submit', function (e) {
        e.preventDefault();
        console.log('holaaaaaaaaaaa estoy aqui')
        var nombre = $('input[name="nombre"]').val();
        var telefono = $('input[name="telefono"]').val();
        var comensales = $('input[name="comensales"]').val();
        var fecha = $('input[name="fecha"]').val();
        var restaurante_id = localStorage.getItem('restaurante_id');
        console.log('Datos: '+ nombre+' '+telefono+' '+comensales+' '+fecha+' '+restaurante_id);

        $.ajax({
            url: reservasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'crear_reserva',
                nonce: reservasAjax.nonce,
                nombre: nombre,
                telefono: telefono,
                comensales: comensales,
                fecha: fecha,
                restaurante_id: restaurante_id
            },
            success: function (response) {
                console.log("Esto es response: ", response);
                if (response.success) {
                    alert('✅ Reserva enviada correctamente');
                    $('#form-reserva')[0].reset();
                } else {
                    alert('❌ Error al crear la reserva: ' + response.data);
                }
            },
            error: function () {
                alert('❌ Error en la solicitud AJAX');
            }
        });
    });
});

function guardarRestauranteEnLocalStorage(id, lat, lon, titulo) {
    console.log("Halo presidente:"+horario)
    console.log("Esto es id: "+id)
    console.log("Esto es lat:"+lat)
    console.log("Esto es lon:"+lon)
    console.log("Esto es título:"+titulo)
    localStorage.setItem('restaurante_id', id);
    localStorage.setItem('restaurante_lat', lat);
    localStorage.setItem('restaurante_lon', lon);
    localStorage.setItem('restaurante_titulo', decodeURIComponent(titulo));
}