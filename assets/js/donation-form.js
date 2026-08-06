jQuery(document).ready(function ($) {
    // Handle predefined amount buttons
    $('.fdfs-amount-btn').on('click', function () {
        $('.fdfs-amount-btn').removeClass('active');
        $(this).addClass('active');
        $('#fdfs-custom-amount').val($(this).data('amount'));
    });

    // Handle custom amount input
    $('#fdfs-custom-amount').on('input', function () {
        $('.fdfs-amount-btn').removeClass('active');
    });

    // Show/hide interval selector
    $('input[name="payment_type"]').on('change', function () {
        if ($(this).val() === 'recurring') {
            $('.fdfs-interval').slideDown();
        } else {
            $('.fdfs-interval').slideUp();
        }
    });

    // Handle form submission [web:44]
    $('#fdfs-donation-form').on('submit', function (e) {
        e.preventDefault();

        var amount = $('#fdfs-custom-amount').val();
        var email = $('#fdfs-email').val();
        var paymentType = $('input[name="payment_type"]:checked').val();
        var interval = $('#fdfs-interval').val();
        var note = $('#fdfs-note').length ? $('#fdfs-note').val() : '';

        if (!amount || amount < 1) {
            showError('Please enter a valid amount (minimum 1)');
            return;
        }

        if (!email) {
            showError('Please enter your email address');
            return;
        }

        // Disable submit button
        var $btn = $('.fdfs-submit-btn');
        $btn.prop('disabled', true).text('Processing...');

        // Fetch a fresh nonce so cached pages (expired embedded nonce) still work;
        // fall back to the localized nonce if the endpoint is unreachable.
        var nonceRequest = $.get(fdfsAjax.nonce_url)
            .then(function (response) { return response.nonce; })
            .fail(function () { return fdfsAjax.nonce; });

        nonceRequest.done(function (nonce) {
            // Make AJAX request
            var requestUrl = fdfsAjax.method === 'rest' ? fdfsAjax.rest_url : fdfsAjax.ajax_url;

            $.ajax({
                url: requestUrl,
                type: 'POST',
                data: {
                    action: 'fdfs_create_checkout_session',
                    nonce: nonce,
                    amount: amount,
                    email: email,
                    payment_type: paymentType,
                    interval: interval,
                    note: note
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
    });

    function showError(message) {
        $('.fdfs-error-message').text(message).slideDown();
        setTimeout(function () {
            $('.fdfs-error-message').slideUp();
        }, 5000);
    }
});