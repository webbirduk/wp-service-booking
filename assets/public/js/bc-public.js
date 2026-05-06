jQuery(document).ready(function($) {
    let splitBookingMode = false;
    let selectedServicesData = []; // [{id, name, duration, staff_id, staff_name, date, time}]
    let currentSplitIndex = 0;

    // Category Filtering Logic
    $('.bc-filter-btn').on('click', function() {
        $('.bc-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        var category = $(this).data('category');
        
        if (category === 'all') {
            $('.bc-card-option').fadeIn(300);
        } else {
            $('.bc-card-option').hide();
            $('.bc-card-option').each(function() {
                var cardCat = $(this).find('.bc-category-badge').text().trim();
                // Match category exactly
                if (cardCat === category) {
                    $(this).fadeIn(300);
                }
            });
        }
    });

    // Persistent Storage Utilities
    const STORAGE_KEY = 'bc_selected_services';
    const getSelectedServices = () => JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
    const saveSelectedServices = (services) => localStorage.setItem(STORAGE_KEY, JSON.stringify(services));

    // Restore Selection on Load
    const initSelection = () => {
        const stored = getSelectedServices();
        stored.forEach(s => {
            $(`.bc-card-option[data-service-id="${s.id}"]`).addClass('selected');
        });
        updateBasketUI();
    };

    initSelection();


    // Basket Toggle Logic (Hover + Click)
    const getBasketPopup = ($el) => {
        return $el.find('#bc-basket-popup');
    };

    let basketTimeout;
    $(document).on('mouseenter', '.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket', function() {
        if (bc_ajax.basket_mode !== 'hover') return;
        clearTimeout(basketTimeout);
        getBasketPopup($(this)).stop().fadeIn(200);
    }).on('mouseleave', '.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket', function() {
        if (bc_ajax.basket_mode !== 'hover') return;
        const $el = $(this);
        basketTimeout = setTimeout(() => {
            getBasketPopup($el).stop().fadeOut(200);
        }, 300);
    });

    $(document).on('click', '.bc-basket-trigger-btn', function(e) {
        const $popup = getBasketPopup($(this).closest('.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket'));
        if ($(e.target).closest('#bc-basket-popup').length === 0) {
            e.preventDefault();
            $popup.stop().fadeToggle(200);
        }
    });

    $(document).on('click', '#bc-close-basket', function(e) {
        e.stopPropagation();
        $(this).closest('#bc-basket-popup').fadeOut(200);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket').length) {
            $('.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket').find('#bc-basket-popup').fadeOut(200);
        }
    });

    function updateBasketUI() {
        const services = getSelectedServices();
        const count = services.length;
        $('.bc-basket-count-val').text(count);
        
        // Update all basket containers on page (menu, shortcode, wizard)
        $('.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket').each(function() {
            const $container = $(this).closest('.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket');
            const $basket = $container.find('#bc-basket-popup');
            if (!$basket.length) return;
            
            const $items = $basket.find('#bc-basket-items');
            const $footer = $basket.find('#bc-basket-footer');
            const $emptyFooter = $basket.find('#bc-empty-basket-footer');
            const $total = $basket.find('#bc-basket-total');
            
            let itemsHtml = '';
            let totalPrice = 0;

            if (count > 0) {
                $footer.show();
                $emptyFooter.hide();
                services.forEach(s => {
                    totalPrice += parseFloat(s.price) || 0;
                    itemsHtml += `
                        <div class="bc-basket-item" style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:rgba(0,0,0,0.02); border-radius:10px; margin-bottom:8px;">
                            <div style="flex-grow:1;">
                                <div style="font-weight:700; font-size:13px; color:var(--bc-heading, #333);">${s.name}</div>
                                <div style="font-size:12px; color:var(--bc-brand, #6366f1); font-weight:600;">${bc_ajax.currency_symbol}${s.price}</div>
                            </div>
                            <span class="bc-remove-item" data-service-id="${s.id}" style="color:#ef4444; font-size:18px; cursor:pointer; padding:5px; line-height:1;">&times;</span>
                        </div>
                    `;
                });
                $items.html(itemsHtml);
                $total.text(bc_ajax.currency_symbol + totalPrice.toFixed(2));
            } else {
                $items.html('<p id="bc-empty-basket-msg" style="text-align:center; color:#666; opacity:0.6; font-size:13px; margin:20px 0;">No services selected yet.</p>');
                $footer.hide();
                $emptyFooter.show();
            }
        });
    }

    $(document).on('click', '.bc-remove-item', function(e) {
        e.stopPropagation();
        const serviceId = $(this).data('service-id');
        
        let services = getSelectedServices();
        const index = services.findIndex(s => s.id == serviceId);
        if (index > -1) {
            services.splice(index, 1);
            saveSelectedServices(services);
        }

        $(`.bc-card-option[data-service-id="${serviceId}"]`).removeClass('selected');
        updateBasketUI();
        
        // Update wizard button if present
        const selectedCount = services.length;
        $('.bc-next-btn[data-next="bc-step-staff"]').prop('disabled', selectedCount === 0);
    });

    // Card Selection Logic for UI
    $(document).on('click', '.bc-card-option', function(e) {
        if ($(e.target).closest('a').length) return;
        
        const id = $(this).data('service-id');
        const name = $(this).find('h4').text();
        const priceText = $(this).find('.bc-price-tag').text().replace(/[^0-9.]/g, '');
        const price = priceText || "0";
        const duration = parseInt($(this).find('.bc-service-meta span:first-child').text()) || 0;
        
        let services = getSelectedServices();
        const index = services.findIndex(s => s.id == id);
        
        if (index > -1) {
            services.splice(index, 1);
            $(this).removeClass('selected');
        } else {
            services.push({id, name, price, duration});
            $(this).addClass('selected');
        }
        
        saveSelectedServices(services);
        updateBasketUI();
        
        const selectedCount = services.length;
        $(this).closest('.bc-wizard-step').find('.bc-next-btn').prop('disabled', selectedCount === 0);

        // Update session duration summary
        let totalDuration = 0;
        let breakdownHtml = '';
        services.forEach(s => {
            totalDuration += s.duration;
            breakdownHtml += `
                <div style="display:flex; justify-content:space-between; font-size:13px; opacity:0.8;">
                    <span>• ${s.name}</span>
                    <span>${s.duration}m</span>
                </div>
            `;
        });

        if (selectedCount > 1) {
            $('#bc-session-duration').text(totalDuration);
            $('#bc-session-duration-time').text(totalDuration);
            $('#bc-service-breakdown').html(breakdownHtml);
            $('#bc-multi-session-notice, #bc-multi-time-notice').fadeIn(300);
        } else {
            $('#bc-multi-session-notice, #bc-multi-time-notice').hide();
        }
    });

    $(document).on('click', '.bc-staff-card', function() {
        $(this).closest('.bc-wizard-step').find('.bc-staff-card').removeClass('selected');
        $(this).addClass('selected');
        
        // Enable the Next button in this step
        $(this).closest('.bc-wizard-step').find('.bc-next-btn').prop('disabled', false);

        // Refresh slots if staff is changed and date is already picked
        if ($('#bc-booking-date').val()) {
            $('#bc-booking-date').trigger('change');
        }
    });

    // Step Navigation
    $(document).on('click', '.bc-next-btn', function(e) {
        e.preventDefault();
        var nextStep = $(this).data('next');
        var currentStep = $(this).closest('.bc-wizard-step');
        
        // Handle Step-Specific Initialization
        if (currentStep.attr('id') === 'bc-step-service') {
            const services = getSelectedServices();
            const selectedCount = services.length;
            splitBookingMode = (bc_ajax.enable_split_scheduling === 'yes' && selectedCount > 1);
            
            selectedServicesData = services.map(s => {
                return {
                    id: s.id,
                    name: s.name,
                    duration: s.duration
                };
            });
            currentSplitIndex = 0;
            
            if (splitBookingMode) {
                $('#bc-split-indicator').show();
                updateSplitUI();
            } else {
                $('#bc-split-indicator').hide();
            }
        }

        // Handle Sequential Scheduling Logic for Split Mode
        if (currentStep.attr('id') === 'bc-step-time' && splitBookingMode) {
            // Store current selection
            selectedServicesData[currentSplitIndex].staff_id = $('.bc-staff-card.selected').data('staff-id') || 'any';
            selectedServicesData[currentSplitIndex].staff_name = $('.bc-staff-card.selected h4').text() || 'Any Specialist';
            selectedServicesData[currentSplitIndex].date = $('#bc-booking-date').val();
            selectedServicesData[currentSplitIndex].time = $('.bc-slot-btn.selected').text();

            if (currentSplitIndex < selectedServicesData.length - 1) {
                currentSplitIndex++;
                updateSplitUI();
                
                // Clear selections for next service
                $('.bc-staff-card').removeClass('selected');
                $('#bc-booking-date').val('');
                selectedDate = null; // Reset the global calendar state
                $('.bc-slot-btn').removeClass('selected');
                $('.bc-time-slots').empty();
                $('.bc-time-picker-section').hide();
                $('#bc-step-time .bc-next-btn').prop('disabled', true);
                $('#bc-step-staff .bc-next-btn').prop('disabled', true);

                transitionToStep('bc-step-staff');
                applyStaffFilter();
                return;
            }
        }

        // Handle Skip Professional Step
        if (nextStep === 'bc-step-staff' && bc_ajax.skip_professional === 'yes') {
            nextStep = 'bc-step-time';
        }

        // Apply Staff Filtering
        if (nextStep === 'bc-step-staff') {
            // In split mode, we filter by the SINGLE current service
            applyStaffFilter();
        }

        // Handle Skip Payment Step
        if (nextStep === 'bc-step-payment' && bc_ajax.skip_payment === 'yes') {
            nextStep = 'bc-step-confirm-manual'; 
        }

        if (currentStep.attr('id') === 'bc-step-details') {
            let hasError = false;
            $('.bc-error-msg').hide().text('');
            $('.bc-input-error').removeClass('bc-input-error');

            const firstName = $('#bc-first-name');
            if (!firstName.val().trim()) {
                firstName.addClass('bc-input-error');
                $('#bc-error-first-name').text('First Name is required').fadeIn(200);
                hasError = true;
            }

            const lastName = $('#bc-last-name');
            if (!lastName.val().trim()) {
                lastName.addClass('bc-input-error');
                $('#bc-error-last-name').text('Last Name is required').fadeIn(200);
                hasError = true;
            }

            const email = $('#bc-email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.val().trim()) {
                email.addClass('bc-input-error');
                $('#bc-error-email').text('Email Address is required').fadeIn(200);
                hasError = true;
            } else if (!emailRegex.test(email.val().trim())) {
                email.addClass('bc-input-error');
                $('#bc-error-email').text('Please enter a valid email address').fadeIn(200);
                hasError = true;
            }

            const phone = $('#bc-phone');
            if (!phone.val().trim()) {
                phone.addClass('bc-input-error');
                $('#bc-error-phone').text('Phone Number is required').fadeIn(200);
                hasError = true;
            }

            if (hasError) {
                return;
            }

            // If payment is skipped, trigger booking directly instead of moving to next step
            if (bc_ajax.skip_payment === 'yes') {
                processManualBooking($(this));
                return;
            }
        }

        console.log('WSB: Navigating to:', nextStep);

        // If we are moving from selection to checkout, handle Stripe redirect
        if (nextStep === 'bc-step-checkout') {
            const method = $('input[name="payment_method"]:checked').val();
            if (method === 'stripe_card') {
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.html('<span>⌛</span> Redirecting to Secure Checkout...').prop('disabled', true);
                
                const serviceIds = $('.bc-card-option.selected').map(function() { return $(this).data('service-id'); }).get();
                
                $.ajax({
                    url: bc_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bc_create_checkout_session',
                        nonce: bc_ajax.nonce,
                        service_id: serviceIds.join(','),
                        staff_id: $('.bc-staff-card.selected').data('staff-id'),
                        booking_date: $('#bc-booking-date').val(),
                        start_time: $('.bc-slot-btn.selected').text(),
                        first_name: $('#bc-first-name').val(),
                        last_name: $('#bc-last-name').val(),
                        email: $('#bc-email').val(),
                        phone: $('#bc-phone').val()
                    },
                    success: function(response) {
                        if (response.success && response.data.url) {
                            localStorage.removeItem(STORAGE_KEY);
                            window.location.href = response.data.url;
                        } else {
                            alert(response.data.message || 'Could not initialize Stripe checkout.');
                            $btn.html(originalHtml).prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Network error. Please try again.');
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                });
                return; // Stop here, don't transition
            }
        }

        // Transition to next step
        currentStep.css({opacity: 1}).animate({opacity: 0, marginTop: '-20px'}, 200, function() {
            currentStep.hide();
            const $next = $('#' + nextStep);
            $next.css({opacity: 0, marginTop: '20px'}).show().animate({opacity: 1, marginTop: '0'}, 300);
            
            // If we just reached the selection step, ensure we clean up the final step
            if (nextStep === 'bc-step-payment') {
                $('#bc-stripe-payment-container, #bc-paypal-checkout-container').hide();
            }

            // If we just reached the final checkout step (for non-Stripe methods)
            if (nextStep === 'bc-step-checkout') {
                const method = $('input[name="payment_method"]:checked').val();
                console.log('WSB: Final checkout for method:', method);
                
                // Populate final summary from selected service cards
                let serviceNames = [];
                let totalPrice = 0;
                let currencySymbol = '';

                $('.bc-card-option.selected').each(function() {
                    const priceText = $(this).find('.bc-price-tag').text();
                    const numericPrice = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                    totalPrice += numericPrice;
                    currencySymbol = priceText.replace(/[0-9.,]/g, '').trim();
                });

                if (splitBookingMode) {
                    let summaryHtml = '';
                    selectedServicesData.forEach(item => {
                        summaryHtml += `<div style="margin-bottom:10px; border-bottom:1px dashed rgba(0,0,0,0.05); padding-bottom:8px;">
                                        <strong>${item.name}</strong><br>
                                        <span style="font-size:12px; opacity:0.7;">👤 Specialist: ${item.staff_name}</span><br>
                                        <span style="font-size:12px; opacity:0.7;">📅 ${item.date} at ${item.time}</span>
                                        </div>`;
                    });
                    $('#bc-checkout-summary-service').html(summaryHtml);
                    $('#bc-checkout-summary-datetime').html('<span style="color:var(--bc-brand); font-weight:700;">Multiple Appointments</span>');
                } else {
                    $('.bc-card-option.selected').each(function() {
                        serviceNames.push($(this).find('h4').text());
                    });
                    $('#bc-checkout-summary-service').html(serviceNames.join('<br>'));
                    $('#bc-checkout-summary-datetime').html(
                        '📅 ' + $('#bc-booking-date').val() + '<br>' +
                        '🕒 ' + $('.bc-slot-btn.selected').text()
                    );
                }
                
                $('#bc-checkout-summary-price, #bc-checkout-summary-total').text(currencySymbol + totalPrice.toFixed(2));

                if (method === 'paypal') {
                    $('#bc-stripe-payment-container').hide();
                    $('#bc-paypal-checkout-container').show();
                    // Move PayPal button container to the final step if it exists
                    if ($('#bc-paypal-button-container').length) {
                        $('#bc-paypal-checkout-container').append($('#bc-paypal-button-container'));
                        $('#bc-paypal-button-container').show();
                    }
                    if (typeof initPaypal === 'function') {
                        initPaypal();
                    }
                }
            }
        });
    });
    function updateSplitUI() {
        const current = selectedServicesData[currentSplitIndex];
        
        // Update Breakdown with Status indicators
        let breakdownHtml = '';
        selectedServicesData.forEach((item, index) => {
            let statusIcon = '<span style="color:var(--bc-text-muted); opacity:0.3;">○</span>';
            let rowStyle = 'opacity:0.5;';
            let statusLabel = '';
            
            if (index < currentSplitIndex) {
                statusIcon = '<span style="color:#10b981;">✅</span>';
                rowStyle = 'opacity:1; font-weight:500;';
                statusLabel = '<span style="font-size:10px; color:#10b981; margin-left:8px;">Scheduled</span>';
            } else if (index === currentSplitIndex) {
                statusIcon = '<span style="color:var(--bc-brand); animation: wsbPulse 1.5s infinite;">📍</span>';
                rowStyle = 'opacity:1; font-weight:800; background:rgba(99, 102, 241, 0.03); border-radius:6px; padding:4px 8px; margin: 2px -8px;';
                statusLabel = '<span style="font-size:10px; color:var(--bc-brand); margin-left:8px;">Planning...</span>';
            }
            
            breakdownHtml += `
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; ${rowStyle} transition:all 0.3s;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        ${statusIcon}
                        <span>${item.name}${statusLabel}</span>
                    </div>
                    <span style="font-variant-numeric: tabular-nums;">${item.duration}m</span>
                </div>
            `;
        });

        $('#bc-service-breakdown').html(breakdownHtml);
        $('#bc-current-split-service-name').text(current.name);
        $('#bc-session-duration').text(current.duration);
        $('#bc-session-duration-time').text(current.duration);
        
        // Update headers to reflect "Step X of Y" for the specific service
        $('#bc-step-staff .bc-step-details h3').text(`Select Specialist for ${current.name}`);
        $('#bc-step-time .bc-step-details h3').text(`Schedule ${current.name}`);
    }

    function transitionToStep(stepId) {
        const current = $('.bc-wizard-step:visible');
        current.css({opacity: 1}).animate({opacity: 0, marginTop: '20px'}, 200, function() {
            current.hide();
            $('#' + stepId).css({opacity: 0, marginTop: '-20px'}).show().animate({opacity: 1, marginTop: '0'}, 300);
        });
    }

    function applyStaffFilter() {
        if (bc_ajax.filter_staff_by_service !== 'yes') return;

        let selectedServiceIds = [];
        if (splitBookingMode && selectedServicesData[currentSplitIndex]) {
            selectedServiceIds = [selectedServicesData[currentSplitIndex].id];
        } else {
            selectedServiceIds = $('.bc-card-option.selected').map(function() { return $(this).data('service-id'); }).get();
        }
        
        if (selectedServiceIds.length > 0) {
            let eligibleStaffIds = null;
            
            selectedServiceIds.forEach(serviceId => {
                const staffForService = bc_ajax.staff_service_mapping[serviceId] || [];
                if (eligibleStaffIds === null) {
                    eligibleStaffIds = [...staffForService];
                } else {
                    eligibleStaffIds = eligibleStaffIds.filter(id => staffForService.includes(id));
                }
            });
            
            // Show/Hide staff cards
            $('.bc-staff-card').hide();
            // Always show "Any" staff card if it exists
            $('.bc-staff-card[data-staff-id="any"]').show();

            if (eligibleStaffIds && eligibleStaffIds.length > 0) {
                eligibleStaffIds.forEach(staffId => {
                    $(`.bc-staff-card[data-staff-id="${staffId}"]`).show();
                });
                $('.bc-no-staff-msg').hide();
                
                // Deselect if hidden
                const $selectedStaff = $('.bc-staff-card.selected');
                if ($selectedStaff.length && $selectedStaff.is(':hidden')) {
                    $selectedStaff.removeClass('selected');
                    $('#bc-step-staff .bc-next-btn').prop('disabled', true);
                }
            } else {
                if ($('.bc-no-staff-msg').length === 0) {
                    $('#bc-step-staff .bc-card-grid').after('<p class="bc-no-staff-msg" style="text-align:center; color:#ef4444; padding:20px; font-weight:700;">No professionals are available for the selected service.</p>');
                }
                $('.bc-no-staff-msg').show();
                $('#bc-step-staff .bc-next-btn').prop('disabled', true);
            }
        }
    }

    $('.bc-prev-btn').on('click', function(e) {
        e.preventDefault();
        var prevStep = $(this).data('prev');
        var currentStep = $(this).closest('.bc-wizard-step');

        // Handle Back Navigation when skipping
        if (prevStep === 'bc-step-staff' && bc_ajax.skip_professional === 'yes') {
            prevStep = 'bc-step-service';
        }
        
        currentStep.css({opacity: 1}).animate({opacity: 0, marginTop: '20px'}, 200, function() {
            currentStep.hide();
            $('#' + prevStep).css({opacity: 0, marginTop: '-20px'}).show().animate({opacity: 1, marginTop: '0'}, 300);
        });
    });

    async function processManualBooking($btn) {
        const originalText = $btn.text();
        $btn.text('Securing Appointments...').prop('disabled', true);

        const customerData = {
            first_name: $('#bc-first-name').val(),
            last_name: $('#bc-last-name').val(),
            email: $('#bc-email').val(),
            phone: $('#bc-phone').val(),
            notes: $('#bc-notes').val()
        };

        let bookingsToCreate = [];
        if (splitBookingMode) {
            bookingsToCreate = selectedServicesData.map(item => ({
                service_id: item.id,
                staff_id: item.staff_id,
                booking_date: item.date,
                start_time: item.time
            }));
        } else {
            bookingsToCreate = [{
                service_id: $('.bc-card-option.selected').map(function() { return $(this).data('service-id'); }).get().join(','),
                staff_id: $('.bc-staff-card.selected').data('staff-id') || 'any',
                booking_date: $('#bc-booking-date').val(),
                start_time: $('.bc-slot-btn.selected').text()
            }];
        }

        try {
            let lastResponse = null;
            for (const b of bookingsToCreate) {
                const res = await $.ajax({
                    url: bc_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bc_create_booking',
                        nonce: bc_ajax.nonce,
                        ...customerData,
                        ...b,
                        payment_method: 'manual'
                    }
                });
                lastResponse = res;
            }

            if (lastResponse && lastResponse.success) {
                // Clear basket
                localStorage.removeItem(STORAGE_KEY);
                // Show success state
                $('#bc-booking-wizard-container').html(
                    '<div style="text-align:center; padding:60px 40px; background:#fff; border-radius:32px; border:1px solid var(--bc-border); box-shadow:var(--bc-shadow-lg);">' +
                    '<div style="width:100px; height:100px; background:#10b981; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:50px; margin:0 auto 30px; animation:wsbPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);">✓</div>' +
                    '<h2 style="font-size:32px; font-weight:800; color:#0f172a; margin-bottom:15px;">All appointments secured!</h2>' +
                    '<p style="color:#64748b; font-size:18px; line-height:1.6; margin-bottom:40px;">Your scheduling request has been processed successfully. Check your email for details.</p>' +
                    '<button onclick="location.reload()" class="bc-btn bc-next-btn" style="padding:15px 40px;">Book More Services</button>' +
                    '</div>'
                );
            } else {
                alert(lastResponse.data.message || 'Booking failed.');
                $btn.text(originalText).prop('disabled', false);
            }
        } catch (err) {
            console.error('WSB Booking Error:', err);
            alert('A technical error occurred while securing your appointments. Please try again.');
            $btn.text(originalText).prop('disabled', false);
        }
    }

    // Custom Interactive Calendar Generator
    let currentDate = new Date();
    let selectedDate = null;

    function renderCalendar() {
        const monthYearTitle = $('#bc-current-month-year');
        const daysGrid = $('#bc-calendar-days');
        
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
            daysGrid.append('<div class="bc-cal-day disabled"></div>');
        }
        
        const today = new Date();
        today.setHours(0,0,0,0);
        
        // Current month days
        for (let day = 1; day <= lastDay; day++) {
            const cellDate = new Date(year, month, day);
            const dateString = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            
            let classes = 'bc-cal-day';
            if (cellDate < today) {
                classes += ' disabled';
            }
            if (selectedDate === dateString) {
                classes += ' selected';
            }
            
            const dayCell = $('<div class="' + classes + '" data-date="' + dateString + '">' + day + '</div>');
            
            if (cellDate >= today) {
                dayCell.on('click', function() {
                    $('.bc-cal-day').removeClass('selected');
                    $(this).addClass('selected');
                    selectedDate = $(this).data('date');
                    $('#bc-booking-date').val(selectedDate).trigger('change');
                });
            }
            
            daysGrid.append(dayCell);
        }
    }

    $('#bc-prev-month').on('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    $('#bc-next-month').on('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    // Trigger calendar render when arriving at the time step
    $(document).on('click', '.bc-next-btn', function() {
        setTimeout(renderCalendar, 350);
    });

    // Time Slot loading
    $('#bc-booking-date').on('change', function() {
        var dateVal = $(this).val();
        if(!dateVal) return;
        
        $('.bc-time-picker-section').slideDown(300);
        $('.bc-time-slots').html('<p style="color: var(--bc-pub-muted); text-align:center; width:100%;">Loading available times...</p>');
        
        $.ajax({
            url: bc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bc_get_slots',
                nonce: bc_ajax.nonce,
                date: dateVal,
                service_id: splitBookingMode && selectedServicesData[currentSplitIndex] 
                            ? selectedServicesData[currentSplitIndex].id 
                            : $('.bc-card-option.selected').map(function() { return $(this).data('service-id'); }).get().join(','),
                staff_id: $('.bc-staff-card.selected').data('staff-id') || 'any'
            },
            success: function(response) {
                if (response.success) {
                    var slotsHtml = '';
                    response.data.slots.forEach(function(slot) {
                        slotsHtml += '<button class="bc-slot-btn">' + slot + '</button> ';
                    });
                    $('.bc-time-slots').html(slotsHtml);
                    
                    // Re-bind slot selection
                    $('.bc-slot-btn').on('click', function(e) {
                        e.preventDefault();
                        $('.bc-slot-btn').removeClass('selected');
                        $(this).addClass('selected');
                        $('#bc-step-time').find('.bc-next-btn').prop('disabled', false);
                    });
                }
            }
        });
    });

    // Final Booking Submission
    $('#bc-confirm-booking').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        
        // Basic Validation
        let hasError = false;
        $('.bc-error-msg').hide().text('');
        $('.bc-input-error').removeClass('bc-input-error');

        const firstName = $('#bc-first-name');
        if (!firstName.val().trim()) {
            firstName.addClass('bc-input-error');
            $('#bc-error-first-name').text('First Name is required').fadeIn(200);
            hasError = true;
        }

        const lastName = $('#bc-last-name');
        if (!lastName.val().trim()) {
            lastName.addClass('bc-input-error');
            $('#bc-error-last-name').text('Last Name is required').fadeIn(200);
            hasError = true;
        }

        const email = $('#bc-email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email.val().trim()) {
            email.addClass('bc-input-error');
            $('#bc-error-email').text('Email Address is required').fadeIn(200);
            hasError = true;
        } else if (!emailRegex.test(email.val().trim())) {
            email.addClass('bc-input-error');
            $('#bc-error-email').text('Please enter a valid email address').fadeIn(200);
            hasError = true;
        }

        const phone = $('#bc-phone');
        if (!phone.val().trim()) {
            phone.addClass('bc-input-error');
            $('#bc-error-phone').text('Phone Number is required').fadeIn(200);
            hasError = true;
        }

        if (hasError) {
            return;
        }

        btn.text('Processing...').prop('disabled', true);
        
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        const serviceIds = $('.bc-card-option.selected').map(function() { return $(this).data('service-id'); }).get().join(',');
        const staffId = $('.bc-staff-card.selected').data('staff-id');
        const bookingDate = $('#bc-booking-date').val();
        const bookingTime = $('.bc-slot-btn.selected').text();

        function processStandardBooking() {
            $.ajax({
                url: bc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bc_create_booking',
                    nonce: bc_ajax.nonce,
                    first_name: $('#bc-first-name').val(),
                    last_name: $('#bc-last-name').val(),
                    email: $('#bc-email').val(),
                    phone: $('#bc-phone-code').val() + $('#bc-phone').val(),
                    notes: $('#bc-notes').val(),
                    service_id: serviceIds,
                    staff_id: staffId,
                    booking_date: bookingDate,
                    start_time: bookingTime,
                    payment_method: paymentMethod
                },
                success: function(response) {
                    if (response.success) {
                        $('#bc-step-payment').html(
                            '<div class="bc-success-card" style="text-align:center; padding: 40px 20px; background:#fff; border-radius:20px; border:1.5px solid var(--bc-border); box-shadow:var(--bc-shadow-sm); max-width:550px; margin:20px auto;">' +
                            '<div style="width: 80px; height: 80px; background:rgba(16, 185, 129, 0.1); color:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:40px; margin:0 auto 25px;">✔</div>' +
                            '<h2 style="margin:0 0 10px; font-size:28px; color:var(--bc-text-main); font-weight:800;">Booking Confirmed!</h2>' +
                            '<p style="color:var(--bc-text-muted); font-size:16px; margin-bottom:30px; line-height:1.6;">' + response.data.message + '</p>' +
                            '<div style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap;">' +
                            '<a href="' + (bc_ajax.dashboard_url || '/booking-dashboard') + '" class="bc-btn bc-next-btn" style="text-decoration:none; display:inline-block;">View My Bookings</a>' +
                            '<button class="bc-btn bc-prev-btn" onclick="location.reload()">Book Another</button>' +
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
                url: bc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bc_create_checkout_session',
                    nonce: bc_ajax.nonce,
                    service_id: serviceIds,
                    staff_id: staffId,
                    booking_date: bookingDate,
                    start_time: bookingTime,
                    first_name: $('#bc-first-name').val(),
                    last_name: $('#bc-last-name').val(),
                    email: $('#bc-email').val(),
                    phone: $('#bc-phone').val()
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
    $(document).on('click', '.bc-client-action-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const action = btn.data('action');
        const bookingId = btn.data('id');
        
        if (action === 'cancel') {
            $('#bc-cancel-id').val(bookingId);
            $('#bc-cancel-title-id').text('#' + bookingId);
            $('#bc-cancel-msg').hide();
            $('#bc-cancel-modal').css('display', 'flex').fadeIn(200);
        } else {
            $('#bc-reschedule-id').val(bookingId);
            $('#bc-reschedule-msg').hide();
            $('.bc-reschedule-slots').html('');
            $('#bc-reschedule-time-input').val('');
            $('#bc-reschedule-staff').val('');
            $('#bc-reschedule-date').val('');
            $('#bc-reschedule-slots-container').hide();
            $('#bc-reschedule-modal').css('display', 'flex').fadeIn(200);
        }
    });
    // Client Dashboard Tabs Switcher
    $(document).on('click', '.bc-dash-tab', function(e) {
        e.preventDefault();
        $('.bc-dash-tab').css({'border-bottom': '3px solid transparent', 'color': 'var(--bc-text-muted)'});
        $(this).css({'border-bottom': '3px solid var(--bc-brand)', 'color': 'var(--bc-brand)'});
        
        $('.bc-dash-content-panel').hide();
        $('#' + $(this).data('target')).fadeIn(200);
    });

    // Client Account Form Submission
    $(document).on('submit', '#bc-client-account-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msgBox = $('#bc-account-msg');
        
        btn.prop('disabled', true).text('Saving...');
        msgBox.hide().removeClass('bc-success bc-error');
        
        $.ajax({
            url: bc_ajax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=bc_update_account_details&nonce=' + bc_ajax.nonce,
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
    $(document).on('click', '.bc-booking-row', function(e) {
        if ($(e.target).closest('.bc-client-action-btn').length) return;
        
        const row = $(this);
        $('#bc-modal-id').text('#' + row.data('id'));
        $('#bc-modal-service').text(row.data('service'));
        $('#bc-modal-staff').text(row.data('staff'));
        $('#bc-modal-datetime').text(row.data('date') + ' @ ' + row.data('time'));
        $('#bc-modal-amount').text(row.data('amount'));
        
        const status = row.data('status').toLowerCase();
        const statusEl = $('#bc-modal-status');
        statusEl.text(status);
        
        if (status === 'confirmed' || status === 'completed') {
            statusEl.css({'background': 'rgba(16, 185, 129, 0.1)', 'color': '#10b981'});
        } else if (status === 'cancelled') {
            statusEl.css({'background': 'rgba(239, 68, 68, 0.1)', 'color': '#ef4444'});
        } else {
            statusEl.css({'background': 'rgba(245, 158, 11, 0.1)', 'color': '#f59e0b'});
        }
        
        $('#bc-details-modal').css('display', 'flex').fadeIn(200);
    });

    $(document).on('click', '.bc-modal-close', function() {
        $('#bc-details-modal').fadeOut(200);
    });

    $(document).on('click', '#bc-details-modal', function(e) {
        if ($(e.target).is('#bc-details-modal')) {
            $(this).fadeOut(200);
        }
    });
    // Reschedule Modal Dismiss
    $(document).on('click', '.bc-reschedule-close', function() {
        $('#bc-reschedule-modal').fadeOut(200);
    });

    $(document).on('click', '#bc-reschedule-modal', function(e) {
        if ($(e.target).is('#bc-reschedule-modal')) {
            $(this).fadeOut(200);
        }
    });

    // Slot fetching for Reschedule
    $(document).on('change', '#bc-reschedule-staff, #bc-reschedule-date', function() {
        const staffId = $('#bc-reschedule-staff').val();
        const date = $('#bc-reschedule-date').val();
        const bookingId = $('#bc-reschedule-id').val();
        
        if (!staffId || !date) {
            $('#bc-reschedule-slots-container').hide();
            return;
        }
        $('#bc-reschedule-slots-container').fadeIn(200);
        
        $('.bc-reschedule-slots').html('<div style="grid-column:1/-1; text-align:center; color:var(--bc-text-muted);">Loading slots...</div>');
        $('#bc-reschedule-time-input').val('');
        
        $.ajax({
            url: bc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bc_get_slots',
                nonce: bc_ajax.nonce,
                staff_id: staffId,
                date: date,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success && response.data.slots && response.data.slots.length > 0) {
                    let slotsHtml = '';
                    response.data.slots.forEach(function(slot) {
                        slotsHtml += '<button type="button" class="bc-reschedule-slot-btn" data-time="' + slot + '" style="padding:10px; border:1.5px solid var(--bc-border); border-radius:10px; background:#fff; font-weight:700; cursor:pointer; text-align:center; transition:all 0.2s;">' + slot + '</button>';
                    });
                    $('.bc-reschedule-slots').html(slotsHtml);
                    
                    // Bind click selection
                    $('.bc-reschedule-slot-btn').on('click', function(e) {
                        e.preventDefault();
                        $('.bc-reschedule-slot-btn').css({'background': '#fff', 'color': 'var(--bc-text-main)', 'border-color': 'var(--bc-border)'});
                        $(this).css({'background': 'var(--bc-brand)', 'color': '#fff', 'border-color': 'var(--bc-brand)'});
                        $('#bc-reschedule-time-input').val($(this).data('time'));
                        $('#bc-reschedule-time-error').hide();
                    });
                } else {
                    $('.bc-reschedule-slots').html('<div style="grid-column:1/-1; text-align:center; color:#ef4444; font-weight:600;">No slots available for this date.</div>');
                }
            },
            error: function() {
                $('.bc-reschedule-slots').html('<div style="grid-column:1/-1; text-align:center; color:#ef4444;">Error loading slots.</div>');
            }
        });
    });

    // Reschedule Submission
    $(document).on('submit', '#bc-reschedule-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msgBox = $('#bc-reschedule-msg');
        const timeInput = $('#bc-reschedule-time-input').val();
        
        if (!timeInput) {
            $('#bc-reschedule-time-error').fadeIn(200);
            return;
        }
        
        btn.prop('disabled', true).text('Processing...');
        msgBox.hide().removeClass('bc-success bc-error');
        
        $.ajax({
            url: bc_ajax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=bc_client_booking_action&client_action=reschedule&nonce=' + bc_ajax.nonce,
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
    $(document).on('click', '.bc-cancel-close', function() {
        $('#bc-cancel-modal').fadeOut(200);
    });

    $(document).on('click', '#bc-cancel-modal', function(e) {
        if ($(e.target).is('#bc-cancel-modal')) {
            $(this).fadeOut(200);
        }
    });

    $(document).on('submit', '#bc-cancel-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const msgBox = $('#bc-cancel-msg');
        const bookingId = $('#bc-cancel-id').val();
        
        btn.prop('disabled', true).text('Processing...');
        msgBox.hide();
        
        $.ajax({
            url: bc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bc_client_booking_action',
                nonce: bc_ajax.nonce,
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

    // Payment Method Selection Handling
    $(document).on('click', '.bc-payment-method-card', function() {
        const $card = $(this);
        const method = $card.data('method');
        
        // UI Feedback
        $('.bc-payment-method-card').css({
            'border': '1.5px solid var(--bc-border)',
            'background': '#ffffff'
        }).removeClass('active').find('.bc-method-check').hide();
        
        $card.css({
            'border': '2px solid var(--bc-brand)',
            'background': '#fff'
        }).addClass('active').find('.bc-method-check').show();
        
        // Update hidden radio
        $card.find('input[name="payment_method"]').prop('checked', true).trigger('change');
    });

    $(document).on('click', '.bc-payment-tab', function() {
        $('.bc-payment-tab').css({'border': '1px solid #e2e8f0', 'background': '#ffffff'});
        $(this).css({'border': '2px solid var(--bc-brand)', 'background': '#fff'});
        
        const method = $(this).data('method');
        $(`input[name="payment_method"][value="${method}"]`).prop('checked', true).trigger('change');
    });

    $(document).on('click', '#bc-complete-checkout-btn', function(e) {
        e.preventDefault();
        $('#bc-confirm-booking').trigger('click');
    });

    function initializeStripe() {
        if (stripeInstance || isStripeLoading) return;
        isStripeLoading = true;

        const serviceId = $('.bc-card-option.selected').data('service-id');
        if (!serviceId) {
            $('#bc-stripe-error').text('Please go back and select a Service first.').show();
            return;
        }

        if (!bc_ajax.stripe_pk) {
            $('#bc-stripe-error').text('Configuration Error: Stripe Publishable Key is missing in Settings.').show();
            return;
        }

        stripeInstance = Stripe(bc_ajax.stripe_pk);

        $.ajax({
            url: bc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bc_create_stripe_intent',
                nonce: bc_ajax.nonce,
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
                        if ($('#bc-payment-loading').is(':visible')) {
                            $('#bc-payment-loading').hide();
                            $('#bc-stripe-error').text('Taking longer than usual. Please check your internet connection or reload.');
                        }
                    }, 10000);

                    paymentElement.on('ready', function() {
                        clearTimeout(readyTimeout);
                        $('#bc-payment-loading').hide();
                        isStripeLoading = false;
                    });

                    paymentElement.on('change', function(event) {
                        if (event.error) {
                            $('#bc-stripe-error').text(event.error.message);
                        } else {
                            $('#bc-stripe-error').text('');
                        }
                    });

                    paymentElement.mount('#bc-payment-element');
                } else {
                    isStripeLoading = false;
                    $('#bc-payment-loading').hide();
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Failed to initialize payment intent.';
                    $('#bc-stripe-error').text(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                isStripeLoading = false;
                $('#bc-payment-loading').hide();
                $('#bc-stripe-error').text('Secure connection failed (Network Error). Please refresh and try again.');
                console.error('WSB Stripe AJAX Error:', status, error);
            }
        });
    }

    // Basket Checkout Button Logic
    $(document).on('click', '.bc-basket-checkout-btn', function(e) {
        if ($('#bc-booking-wizard-container').length) {
            e.preventDefault();
            $('.bc-next-btn[data-next="bc-step-staff"]').trigger('click');
            // Close the popup
            $('.bc-basket-trigger-btn, .bc-menu-basket-wrap, .bc-standalone-basket').find('#bc-basket-popup').fadeOut(200);
        }
    });

    // Auto-select service from URL parameter or Jump to Staff
    function handleServiceDeepLink() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // 1. Check for Auto-selection (From Single Service Page)
        const selectId = urlParams.get('bc_select_service');
        if (selectId) {
            const $target = $(`.bc-card-option[data-service-id="${selectId}"]`);
            if ($target.length) {
                const name = $.trim($target.find('h4').text());
                const price = $target.data('price') || 0;
                const duration = $target.data('duration') || 0;
                
                if (name) {
                    let services = getSelectedServices();
                    if (!services.some(s => s.id == selectId)) {
                        services.push({ id: selectId, name, price, duration });
                        saveSelectedServices(services);
                    }
                    updateBasketUI();
                    $target.addClass('selected');
                }
            }
        }

        // 2. Handle Jump to Staff (from Basket or Select Link)
        if (urlParams.get('bc_jump_to_staff')) {
            const services = getSelectedServices();
            if (services.length > 0) {
                setTimeout(() => {
                    $('.bc-next-btn[data-next="bc-step-staff"]').trigger('click');
                    
                    // Clean URL to prevent double jump on refresh
                    const newUrl = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, newUrl);
                }, 800);
            }
            return;
        }

        // 3. Legacy deep link support
        const serviceId = urlParams.get('service_id');
        if (serviceId) {
            const $target = $(`.bc-card-option[data-service-id="${serviceId}"]`);
            if ($target.length) {
                // Pre-select the service
                $('.bc-card-option').removeClass('selected');
                $target.addClass('selected');
                
                // Update Storage
                const id = $target.data('service-id');
                const name = $target.find('h4').text();
                const priceText = $target.find('.bc-price-tag').text().replace(/[^0-9.]/g, '');
                const price = priceText || "0";
                const duration = parseInt($target.find('.bc-service-meta span:first-child').text()) || 0;
                saveSelectedServices([{id, name, price, duration}]);
                updateBasketUI();

                // Hide all steps immediately
                $('.bc-wizard-step').hide();
                
                // Determine target step (Time selection)
                const targetStep = $('#bc-step-time');
                
                // Remove step numbers from headings for a cleaner "direct" look
                $('.bc-wizard-step h3').each(function() {
                    let text = $(this).text();
                    $(this).text(text.replace(/^\d+\.\s*/, ''));
                });

                // Show target step instantly
                targetStep.show();
                
                // IMPORTANT: Manually trigger calendar render since we bypassed normal navigation
                if (typeof renderCalendar === 'function') {
                    renderCalendar();
                }

                // Trigger any necessary logic for the target step
                if (targetStep.attr('id') === 'bc-step-staff') {
                    applyStaffFilter();
                }

                if ($('#bc-booking-date').val()) {
                    $('#bc-booking-date').trigger('change');
                }
            }
        }
    }
    handleServiceDeepLink();
});

/**
 * Professional Separation: Booking Success Feedback
 */
function wsbCloseSuccess() {
    const overlay = document.getElementById('bc-success-overlay');
    if (!overlay) return;
    overlay.style.transition = 'all 0.5s ease-in';
    overlay.style.opacity = '0';
    overlay.style.transform = 'translateY(-20px)';
    setTimeout(() => {
        overlay.style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('bc_payment_confirmed');
        window.history.replaceState({}, '', url);
    }, 500);
}

// Auto-close success message if present
jQuery(document).ready(function() {
    if (document.getElementById('bc-success-overlay')) {
        setTimeout(wsbCloseSuccess, 2000);
    }
});
