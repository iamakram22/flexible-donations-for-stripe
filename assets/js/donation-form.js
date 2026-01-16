jQuery(document).ready(function ($) {
    // Handle predefined amount buttons
    $('.scd-amount-btn').on('click', function () {
        $('.scd-amount-btn').removeClass('active');
        $(this).addClass('active');
        $('#scd-custom-amount').val($(this).data('amount'));
    });

    // Handle custom amount input
    $('#scd-custom-amount').on('input', function () {
        $('.scd-amount-btn').removeClass('active');
    });

    // Show/hide interval selector
    $('input[name="payment_type"]').on('change', function () {
        if ($(this).val() === 'recurring') {
            $('.scd-interval').slideDown();
        } else {
            $('.scd-interval').slideUp();
        }
    });

    // Handle form submission [web:44]
    $('#scd-donation-form').on('submit', function (e) {
        e.preventDefault();

        var amount = $('#scd-custom-amount').val();
        var email = $('#scd-email').val();
        var paymentType = $('input[name="payment_type"]:checked').val();
        var interval = $('#scd-interval').val();

        if (!amount || amount < 1) {
            showError('Please enter a valid amount (minimum 1)');
            return;
        }

        if (!email) {
            showError('Please enter your email address');
            return;
        }

        // Disable submit button
        var $btn = $('.scd-submit-btn');
        $btn.prop('disabled', true).text('Processing...');

        // Make AJAX request
        $.ajax({
            url: scdAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'scd_create_checkout_session',
                nonce: scdAjax.nonce,
                amount: amount,
                email: email,
                payment_type: paymentType,
                interval: interval
            },
            success: function (response) {
                if (response.success) {
                    // Redirect to Stripe Checkout
                    window.location.href = response.data.url;
                } else {
                    showError(response.data.message);
                    $btn.prop('disabled', false).text('Proceed to Payment');
                }
            },
            error: function () {
                showError('An error occurred. Please try again.');
                $btn.prop('disabled', false).text('Proceed to Payment');
            }
        });
    });

    function showError(message) {
        $('.scd-error-message').text(message).slideDown();
        setTimeout(function () {
            $('.scd-error-message').slideUp();
        }, 5000);
    }
});