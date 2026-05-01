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
        $(this).closest('.wsb-wizard-step').find('.wsb-card-option, .wsb-staff-card').removeClass('selected');
        $(this).addClass('selected');
        
        // Enable the Next button in this step
        $(this).closest('.wsb-wizard-step').find('.wsb-next-btn').prop('disabled', false);

        // Refresh slots if staff is changed and date is already picked
        if ($(this).hasClass('wsb-staff-card') && $('#wsb-booking-date').val()) {
            $('#wsb-booking-date').trigger('change');
        }
    });

    // Step Navigation
    $(document).on('click', '.wsb-next-btn', function(e) {
        e.preventDefault();
        var nextStep = $(this).data('next');
        var currentStep = $(this).closest('.wsb-wizard-step');
        
        if (currentStep.attr('id') === 'wsb-step-details') {
            let hasError = false;
            $('.wsb-error-msg').hide().text('');
            $('.wsb-input-error').removeClass('wsb-input-error');

            const firstName = $('#wsb-first-name');
            if (!firstName.val().trim()) {
                firstName.addClass('wsb-input-error');
                $('#wsb-error-first-name').text('First Name is required').fadeIn(200);
                hasError = true;
            }

            const lastName = $('#wsb-last-name');
            if (!lastName.val().trim()) {
                lastName.addClass('wsb-input-error');
                $('#wsb-error-last-name').text('Last Name is required').fadeIn(200);
                hasError = true;
            }

            const email = $('#wsb-email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.val().trim()) {
                email.addClass('wsb-input-error');
                $('#wsb-error-email').text('Email Address is required').fadeIn(200);
                hasError = true;
            } else if (!emailRegex.test(email.val().trim())) {
                email.addClass('wsb-input-error');
                $('#wsb-error-email').text('Please enter a valid email address').fadeIn(200);
                hasError = true;
            }

            const phone = $('#wsb-phone');
            if (!phone.val().trim()) {
                phone.addClass('wsb-input-error');
                $('#wsb-error-phone').text('Phone Number is required').fadeIn(200);
                hasError = true;
            }

            if (hasError) {
                return;
            }
        }

        console.log('WSB: Navigating to:', nextStep);

        // Transition to next step
        currentStep.css({opacity: 1}).animate({opacity: 0, marginTop: '-20px'}, 200, function() {
            currentStep.hide();
            const $next = $('#' + nextStep);
            $next.css({opacity: 0, marginTop: '20px'}).show().animate({opacity: 1, marginTop: '0'}, 300);
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

    // Custom Interactive Calendar Generator
    let currentDate = new Date();
    let selectedDate = null;

    function renderCalendar() {
        const monthYearTitle = $('#wsb-current-month-year');
        const daysGrid = $('#wsb-calendar-days');
        
        if(!monthYearTitle.length) return; 
        
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        monthYearTitle.text(monthNames[month] + " " + year);
        
        daysGrid.empty();
        
        const firstDayIndex = new Date(year, month, 1).getDay();
        const lastDay = new Date(year, month + 1, 0).getDate();
        
        // Previous month padding days
        for (let i = 0; i < firstDayIndex; i++) {
            daysGrid.append('<div class="wsb-cal-day disabled"></div>');
        }
        
        const today = new Date();
        today.setHours(0,0,0,0);
        
        // Current month days
        for (let day = 1; day <= lastDay; day++) {
            const cellDate = new Date(year, month, day);
            const dateString = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            
            let classes = 'wsb-cal-day';
            if (cellDate < today) {
                classes += ' disabled';
            }
            if (selectedDate === dateString) {
                classes += ' selected';
            }
            
            const dayCell = $('<div class="' + classes + '" data-date="' + dateString + '">' + day + '</div>');
            
            if (cellDate >= today) {
                dayCell.on('click', function() {
                    $('.wsb-cal-day').removeClass('selected');
                    $(this).addClass('selected');
                    selectedDate = $(this).data('date');
                    $('#wsb-booking-date').val(selectedDate).trigger('change');
                });
            }
            
            daysGrid.append(dayCell);
        }
    }

    $('#wsb-prev-month').on('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    $('#wsb-next-month').on('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    // Trigger calendar render when arriving at the time step
    $(document).on('click', '.wsb-next-btn', function() {
        setTimeout(renderCalendar, 350);
    });

    // Time Slot loading
    $('#wsb-booking-date').on('change', function() {
        var dateVal = $(this).val();
        if(!dateVal) return;
        
        $('.wsb-time-picker-section').slideDown(300);
        $('.wsb-time-slots').html('<p style="color: var(--wsb-pub-muted); text-align:center; width:100%;">Loading available times...</p>');
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_get_slots',
                nonce: wsb_ajax.nonce,
                date: dateVal,
                staff_id: $('.wsb-staff-card.selected').data('staff-id') || 'any'
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
        let hasError = false;
        $('.wsb-error-msg').hide().text('');
        $('.wsb-input-error').removeClass('wsb-input-error');

        const firstName = $('#wsb-first-name');
        if (!firstName.val().trim()) {
            firstName.addClass('wsb-input-error');
            $('#wsb-error-first-name').text('First Name is required').fadeIn(200);
            hasError = true;
        }

        const lastName = $('#wsb-last-name');
        if (!lastName.val().trim()) {
            lastName.addClass('wsb-input-error');
            $('#wsb-error-last-name').text('Last Name is required').fadeIn(200);
            hasError = true;
        }

        const email = $('#wsb-email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email.val().trim()) {
            email.addClass('wsb-input-error');
            $('#wsb-error-email').text('Email Address is required').fadeIn(200);
            hasError = true;
        } else if (!emailRegex.test(email.val().trim())) {
            email.addClass('wsb-input-error');
            $('#wsb-error-email').text('Please enter a valid email address').fadeIn(200);
            hasError = true;
        }

        const phone = $('#wsb-phone');
        if (!phone.val().trim()) {
            phone.addClass('wsb-input-error');
            $('#wsb-error-phone').text('Phone Number is required').fadeIn(200);
            hasError = true;
        }

        if (hasError) {
            return;
        }

        btn.text('Processing...').prop('disabled', true);
        
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        const serviceId = $('.wsb-card-option.selected').data('service-id');
        const staffId = $('.wsb-staff-card.selected').data('staff-id');
        const bookingDate = $('#wsb-booking-date').val();
        const bookingTime = $('.wsb-slot-btn.selected').text();

        function processStandardBooking() {
            $.ajax({
                url: wsb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsb_create_booking',
                    nonce: wsb_ajax.nonce,
                    first_name: $('#wsb-first-name').val(),
                    last_name: $('#wsb-last-name').val(),
                    email: $('#wsb-email').val(),
                    phone: $('#wsb-phone-code').val() + $('#wsb-phone').val(),
                    notes: $('#wsb-notes').val(),
                    service_id: serviceId,
                    staff_id: staffId,
                    booking_date: bookingDate,
                    start_time: bookingTime,
                    payment_method: paymentMethod
                },
                success: function(response) {
                    if (response.success) {
                        $('#wsb-step-payment').html(
                            '<div class="wsb-success-card" style="text-align:center; padding: 40px 20px; background:#fff; border-radius:20px; border:1.5px solid var(--wsb-border); box-shadow:var(--wsb-shadow-sm); max-width:550px; margin:20px auto;">' +
                            '<div style="width: 80px; height: 80px; background:rgba(16, 185, 129, 0.1); color:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:40px; margin:0 auto 25px;">✔</div>' +
                            '<h2 style="margin:0 0 10px; font-size:28px; color:var(--wsb-text-main); font-weight:800;">Booking Confirmed!</h2>' +
                            '<p style="color:var(--wsb-text-muted); font-size:16px; margin-bottom:30px; line-height:1.6;">' + response.data.message + '</p>' +
                            '<div style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap;">' +
                            '<a href="' + (wsb_ajax.dashboard_url || '/booking-dashboard') + '" class="wsb-btn wsb-next-btn" style="text-decoration:none; display:inline-block;">View My Bookings</a>' +
                            '<button class="wsb-btn wsb-prev-btn" onclick="location.reload()">Book Another</button>' +
                            '</div>' +
                            '</div>'
                        );
                    } else {
                        alert(response.data.message || 'Checkout failed.');
                        btn.text('Confirm Booking').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Server communication error.');
                    btn.text('Confirm Booking').prop('disabled', false);
                }
            });
        }

        if (paymentMethod === 'stripe_card') {
            console.log('WSB: Initializing Stripe Checkout redirect...');
            $.ajax({
                url: wsb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsb_create_checkout_session',
                    nonce: wsb_ajax.nonce,
                    service_id: serviceId,
                    staff_id: staffId,
                    booking_date: bookingDate,
                    start_time: bookingTime,
                    first_name: $('#wsb-first-name').val(),
                    last_name: $('#wsb-last-name').val(),
                    email: $('#wsb-email').val(),
                    phone: $('#wsb-phone').val()
                },
                success: function(response) {
                    if (response.success && response.data.url) {
                        window.location.href = response.data.url;
                    } else {
                        alert(response.data.message || 'Could not initialize checkout.');
                        btn.text('Confirm Booking').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    btn.text('Confirm Booking').prop('disabled', false);
                }
            });
        } else {
            processStandardBooking();
        }
    });
    $(document).on('click', '.wsb-client-action-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const action = btn.data('action');
        const bookingId = btn.data('id');
        
        if (action === 'cancel') {
            $('#wsb-cancel-id').val(bookingId);
            $('#wsb-cancel-title-id').text('#' + bookingId);
            $('#wsb-cancel-msg').hide();
            $('#wsb-cancel-modal').css('display', 'flex').fadeIn(200);
        } else {
            $('#wsb-reschedule-id').val(bookingId);
            $('#wsb-reschedule-msg').hide();
            $('.wsb-reschedule-slots').html('');
            $('#wsb-reschedule-time-input').val('');
            $('#wsb-reschedule-staff').val('');
            $('#wsb-reschedule-date').val('');
            $('#wsb-reschedule-slots-container').hide();
            $('#wsb-reschedule-modal').css('display', 'flex').fadeIn(200);
        }
    });
    // Client Dashboard Tabs Switcher
    $(document).on('click', '.wsb-dash-tab', function(e) {
        e.preventDefault();
        $('.wsb-dash-tab').css({'border-bottom': '3px solid transparent', 'color': 'var(--wsb-text-muted)'});
        $(this).css({'border-bottom': '3px solid var(--wsb-brand)', 'color': 'var(--wsb-brand)'});
        
        $('.wsb-dash-content-panel').hide();
        $('#' + $(this).data('target')).fadeIn(200);
    });

    // Client Account Form Submission
    $(document).on('submit', '#wsb-client-account-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msgBox = $('#wsb-account-msg');
        
        btn.prop('disabled', true).text('Saving...');
        msgBox.hide().removeClass('wsb-success wsb-error');
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=wsb_update_account_details&nonce=' + wsb_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    msgBox.css('color', '#10b981').text(response.data.message).fadeIn(200);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    msgBox.css('color', '#ef4444').text(response.data.message).fadeIn(200);
                    btn.prop('disabled', false).text('Save Changes');
                }
            },
            error: function() {
                msgBox.css('color', '#ef4444').text('Connection error. Please try again.').fadeIn(200);
                btn.prop('disabled', false).text('Save Changes');
            }
        });
    });
    // Booking Details Modal Logic
    $(document).on('click', '.wsb-booking-row', function(e) {
        if ($(e.target).closest('.wsb-client-action-btn').length) return;
        
        const row = $(this);
        $('#wsb-modal-id').text('#' + row.data('id'));
        $('#wsb-modal-service').text(row.data('service'));
        $('#wsb-modal-staff').text(row.data('staff'));
        $('#wsb-modal-datetime').text(row.data('date') + ' @ ' + row.data('time'));
        $('#wsb-modal-amount').text(row.data('amount'));
        
        const status = row.data('status').toLowerCase();
        const statusEl = $('#wsb-modal-status');
        statusEl.text(status);
        
        if (status === 'confirmed' || status === 'completed') {
            statusEl.css({'background': 'rgba(16, 185, 129, 0.1)', 'color': '#10b981'});
        } else if (status === 'cancelled') {
            statusEl.css({'background': 'rgba(239, 68, 68, 0.1)', 'color': '#ef4444'});
        } else {
            statusEl.css({'background': 'rgba(245, 158, 11, 0.1)', 'color': '#f59e0b'});
        }
        
        $('#wsb-details-modal').css('display', 'flex').fadeIn(200);
    });

    $(document).on('click', '.wsb-modal-close', function() {
        $('#wsb-details-modal').fadeOut(200);
    });

    $(document).on('click', '#wsb-details-modal', function(e) {
        if ($(e.target).is('#wsb-details-modal')) {
            $(this).fadeOut(200);
        }
    });
    // Reschedule Modal Dismiss
    $(document).on('click', '.wsb-reschedule-close', function() {
        $('#wsb-reschedule-modal').fadeOut(200);
    });

    $(document).on('click', '#wsb-reschedule-modal', function(e) {
        if ($(e.target).is('#wsb-reschedule-modal')) {
            $(this).fadeOut(200);
        }
    });

    // Slot fetching for Reschedule
    $(document).on('change', '#wsb-reschedule-staff, #wsb-reschedule-date', function() {
        const staffId = $('#wsb-reschedule-staff').val();
        const date = $('#wsb-reschedule-date').val();
        const bookingId = $('#wsb-reschedule-id').val();
        
        if (!staffId || !date) {
            $('#wsb-reschedule-slots-container').hide();
            return;
        }
        $('#wsb-reschedule-slots-container').fadeIn(200);
        
        $('.wsb-reschedule-slots').html('<div style="grid-column:1/-1; text-align:center; color:var(--wsb-text-muted);">Loading slots...</div>');
        $('#wsb-reschedule-time-input').val('');
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_get_slots',
                nonce: wsb_ajax.nonce,
                staff_id: staffId,
                date: date,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success && response.data.slots && response.data.slots.length > 0) {
                    let slotsHtml = '';
                    response.data.slots.forEach(function(slot) {
                        slotsHtml += '<button type="button" class="wsb-reschedule-slot-btn" data-time="' + slot + '" style="padding:10px; border:1.5px solid var(--wsb-border); border-radius:10px; background:#fff; font-weight:700; cursor:pointer; text-align:center; transition:all 0.2s;">' + slot + '</button>';
                    });
                    $('.wsb-reschedule-slots').html(slotsHtml);
                    
                    // Bind click selection
                    $('.wsb-reschedule-slot-btn').on('click', function(e) {
                        e.preventDefault();
                        $('.wsb-reschedule-slot-btn').css({'background': '#fff', 'color': 'var(--wsb-text-main)', 'border-color': 'var(--wsb-border)'});
                        $(this).css({'background': 'var(--wsb-brand)', 'color': '#fff', 'border-color': 'var(--wsb-brand)'});
                        $('#wsb-reschedule-time-input').val($(this).data('time'));
                        $('#wsb-reschedule-time-error').hide();
                    });
                } else {
                    $('.wsb-reschedule-slots').html('<div style="grid-column:1/-1; text-align:center; color:#ef4444; font-weight:600;">No slots available for this date.</div>');
                }
            },
            error: function() {
                $('.wsb-reschedule-slots').html('<div style="grid-column:1/-1; text-align:center; color:#ef4444;">Error loading slots.</div>');
            }
        });
    });

    // Reschedule Submission
    $(document).on('submit', '#wsb-reschedule-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msgBox = $('#wsb-reschedule-msg');
        const timeInput = $('#wsb-reschedule-time-input').val();
        
        if (!timeInput) {
            $('#wsb-reschedule-time-error').fadeIn(200);
            return;
        }
        
        btn.prop('disabled', true).text('Processing...');
        msgBox.hide().removeClass('wsb-success wsb-error');
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=wsb_client_booking_action&client_action=reschedule&nonce=' + wsb_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    msgBox.css({'color': '#10b981', 'display': 'block'}).text(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    msgBox.css({'color': '#ef4444', 'display': 'block'}).text(response.data.message);
                    btn.prop('disabled', false).text('Request Reschedule');
                }
            },
            error: function() {
                msgBox.css({'color': '#ef4444', 'display': 'block'}).text('Connection error. Please try again.');
                btn.prop('disabled', false).text('Request Reschedule');
            }
        });
    });
    // Cancel Modal Listeners
    $(document).on('click', '.wsb-cancel-close', function() {
        $('#wsb-cancel-modal').fadeOut(200);
    });

    $(document).on('click', '#wsb-cancel-modal', function(e) {
        if ($(e.target).is('#wsb-cancel-modal')) {
            $(this).fadeOut(200);
        }
    });

    $(document).on('submit', '#wsb-cancel-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msgBox = $('#wsb-cancel-msg');
        const bookingId = $('#wsb-cancel-id').val();
        
        btn.prop('disabled', true).text('Processing...');
        msgBox.hide();
        
        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_client_booking_action',
                nonce: wsb_ajax.nonce,
                booking_id: bookingId,
                client_action: 'cancel'
            },
            success: function(response) {
                if (response.success) {
                    msgBox.css({'color': '#10b981', 'display': 'block'}).text(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    msgBox.css({'color': '#ef4444', 'display': 'block'}).text(response.data.message);
                    btn.prop('disabled', false).text('Request Cancellation');
                }
            },
            error: function() {
                msgBox.css({'color': '#ef4444', 'display': 'block'}).text('Connection error. Please try again.');
                btn.prop('disabled', false).text('Request Cancellation');
            }
        });
    });
    // Stripe payment elements initialization
    let stripeInstance = null;
    let stripeElements = null;
    let paymentElement = null;
    let isStripeLoading = false;

    $(document).on('click', '.wsb-payment-tab', function() {
        $('.wsb-payment-tab').css({'border': '1px solid #e2e8f0', 'background': '#ffffff'});
        $(this).css({'border': '2px solid #2563eb', 'background': '#f8fafc'});
        
        const method = $(this).data('method');
        $(`input[name="payment_method"][value="${method}"]`).prop('checked', true).trigger('change');
    });

    $(document).on('click', '#wsb-complete-checkout-btn', function(e) {
        e.preventDefault();
        $('#wsb-confirm-booking').trigger('click');
    });

    function initializeStripe() {
        if (stripeInstance || isStripeLoading) return;
        isStripeLoading = true;

        const serviceId = $('.wsb-card-option.selected').data('service-id');
        if (!serviceId) {
            $('#wsb-stripe-error').text('Please go back and select a Service first.').show();
            return;
        }

        if (!wsb_ajax.stripe_pk) {
            $('#wsb-stripe-error').text('Configuration Error: Stripe Publishable Key is missing in Settings.').show();
            return;
        }

        stripeInstance = Stripe(wsb_ajax.stripe_pk);

        $.ajax({
            url: wsb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_create_stripe_intent',
                nonce: wsb_ajax.nonce,
                service_id: serviceId
            },
            success: function(response) {
                if (response.success && response.data.client_secret) {
                    const options = {
                        clientSecret: response.data.client_secret,
                        appearance: {
                            theme: 'stripe',
                            labels: 'floating'
                        }
                    };

                    stripeElements = stripeInstance.elements(options);
                    
                    // Standard Card Element
                    paymentElement = stripeElements.create('payment');
                    
                    // Safety timeout to hide spinner if ready event never fires
                    const readyTimeout = setTimeout(function() {
                        if ($('#wsb-payment-loading').is(':visible')) {
                            $('#wsb-payment-loading').hide();
                            $('#wsb-stripe-error').text('Taking longer than usual. Please check your internet connection or reload.');
                        }
                    }, 10000);

                    paymentElement.on('ready', function() {
                        clearTimeout(readyTimeout);
                        $('#wsb-payment-loading').hide();
                        isStripeLoading = false;
                    });

                    paymentElement.on('change', function(event) {
                        if (event.error) {
                            $('#wsb-stripe-error').text(event.error.message);
                        } else {
                            $('#wsb-stripe-error').text('');
                        }
                    });

                    paymentElement.mount('#wsb-payment-element');
                } else {
                    isStripeLoading = false;
                    $('#wsb-payment-loading').hide();
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Failed to initialize payment intent.';
                    $('#wsb-stripe-error').text(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                isStripeLoading = false;
                $('#wsb-payment-loading').hide();
                $('#wsb-stripe-error').text('Secure connection failed (Network Error). Please refresh and try again.');
                console.error('WSB Stripe AJAX Error:', status, error);
            }
        });
    }
});

/**
 * Professional Separation: Booking Success Feedback
 */
function wsbCloseSuccess() {
    const overlay = document.getElementById('wsb-success-overlay');
    if (!overlay) return;
    overlay.style.transition = 'all 0.5s ease-in';
    overlay.style.opacity = '0';
    overlay.style.transform = 'translateY(-20px)';
    setTimeout(() => {
        overlay.style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('wsb_payment_confirmed');
        window.history.replaceState({}, '', url);
    }, 500);
}

// Auto-close success message if present
jQuery(document).ready(function() {
    if (document.getElementById('wsb-success-overlay')) {
        setTimeout(wsbCloseSuccess, 2000);
    }
});
