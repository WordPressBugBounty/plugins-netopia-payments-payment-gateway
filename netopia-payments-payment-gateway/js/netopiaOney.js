document.addEventListener('DOMContentLoaded', (event) => {
    const multiSelect = document.getElementById('woocommerce_netopiapayments_payment_methods');

    multiSelect.addEventListener('change', (event) => {
        const selectedOptions = Array.from(multiSelect.selectedOptions).map(option => option.value);
        
        const optionToMirror = 'oney';  // The option that triggers the mirror action
        const mirroredOption = 'credit_card';  // The option that gets mirrored
        
        if (selectedOptions.includes(optionToMirror)) {
            if (!selectedOptions.includes(mirroredOption)) {
                multiSelect.querySelector(`option[value="${mirroredOption}"]`).selected = true;
                toastr.success('Creadit card has been automatically selected because you selected Oney option.', 'success!');
            }
        }
    });
});