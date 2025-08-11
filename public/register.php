<?php
session_start();
$page_title = 'Crear una Cuenta';
require_once __DIR__ . '/../config/config.php';
require_once '../templates/public/header_public.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Crear Cuenta</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" id="error-container">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
                <?php endif; ?>
                <div id="api-error" class="alert alert-danger" style="display:none;"></div>

                <div id="step-country">
                    <label for="country" class="form-label">¿De qué país eres?</label>
                    <select class="form-select" id="country">
                        <option value="">Selecciona tu país...</option>
                        <option value="VE">Venezuela</option>
                        <option value="OT">Otro</option>
                    </select>
                </div>

                <form action="<?php echo BASE_URL; ?>app/auth/client_process.php" method="POST" id="registration-form" class="mt-3" style="display:none;">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="country_code" id="country_code_hidden">
                    <input type="hidden" name="fecha_nacimiento" id="fecha_nacimiento_input">
                    
                    <div id="venezuelan-fields" style="display:none;">
                        <label for="cedula" class="form-label">Cédula de Identidad</label>
                        <div class="input-group">
                            <select class="form-select" id="nacionalidad" style="max-width: 80px;">
                                <option value="V">V</option>
                                <option value="E">E</option>
                            </select>
                            <input type="text" class="form-control" name="cedula" id="cedula" placeholder="Ej: 19512990">
                            <button class="btn btn-outline-secondary" type="button" id="validate-cedula-btn">Validar</button>
                        </div>
                        <div id="validator-spinner" class="form-text" style="display:none;">Validando...</div>
                    </div>

                    <div id="manual-venezuelan-fields" class="mt-3 p-3 bg-light border rounded" style="display:none;">
                        <p>No pudimos validar esta cédula automáticamente. ¿Deseas registrar tus datos manualmente?</p>
                        <button type="button" class="btn btn-info" id="manual-reg-btn">Sí, registrar manualmente</button>
                    </div>

                    <div id="common-fields" class="mt-3" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required style="text-transform:uppercase;">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" class="form-control" name="apellido" id="apellido" required style="text-transform:uppercase;">
                            </div>
                        </div>

                        <div id="dob-field-manual" class="mb-3" style="display:none;">
                             <label for="fecha_nacimiento_manual" class="form-label">Fecha de Nacimiento</label>
                             <input type="date" class="form-control" id="fecha_nacimiento_manual">
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Número de Teléfono con Whatsapp</label>
                            <div class="input-group">
                                <span class="input-group-text" id="phone-prefix">+</span>
                                <input type="tel" class="form-control" name="telefono" id="telefono" required placeholder="4121234567">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Completar Registro</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p>¿Ya tienes una cuenta? <a href="login.php">Inicia Sesión</a></p>
            </div>
        </div>
    </div>
</div>

<script>
// El JavaScript no necesita cambios para esta funcionalidad, pero lo incluimos completo.
document.addEventListener('DOMContentLoaded', function() {
    const countrySelect = document.getElementById('country');
    const registrationForm = document.getElementById('registration-form');
    const countryCodeHidden = document.getElementById('country_code_hidden');
    const phonePrefix = document.getElementById('phone-prefix');
    const fechaNacimientoInput = document.getElementById('fecha_nacimiento_input');
    const dobFieldManual = document.getElementById('dob-field-manual');
    const dobFieldInputManual = document.getElementById('fecha_nacimiento_manual');
    const venezuelanFields = document.getElementById('venezuelan-fields');
    const manualVenezuelanFields = document.getElementById('manual-venezuelan-fields');
    const manualRegBtn = document.getElementById('manual-reg-btn');
    const commonFields = document.getElementById('common-fields');
    const validateBtn = document.getElementById('validate-cedula-btn');
    const cedulaInput = document.getElementById('cedula');
    const nombreInput = document.getElementById('nombre');
    const apellidoInput = document.getElementById('apellido');
    const spinner = document.getElementById('validator-spinner');
    const apiError = document.getElementById('api-error');

    function resetForm() {
        apiError.style.display = 'none';
        venezuelanFields.style.display = 'none';
        manualVenezuelanFields.style.display = 'none';
        commonFields.style.display = 'none';
        dobFieldManual.style.display = 'none';
        dobFieldInputManual.required = false;
        nombreInput.readOnly = false;
        apellidoInput.readOnly = false;
        registrationForm.reset();
    }

    countrySelect.addEventListener('change', function() {
        registrationForm.style.display = 'block';
        resetForm();
        countryCodeHidden.value = this.value;

        if (this.value === 'VE') {
            phonePrefix.textContent = '+58';
            venezuelanFields.style.display = 'block';
        } else if (this.value === 'OT') {
            phonePrefix.textContent = '+';
            commonFields.style.display = 'block';
            dobFieldManual.style.display = 'block';
            dobFieldInputManual.required = true;
            dobFieldInputManual.addEventListener('change', () => {
                fechaNacimientoInput.value = dobFieldInputManual.value;
            });
        } else {
            registrationForm.style.display = 'none';
        }
    });

    validateBtn.addEventListener('click', function() {
        const cedula = cedulaInput.value.trim();
        const nacionalidad = document.getElementById('nacionalidad').value;
        if (!cedula || !/^\d+$/.test(cedula)) {
            alert('Por favor, introduce un número de cédula válido.');
            return;
        }
        spinner.style.display = 'block';
        apiError.style.display = 'none';
        validateBtn.disabled = true;

        fetch(`api/validate_cedula.php?nacionalidad=${nacionalidad}&cedula=${cedula}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    nombreInput.value = data.nombre;
                    apellidoInput.value = data.apellido;
                    if (data.fecha_nacimiento) {
                        fechaNacimientoInput.value = data.fecha_nacimiento;
                    }
                    nombreInput.readOnly = true;
                    apellidoInput.readOnly = true;
                    dobFieldManual.style.display = 'none';
                    dobFieldInputManual.required = false;
                    commonFields.style.display = 'block';
                    manualVenezuelanFields.style.display = 'none';
                } else {
                    const message = data.message || '';
                    if (message === 'RECORD_NOT_FOUND' || message.toLowerCase().includes('no encontrada')) {
                        manualVenezuelanFields.style.display = 'block';
                    } else {
                        apiError.textContent = message || 'La cédula no es válida.';
                        apiError.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                apiError.textContent = 'Ocurrió un error al contactar el servicio de validación.';
                apiError.style.display = 'block';
            })
            .finally(() => {
                spinner.style.display = 'none';
                validateBtn.disabled = false;
            });
    });

    manualRegBtn.addEventListener('click', function() {
        dobFieldManual.style.display = 'block';
        dobFieldInputManual.required = true;
        dobFieldInputManual.addEventListener('change', () => {
            fechaNacimientoInput.value = dobFieldInputManual.value;
        });
        commonFields.style.display = 'block';
        nombreInput.value = ''; 
        apellidoInput.value = '';
        nombreInput.readOnly = false;
        apellidoInput.readOnly = false;
        manualVenezuelanFields.style.display = 'none';
    });
});
</script>

<?php require_once '../templates/public/footer_public.php'; ?>