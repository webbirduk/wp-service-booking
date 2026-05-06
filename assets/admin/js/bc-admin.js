jQuery(document).ready(function($) {
    // 1. Master Tab Navigation (SPA)
    $(document).on('click', '.bc-nav-item[data-tab]', function() {
        var tab = $(this).data('tab');
        var sidebarItem = $(this);

        // UI Feedback
        $('.bc-nav-item').removeClass('active');
        sidebarItem.addClass('active');

        loadTab(tab);
    });

    function loadTab(tab, extraParams = '') {
        $('.bc-loader').fadeIn('fast');
        $('#bc-ajax-response').css('opacity', '0.5');

        var targetUrl = bc_admin_ajax.ajax_url;
        
        $.ajax({
            url: targetUrl,
            type: 'POST',
            data: {
                action: 'bc_load_admin_tab',
                nonce: bc_admin_ajax.nonce,
                tab: tab,
                params: extraParams
            },
            success: function(response) {
                if (response.success) {
                    try {
                        $('#bc-ajax-response').html(response.data.content);
                        $(document).trigger('bc-tab-loaded', [tab]);
                        
                        // Scroll to top so user sees notices
                        $('.bc-master-content').scrollTop(0);

                        // Update URL without reload
                        var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=bc_main&tab=' + tab + extraParams;
                        window.history.pushState({path: newUrl}, '', newUrl);
                    } catch (e) {
                        console.error('BC Render Error:', e);
                    }
                    handleBCNotices(); // Ensure notices are handled after AJAX load
                } else {
                    console.error('BC AJAX Error: Success was false.', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('BC AJAX Network Error:', status, error);
            },
            complete: function() {
                $('.bc-loader').fadeOut('fast');
                $('#bc-ajax-response').css('opacity', '1');
            }
        });
    }

    // Intercept internal links for SPA feel
    $(document).on('click', '.bc-master-content a', function(e) {
        var href = $(this).attr('href');
        if (href && (href.indexOf('page=bc_main') !== -1 || href.indexOf('page=boocommerce') !== -1) && !$(this).hasClass('bc-no-ajax')) {
            e.preventDefault();
            // Parse tab and other params
            var urlParams = new URLSearchParams(href.split('?')[1]);
            var tab = urlParams.get('tab') || 'dashboard';
            // Also handle old slugs if any
            if (href.indexOf('bookings') !== -1) tab = 'bookings';
            if (href.indexOf('services') !== -1) tab = 'services';
            if (href.indexOf('staff') !== -1) tab = 'staff';
            if (href.indexOf('customers') !== -1) tab = 'customers';
            if (href.indexOf('finance') !== -1) tab = 'finance';
            if (href.indexOf('design') !== -1) tab = 'design';
            if (href.indexOf('settings') !== -1) tab = 'settings';

            // Reconstruct extra params (like action=edit, id=...)
            var extra = '';
            urlParams.forEach((value, key) => {
                if (key !== 'page' && key !== 'tab') {
                    extra += '&' + key + '=' + value;
                }
            });

            // Update sidebar UI
            $('.bc-nav-item').removeClass('active');
            $('.bc-nav-item[data-tab="' + tab + '"]').addClass('active');

            loadTab(tab, extra);
        }
    });

    // 2. Featured Image & Gallery Logic
    $(document).on('click', '.bc-select-image, .bc-select-gallery', function(e) {
        e.preventDefault();
        var button = $(this);
        var isMultiple = button.hasClass('bc-select-gallery');
        var targetInput = $(button.data('target'));
        var previewContainer = $(button.data('preview'));

        var frame = wp.media({
            title: isMultiple ? 'Select Gallery Images' : 'Select Image',
            button: { text: isMultiple ? 'Add to Gallery' : 'Use this image' },
            multiple: isMultiple
        });

        frame.on('select', function() {
            if (isMultiple) {
                var selection = frame.state().get('selection');
                var urls = [];
                var previewHtml = '';
                selection.map(function(attachment) {
                    attachment = attachment.toJSON();
                    urls.push(attachment.url);
                    previewHtml += '<div style="width:50px; height:50px; border-radius:4px; background:url(' + attachment.url + ') center/cover; border:1px solid #334155; display:inline-block; margin-right:5px;"></div>';
                });
                targetInput.val(urls.join(','));
                previewContainer.html(previewHtml);
            } else {
                var attachment = frame.state().get('selection').first().toJSON();
                targetInput.val(attachment.url);
                previewContainer.css('background', '#0f172a url(' + attachment.url + ') center/cover');
            }
        });

        frame.open();
    });

    // 3. Form Interceptor
    $(document).on('submit', '.bc-master-content form', function(e) {
        if ($(this).hasClass('bc-no-ajax')) return;
        e.preventDefault();
        
        var form = $(this);
        
        // Sync TinyMCE editors before gathering form data
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }

        var formData = new FormData(form[0]);
        var activeTab = $('.bc-nav-item.active').data('tab') || 'dashboard';
        
        // Ensure action and nonce
        if (!formData.has('action')) formData.append('action', 'bc_load_admin_tab');
        if (!formData.has('nonce')) formData.append('nonce', bc_admin_ajax.nonce);
        
        // Prioritize tab from form if it exists (e.g. filter forms), otherwise use active tab
        var tabToLoad = formData.get('tab') || activeTab;
        if (!formData.has('tab')) formData.append('tab', tabToLoad);
        
        $('.bc-loader').fadeIn('fast');
        $.ajax({
            url: bc_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#bc-ajax-response').html(response.data.content);
                    $(document).trigger('bc-tab-loaded', [activeTab]);
                    
                    // Scroll to top so user sees notices
                    $('.bc-master-content').scrollTop(0);

                    // Update URL with filter params for shareability/refresh
                    var queryString = form.serialize();
                    var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + queryString;
                    window.history.pushState({path: newUrl}, '', newUrl);

                    handleBCNotices(); // Handle notices after form submission
                }
                $('.bc-loader').fadeOut('fast');
            },
            error: function() {
                console.error('BC Error: Form submission failed.');
            },
            complete: function() {
                $('.bc-loader').fadeOut('fast');
            }
        });
    });
    // Stripe Connection Tester
    $(document).on('click', '#bc-test-stripe-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var secretKey = $('#bc_stripe_secret_key').val();
        var spinner = $('#bc-stripe-test-spinner');
        var resultBox = $('#bc-stripe-test-result');

        if (!secretKey) {
            resultBox.css({'color': '#ef4444', 'display': 'block'}).text('Enter a Secret Key first!');
            return;
        }

        btn.prop('disabled', true);
        spinner.fadeIn(150);
        resultBox.hide();

        $.ajax({
            url: bc_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bc_test_stripe_connection',
                nonce: bc_admin_ajax.nonce,
                stripe_sk: secretKey
            },
            success: function(response) {
                spinner.hide();
                btn.prop('disabled', false);
                resultBox.fadeIn(150);
                if (response.success) {
                    resultBox.css('color', '#10b981').text(response.data.message);
                } else {
                    resultBox.css('color', '#ef4444').text(response.data.message);
                }
            },
            error: function() {
                spinner.hide();
                btn.prop('disabled', false);
                resultBox.css({'color': '#ef4444', 'display': 'block'}).text('Network failure. Retry test.');
            }
        });
    });
    // 4. Clickable Rows (SPA Navigation)
    $(document).on('click', '.bc-clickable-row', function(e) {
        // Don't trigger if clicking an actual link, button, or the action container
        if ($(e.target).closest('.bc-row-actions, a, button, input, select').length) return;
        
        var href = $(this).data('href');
        if (href) {
            var urlParams = new URLSearchParams(href.split('?')[1]);
            var tab = urlParams.get('tab') || 'dashboard';
            
            // Collect all other params
            var extra = '';
            urlParams.forEach((value, key) => {
                if (key !== 'page' && key !== 'tab') {
                    extra += '&' + key + '=' + value;
                }
            });

            // Update Sidebar UI
            $('.bc-nav-item').removeClass('active');
            $('.bc-nav-item[data-tab="' + tab + '"]').addClass('active');

            loadTab(tab, extra);
        }
    });

    // 5. Notice Handler (Close Icon + Auto-disappear)
    function handleBCNotices() {
        $('.bc-master-wrapper .notice').each(function() {
            var notice = $(this);
            
            // Add close icon if not already present
            if (notice.find('.bc-notice-close').length === 0) {
                notice.append('<span class="bc-notice-close"><span class="dashicons dashicons-dismiss"></span></span>');
            }

            // Automatic disappearance after 5 seconds (5000ms)
            if (!notice.hasClass('bc-sticky-notice')) {
                setTimeout(function() {
                    if (notice.length && notice.parent().length) {
                        notice.fadeOut(400, function() {
                            $(this).remove();
                        });
                    }
                }, 5000);
            }
        });
    }

    $(document).on('click', '.bc-notice-close', function() {
        $(this).closest('.notice').fadeOut(200, function() {
            $(this).remove();
        });
    });

    // Initial check
    handleBCNotices();

    // Re-check after tab loads or forms submit (handled via triggers or callbacks)
    $(document).on('bc-tab-loaded', function() {
        handleBCNotices();
    });

    // Handle History (Back/Forward)
    window.onpopstate = function(event) {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || 'dashboard';
        $('.bc-nav-item').removeClass('active');
        $('.bc-nav-item[data-tab="' + tab + '"]').addClass('active');
        loadTab(tab);
        handleBCNotices();
    };

    // 6. Admin Safety Confirmation (Impact Analysis)
    function bcConfirm(title, msg) {
        return new Promise((resolve) => {
            const modal = $(`
                <div class="bc-admin-modal-overlay">
                    <div class="bc-admin-modal">
                        <div class="bc-admin-modal-badge">System Integrity Warning</div>
                        <h3 id="bc-modal-title">${title}</h3>
                        <div class="bc-admin-modal-body" id="bc-modal-msg">${msg}</div>
                        <div class="bc-admin-modal-footer">
                            <button class="bc-modal-btn bc-modal-cancel">Keep Enabled</button>
                            <button class="bc-modal-btn bc-modal-confirm">Confirm Deactivation</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            modal.find('.bc-modal-confirm').on('click', function() {
                modal.fadeOut(200, function() { modal.remove(); });
                resolve(true);
            });
            
            modal.find('.bc-modal-cancel').on('click', function() {
                modal.fadeOut(200, function() { modal.remove(); });
                resolve(false);
            });
        });
    }

    $(document).on('change', '.bc-master-content .bc-switch input', async function(e) {
        var checkbox = $(this);
        var name = checkbox.attr('name');
        var isChecked = checkbox.is(':checked');
        
        // Impact messages for disabling critical features
        const impacts = {
            'bc_skip_professional_step': {
                'title': 'Bypassing Team Selection',
                'msg': 'Enabling this will automatically skip the professional selection step for customers. Bookings will be assigned to any available staff member.',
                'triggerOn': 'check'
            },
            'bc_skip_payment_step': {
                'title': 'Deactivating Online Checkout',
                'msg': 'Enabling this will allow customers to book without paying upfront. Your Stripe/PayPal integrations will be bypassed.',
                'triggerOn': 'check'
            },
            'bc_filter_staff_by_service': {
                'title': 'Deactivating Expertise Filtering',
                'msg': 'All staff members will now be visible for every service. The system will no longer restrict selection based on your specialist-to-service mapping.',
                'triggerOn': 'uncheck'
            },
            'bc_enable_split_scheduling': {
                'title': 'Disabling Multi-Pro Scheduling',
                'msg': 'Customers will lose the ability to pick different specialists for multi-service bundles. All selected services will be booked as a single consolidated session.',
                'triggerOn': 'uncheck'
            }
        };

        const impact = impacts[name];
        if (!impact) return;

        // Determine if we should trigger the modal
        let isTriggered = false;
        if (impact.triggerOn === 'check' && isChecked) isTriggered = true;
        if (impact.triggerOn === 'uncheck' && !isChecked) isTriggered = true;

        if (isTriggered) {
            // Revert state temporarily while waiting for confirmation
            checkbox.prop('checked', !isChecked);
            
            const confirmed = await bcConfirm(impact.title, impact.msg);
            if (confirmed) {
                checkbox.prop('checked', isChecked);
            } else {
                checkbox.prop('checked', !isChecked);
            }
        }
    });

    // 7. Restore Defaults Confirmation
    $(document).on('click', '#bc-restore-defaults-btn', async function(e) {
        e.preventDefault();
        const confirmed = await bcConfirm(
            'Restore Factory Defaults?', 
            'This will revert all system settings, scheduling rules, and flow controls to their original values. This action cannot be undone.'
        );
        
        if (confirmed) {
            // Create a hidden input to submit the action and trigger the form
            $('<input>').attr({
                type: 'hidden',
                name: 'bc_restore_defaults',
                value: '1'
            }).appendTo($(this).closest('form'));
            
            $(this).closest('form').submit();
        }
    });
});

/**
 * Professional Separation: Financial Analysis Module
 */
function initBCRevenueChart() {
    if (typeof Chart === 'undefined') {
        setTimeout(initBCRevenueChart, 200);
        return;
    }
    const canvas = document.getElementById('bcRevenueChart');
    if (!canvas) return;

    const dataAttr = canvas.getAttribute('data-chart');
    if (!dataAttr) return;

    const rawData = JSON.parse(dataAttr);
    const ctx = canvas.getContext('2d');
    const labels = rawData.map(item => item.label);
    const values = rawData.map(item => parseFloat(item.val));

    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{
                label: 'Revenue',
                data: values.length ? values : [0],
                borderColor: '#6366f1',
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#94a3b8',
                    bodyColor: '#fff',
                    padding: 12,
                    borderColor: '#334155',
                    borderWidth: 1
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(255,255,255,0.05)' }, 
                    ticks: { color: '#94a3b8', font: { size: 11 } } 
                },
                x: { 
                    grid: { display: false }, 
                    ticks: { color: '#94a3b8', font: { size: 11 } } 
                }
            }
        }
    });
}

// Hook into tab loading
jQuery(document).on('bc-tab-loaded', function() {
    initBCRevenueChart();
});

// Initial load check
jQuery(document).ready(function() {
    initBCRevenueChart();
});
