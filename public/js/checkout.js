$(document).ready(function() {
    // Obtenemos las variables pasadas desde PHP
    const form = $('#checkout-form');
    const baseUrl = form.data('base-url');
    const credicardOrigin = form.data('credicard-origin');

    // Referencias a elementos del DOM
    const addressSection = $('#address-section');
    const newAddressForm = $('#new-address-form');
    const newAddressInputs = newAddressForm.find('textarea, input[type="text"]');
    
    // Lógica para sección de pago
    const finalAmountContainer = $('#final-amount-container');
    const calculationDetails = $('#calculation-details');
    const hiddenAmountInput = $('#monto_final_ves_hidden');
    const submitButton = $('#submit-button');
    
    $('input[name="metodo_entrega"]').on('change', function() {
        if (this.value === 'Envío por Courier') {
            addressSection.slideDown();
            $('input[name="address_option"]:checked').trigger('change');
        } else {
            addressSection.slideUp();
            newAddressInputs.prop('required', false);
        }
    });

    $('input[name="address_option"]').on('change', function() {
        if (this.value === 'nueva') {
            newAddressForm.slideDown();
            newAddressInputs.prop('required', true);
        } else {
            newAddressForm.slideUp();
            newAddressInputs.prop('required', false);
        }
    }).trigger('change');

    function calculateAmount(cardType) {
        finalAmountContainer.slideDown();
        calculationDetails.html('<p class="text-center">Calculando monto final...</p>');
        submitButton.prop('disabled', true).text('Calculando...');
        hiddenAmountInput.val(0);

        $.ajax({
            url: baseUrl + 'public/api/get_payment_amount.php',
            type: 'POST',
            data: { card_type: cardType },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const finalAmount = response.monto_final_ves;
                    const commission = response.desglose_comisiones[0].monto;
                    const rate = response.tasa_usada;
                    const usdtEquivalent = finalAmount / rate;
                    const detailsHtml = `
                        <small class="d-block text-muted">Tasa de cambio aplicada: 1 USDT = ${rate.toFixed(2)} VES</small><hr>
                        <div class="d-flex justify-content-between"><span>Subtotal:</span> <span>${response.monto_base_ves.toFixed(2)} VES</span></div>
                        <div class="d-flex justify-content-between"><small>Comisiones y Gastos:</small> <small>+ ${commission.toFixed(2)} VES</small></div><hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="h5">Total a Pagar:</strong>
                            <strong class="h5 text-primary">${finalAmount.toFixed(2)} VES</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center text-muted">
                            <small>Equivalente en USDT:</small>
                            <small>${usdtEquivalent.toFixed(2)} USDT</small>
                        </div>`;
                    
                    calculationDetails.html(detailsHtml);
                    hiddenAmountInput.val(finalAmount);
                    submitButton.prop('disabled', false).text(`Continuar a Pagar ${finalAmount.toFixed(2)} VES`);
                } else {
                    calculationDetails.html(`<p class="text-danger text-center">Error: ${response.message}</p>`);
                    submitButton.prop('disabled', true).text('Error en cálculo');
                }
            },
            error: function() {
                calculationDetails.html('<p class="text-danger text-center">Error de red. No se pudo calcular el monto.</p>');
                submitButton.prop('disabled', true).text('Error de red');
            }
        });
    }

    $('input[name="payment_method"]').on('change', function() {
        const selectedMethod = this.value;
        if (selectedMethod === 'credicard_debito') {
            calculateAmount('debito');
        } else if (selectedMethod === 'credicard_credito') {
            calculateAmount('credito');
        } else {
            finalAmountContainer.slideUp();
            hiddenAmountInput.val(0);
            submitButton.prop('disabled', false).text('Realizar Pedido');
        }
    });

    form.on('submit', function(event) {
        event.preventDefault();
        submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Procesando...');

        const metodoEntrega = $('input[name="metodo_entrega"]:checked').val();
        const addressOption = $('input[name="address_option"]:checked').val();

        if (metodoEntrega === 'Envío por Courier' && addressOption === 'nueva') {
            let formValido = true;
            newAddressInputs.each(function() {
                if ($(this).val().trim() === '') {
                    formValido = false;
                }
            });

            if (!formValido) {
                Swal.fire('Campos requeridos', 'Por favor, completa todos los campos de la nueva dirección de envío.', 'warning');
                submitButton.prop('disabled', false).text('Realizar Pedido');
                return;
            }
        }

        $.ajax({
            url: baseUrl + 'app/checkout_process.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.paymentUrl) {
                        handlePopUpOpen(response.paymentUrl, response.paymentId);
                    } else if (response.redirectUrl) {
                        window.location.href = response.redirectUrl;
                    }
                } else {
                    Swal.fire('Error', response.message, 'error');
                    submitButton.prop('disabled', false).text('Realizar Pedido');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo contactar al servidor. Intenta de nuevo.', 'error');
                submitButton.prop('disabled', false).text('Realizar Pedido');
            }
        });
    });

    function handlePopUpOpen(paymentUrl, paymentId) {
        const width = 600, height = 800;
        const left = (window.screen.width / 2) - (width / 2);
        const top = (window.screen.height / 2) - (height / 2);
        const popup = window.open(paymentUrl, "CredicardPayment", `width=${width},height=${height},top=${top},left=${left}`);
        
        let messageHandled = false;

        const messageHandler = function(event) {
            console.log('Mensaje recibido desde el pop-up:', event.data);

            if (event.origin !== credicardOrigin) {
                console.warn("Mensaje recibido de un origen no autorizado:", event.origin);
                return;
            }

            const status = event.data?.status;
            if (status === "payment-success-cc" || status === "payment-fail-cc") {
                messageHandled = true;
                popup.close();
                window.removeEventListener('message', messageHandler);
                const redirectUrl = `${baseUrl}public/payment_status.php?payment_id=${paymentId}`;
                console.log('Redirigiendo a:', redirectUrl);
                window.location.href = redirectUrl;

            } else if (status === "payment-cancelled-cc") {
                messageHandled = true;
                popup.close();
                window.removeEventListener('message', messageHandler);
                Swal.fire('Operación Cancelada', 'El proceso de pago fue cancelado.', 'info');
                submitButton.prop('disabled', false).text('Realizar Pedido');
            }
        };

        window.addEventListener("message", messageHandler);

        const popupChecker = setInterval(function() {
            if (popup.closed) {
                clearInterval(popupChecker);
                window.removeEventListener('message', messageHandler);

                if (!messageHandled) {
                    // Llamamos al servidor para una última verificación del estado del pago
                    $.ajax({
                        url: `${baseUrl}public/api/check_payment_status.php`,
                        type: 'POST',
                        data: { paymentId: paymentId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.status === 'PAID') {
                                // Caso borde: el pago sí se procesó. Redirigimos a la página de éxito.
                                window.location.href = `${baseUrl}public/payment_status.php?payment_id=${paymentId}`;
                            } else {
                                // El pago no se procesó, mostramos el mensaje de sesión finalizada.
                                Swal.fire('Sesión Finalizada', 'La sesión de pago expiró o fue cerrada antes de completar la operación.', 'warning');
                                submitButton.prop('disabled', false).text('Realizar Pedido');
                            }
                        },
                        error: function() {
                            // Si falla la verificación, mostramos un error genérico.
                            Swal.fire('Error de Verificación', 'No se pudo verificar el estado final del pago. Por favor, revisa tus pedidos.', 'error');
                            submitButton.prop('disabled', false).text('Realizar Pedido');
                        }
                    });
                }
            }
        }, 1000);
    }
});