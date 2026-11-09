<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Datos del Biométrico</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- SweetAlert CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Arial', sans-serif;
        }
        .form-container {
            margin-top: 50px;
        }
        .card {
            border-radius: 15px;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .form-label {
            font-weight: bold;
        }
        .loader {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="container form-container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h3 class="text-center mb-4"><i class="fas fa-trash-alt"></i> Eliminar Datos del Biométrico</h3>

                        <!-- Formulario -->
                        <form id="eliminarForm">
                            <div class="mb-3">
                                <label for="hasta" class="form-label">Eliminar datos hasta hoy</label>
                                <input type="date" class="form-control" id="hasta" name="hasta" readonly required>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-delete btn-lg w-50">
                                    <i class="fas fa-exclamation-triangle"></i> Eliminar Datos
                                </button>
                            </div>
                        </form>

                        <!-- Loader -->
                        <div id="loader" class="loader">
                            <div class="spinner-border text-danger" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p>Eliminando datos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Setear la fecha actual como valor por defecto
        document.addEventListener("DOMContentLoaded", () => {
            const hoy = new Date().toISOString().split("T")[0];
            document.getElementById("hasta").value = hoy;
        });

        document.getElementById("eliminarForm").addEventListener("submit", function (event) {
            event.preventDefault();

            const hasta = document.getElementById("hasta").value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            if (!hasta) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: 'Por favor, selecciona una fecha válida.',
                });
                return;
            }

            Swal.fire({
                title: '¿Estás seguro?',
                text: `Se eliminarán todos los datos del biométrico hasta el ${hasta}. Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById("loader").style.display = "block";

                    // Simulación de petición (reemplaza con tu endpoint real)
                    fetch('/eliminar-datos-biometrico', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ hasta: hasta })
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById("loader").style.display = "none";

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Datos eliminados',
                                text: data.mensaje || 'Los datos fueron eliminados correctamente.',
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Ocurrió un problema al eliminar los datos.',
                            });
                        }
                    })
                    .catch(error => {
                        document.getElementById("loader").style.display = "none";
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un problema en el servidor. Intenta de nuevo.',
                        });
                        console.error('Error al eliminar:', error);
                    });
                }
            });
        });
    </script>
</body>
</html>
