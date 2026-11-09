<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Marcaciones</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- SweetAlert CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .form-container {
            margin-top: 50px;
        }
        .card {
            border-radius: 15px;
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
        }
        .btn-submit:hover {
            background-color: #218838;
        }
        .form-label {
            font-weight: bold;
        }
        /* Spinner oculto por defecto */
        .loader {
            display: none;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="container form-container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h3 class="text-center mb-4"><i class="fas fa-clock"></i> Subir Marcaciones</h3>

                        <!-- Formulario -->
                        <form id="marcacionesForm">
                            <div class="mb-3">
                                <label for="desde" class="form-label">Fecha de Inicio</label>
                                <input type="date" class="form-control" id="desde" name="desde" required>
                            </div>

                            <div class="mb-3">
                                <label for="hasta" class="form-label">Fecha de Fin</label>
                                <input type="date" class="form-control" id="hasta" name="hasta" required>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-submit btn-lg w-50">
                                    <i class="fas fa-upload"></i> Guardar y Subir Marcaciones
                                </button>
                            </div>
                        </form>
                        <br>
                        <form id="sincronizar">
                        <div class="text-center">
                            <div class="btn-group" role="group" aria-label="Basic example">
                                <button type="submit" class="btn btn-info btn-sm w-80">
                                    <i class="fas fa-upload"></i> Sincronizar Marcaciones a la Nube
                                </button>
                                <a href="{{route('eliminardata')}}" class="btn btn-danger" title="Eliminar Marcaciones"><i class="fas fa-trash"></i></a>
                            </div>
                            </div>
                        </form>
                        <!-- Loader (oculto por defecto) -->
                        <div id="loader" class="loader">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p>Cargando...</p>
                        </div>

                        <hr class="my-4">
                        <div class="text-center">
                            <p class="small text-muted mb-2"><i class="fas fa-fingerprint me-1"></i> Consultas Directas al Biométrico</p>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <a href="{{ route('version') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-microchip me-1"></i> Versión / Info
                                </a>
                                <a href="{{ route('biometrico.users') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-users me-1"></i> Usuarios
                                </a>
                                <a href="{{ route('biometrico.marcaciones') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-clock me-1"></i> Marcaciones
                                </a>
                                <a href="{{ route('biometrico.herramientas') }}" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-wrench me-1"></i> Editar Usuario / Exportar .DAT
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <script>
    // Obtener la fecha actual
    let fechaActual = new Date();
    
    // Formatear la fecha en el formato YYYY-MM-DD
    let año = fechaActual.getFullYear();
    let mes = String(fechaActual.getMonth() + 1).padStart(2, '0'); // Los meses empiezan desde 0
    let dia = String(fechaActual.getDate()).padStart(2, '0'); // Añadir 0 si el día es menor que 10
    
    // Establecer el valor del campo de fecha con la fecha actual
    document.getElementById('hasta').value = `${año}-${mes}-${dia}`;


        document.getElementById("marcacionesForm").addEventListener("submit", function(event) {
            event.preventDefault(); 
            document.getElementById("loader").style.display = "block";
            const desde = document.getElementById("desde").value;
            const hasta = document.getElementById("hasta").value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            if (desde && hasta && new Date(desde) <= new Date(hasta)) {
                const formData = {
                    desde: desde,
                    hasta: hasta
                };
                fetch('/guardar-marcaciones', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json()) // Suponiendo que la API retorna un JSON
                .then(data => {
                    // Ocultar el loader
                    document.getElementById("loader").style.display = "none";

                    // Aquí puedes manejar la respuesta de la API
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Marcaciones Subidas',
                            text: data.mensaje,
                            confirmButtonText: '¡Genial!'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Hubo un problema al subir las marcaciones.',
                            confirmButtonText: 'Intentar de nuevo'
                        });
                    }
                })
                .catch(error => {
                    // Ocultar el loader
                    document.getElementById("loader").style.display = "none";

                    // Manejo de errores de la solicitud
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un error al procesar tu solicitud. Por favor, intenta de nuevo.',
                        confirmButtonText: 'Intentar de nuevo'
                    });
                    console.error('Error en la solicitud:', error);
                });
            } else {
                // Ocultar el loader
                document.getElementById("loader").style.display = "none";

                // Mostrar alerta de error con SweetAlert
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Por favor, asegúrese de que las fechas sean correctas y el inicio no sea posterior al final.',
                    confirmButtonText: 'Intentar de nuevo'
                });
            }
        });
        document.getElementById("sincronizar").addEventListener("submit", function(event) {
            event.preventDefault(); // Prevenir el envío normal del formulario

            // Mostrar el loader
            document.getElementById("loader").style.display = "block";

            // Obtener las fechas ingresadas
            const desde = document.getElementById("desde").value;
            const hasta = document.getElementById("hasta").value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            // Verificar si las fechas son válidas
            if (desde && hasta && new Date(desde) <= new Date(hasta)) {
                // Crear objeto de datos a enviar
                const formData = {
                    desde: desde,
                    hasta: hasta
                };

                // Realizar solicitud POST con Fetch API
                fetch('/sincronizar-marcaciones', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json()) // Suponiendo que la API retorna un JSON
                .then(data => {
                    // Ocultar el loader
                    document.getElementById("loader").style.display = "none";

                    // Aquí puedes manejar la respuesta de la API
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Marcaciones Subidas',
                            text: data.mensaje,
                            confirmButtonText: '¡Genial!'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Hubo un problema al subir las marcaciones.',
                            confirmButtonText: 'Intentar de nuevo'
                        });
                    }
                })
                .catch(error => {
                    // Ocultar el loader
                    document.getElementById("loader").style.display = "none";

                    // Manejo de errores de la solicitud
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un error al procesar tu solicitud. Por favor, intenta de nuevo.',
                        confirmButtonText: 'Intentar de nuevo'
                    });
                    console.error('Error en la solicitud:', error);
                });
            } else {
                // Ocultar el loader
                document.getElementById("loader").style.display = "none";

                // Mostrar alerta de error con SweetAlert
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Por favor, asegúrese de que las fechas sean correctas y el inicio no sea posterior al final.',
                    confirmButtonText: 'Intentar de nuevo'
                });
            }
        });
    </script>

</body>
</html>
