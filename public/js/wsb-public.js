jQuery(document).ready(function($) {
    // Category Filtering Logic
    $('.wsb-filter-btn').on('click', function() {
        $('.wsb-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        var category = $(this).data('category');
        
        if (category === 'all') {
            $('.wsb-card-option').fadeIn(300);
        } else {
            $('.wsb-card-option').hide();
            $('.wsb-card-option').each(function() {
                var cardCat = $(this).find('.wsb-category-badge').text().trim();
                // Match category exactly
                if (cardCat === category) {
                    $(this).fadeIn(300);
                }
            });
        }
    });

    // Card Selection Logic for UI
    $(document).on('click', '.wsb-card-option, .wsb-staff-card', function() {
        // Remove selected class from siblings
        $(this).parent().find('.selected').removeClass('selected');
        // Add to clicked
        $(this).addClass('selected');
        
        // Enable the Next button in this step
        $(this).closest('.wsb-wizard-step').find('.wsb-next-btn').prop('disabled', false);
    });

    // Step Navigation
    $('.wsb-next-btn').on('click', function(e) {
        e.preventDefault();
        var nextStep = $(this).data('next');
        var currentStep = $(this).closest('.wsb-wizard-step');
        
        currentStep.css({opacity: 1}).animate({opacity: 0, marginTop: '-20px'}, 200, function() {
            currentStep.hide();
            $('#' + nextStep).css({opacity: 0, marginTop: '20px'}).show().animate({opacity: 1, marginTop: '0'}, 300);
        });
    });

    $('.wsb-prev-btn').on('click', function(e) {
        e.preventDefault();
        var prevStep = $(this).data('prev');
        var currentStep = $(this).closest('.wsb-wizard-step');
        
        currentStep.css({opacity: 1}).animate({opacity: 0, marginTop: '20px'}, 200, function() {
            currentStep.hide();
            $('#' + prevStep).css({opacity: 0, marginTop: '-20px'}).show().animate({opacity: 1, marginTop: '0'}, 300);
        });
    });

    // Time Slot loading
    $('#wsb-booking-date').on('change', function() {
        var dateVal = $(this).val();
        if(!dateVal) return;
        
        $('.wsb-time-slots').html('<p style="color: var(--wsb-pub-muted);">Loading available times...</p>');
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_get_slots',
                nonce: wsb_ajax.nonce,
                date: dateVal
            },
            success: function(response) {
                if (response.success) {
                    var slotsHtml = '';
                    response.data.slots.forEach(function(slot) {
                        slotsHtml += '<button class="wsb-slot-btn">' + slot + '</button> ';
                    });
                    $('.wsb-time-slots').html(slotsHtml);
                    
                    // Re-bind slot selection
                    $('.wsb-slot-btn').on('click', function(e) {
                        e.preventDefault();
                        $('.wsb-slot-btn').removeClass('selected');
                        $(this).addClass('selected');
                        $('#wsb-step-time').find('.wsb-next-btn').prop('disabled', false);
                    });
                }
            }
        });
    });

    // Final Booking Submission
    $('#wsb-confirm-booking').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        
        // Basic Validation
        if(!$('#wsb-first-name').val() || !$('#wsb-email').val()) {
            alert("Please fill your required details.");
            return;
        }

        btn.text('Processing...').prop('disabled', true);
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_create_booking',
                nonce: wsb_ajax.nonce,
                first_name: $('#wsb-first-name').val(),
                last_name: $('#wsb-last-name').val(),
                email: $('#wsb-email').val()
                // in real app, also pass selected service_id, staff_id, time slot.
            },
            success: function(response) {
                if (response.success) {
                    $('#wsb-step-payment').html(
                        '<div style="text-align:center; padding: 40px 0;">' +
                        '<span style="font-size:48px;">🎉</span>' +
                        '<h2 style="margin-top:20px; font-size:24px; color:var(--wsb-pub-brand);">Booking Confirmed!</h2>' +
                        '<p style="color:var(--wsb-pub-muted); margin-bottom:20px;">' + response.data.message + '</p>' +
                        '<button class="wsb-btn wsb-btn-primary" onclick="location.reload()">Book Another Service</button>' +
                        '</div>'
                    );
                }
            }
        });
    });
});
