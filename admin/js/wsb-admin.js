jQuery(document).ready(function($) {
    // 1. Master Tab Navigation (SPA)
    $(document).on('click', '.wsb-nav-item[data-tab]', function() {
        var tab = $(this).data('tab');
        var sidebarItem = $(this);

        // UI Feedback
        $('.wsb-nav-item').removeClass('active');
        sidebarItem.addClass('active');

        loadTab(tab);
    });

    function loadTab(tab, extraParams = '') {
        $('.wsb-loader').fadeIn('fast');
        $('#wsb-ajax-response').css('opacity', '0.5');

        var targetUrl = wsb_admin_ajax.ajax_url;
        
        $.ajax({
            url: targetUrl,
            type: 'POST',
            data: {
                action: 'wsb_load_admin_tab',
                nonce: wsb_admin_ajax.nonce,
                tab: tab,
                params: extraParams
            },
            success: function(response) {
                if (response.success) {
                    try {
                        $('#wsb-ajax-response').html(response.data.content);
                        $(document).trigger('wsb-tab-loaded', [tab]);
                        
                        // Update URL without reload
                        var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=wsb_main&tab=' + tab + extraParams;
                        window.history.pushState({path: newUrl}, '', newUrl);
                    } catch (e) {
                        console.error('WSB Render Error:', e);
                    }
                } else {
                    console.error('WSB AJAX Error: Success was false.', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('WSB AJAX Network Error:', status, error);
            },
            complete: function() {
                $('.wsb-loader').fadeOut('fast');
                $('#wsb-ajax-response').css('opacity', '1');
            }
        });
    }

    // Intercept internal links for SPA feel
    $(document).on('click', '.wsb-master-content a', function(e) {
        var href = $(this).attr('href');
        if (href && (href.indexOf('page=wsb_main') !== -1 || href.indexOf('page=wp-service-booking') !== -1) && !$(this).hasClass('wsb-no-ajax')) {
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
            $('.wsb-nav-item').removeClass('active');
            $('.wsb-nav-item[data-tab="' + tab + '"]').addClass('active');

            loadTab(tab, extra);
        }
    });

    // 2. Featured Image & Gallery Logic
    $(document).on('click', '.wsb-select-image, .wsb-select-gallery', function(e) {
        e.preventDefault();
        var button = $(this);
        var isMultiple = button.hasClass('wsb-select-gallery');
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
    $(document).on('submit', '.wsb-master-content form', function(e) {
        if ($(this).hasClass('wsb-no-ajax')) return;
        e.preventDefault();
        
        var form = $(this);
        var formData = new FormData(form[0]);
        var activeTab = $('.wsb-nav-item.active').data('tab') || 'dashboard';
        
        // Ensure action and nonce
        if (!formData.has('action')) formData.append('action', 'wsb_load_admin_tab');
        if (!formData.has('nonce')) formData.append('nonce', wsb_admin_ajax.nonce);
        
        // Prioritize tab from form if it exists (e.g. filter forms), otherwise use active tab
        var tabToLoad = formData.get('tab') || activeTab;
        if (!formData.has('tab')) formData.append('tab', tabToLoad);
        
        $('.wsb-loader').fadeIn('fast');
        $.ajax({
            url: wsb_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#wsb-ajax-response').html(response.data.content);
                    $(document).trigger('wsb-tab-loaded', [activeTab]);
                    
                    // Update URL with filter params for shareability/refresh
                    var queryString = form.serialize();
                    var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + queryString;
                    window.history.pushState({path: newUrl}, '', newUrl);
                }
                $('.wsb-loader').fadeOut('fast');
            },
            error: function() {
                console.error('WSB Error: Form submission failed.');
            },
            complete: function() {
                $('.wsb-loader').fadeOut('fast');
            }
        });
    });
    // Stripe Connection Tester
    $(document).on('click', '#wsb-test-stripe-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var secretKey = $('#wsb_stripe_secret_key').val();
        var spinner = $('#wsb-stripe-test-spinner');
        var resultBox = $('#wsb-stripe-test-result');

        if (!secretKey) {
            resultBox.css({'color': '#ef4444', 'display': 'block'}).text('Enter a Secret Key first!');
            return;
        }

        btn.prop('disabled', true);
        spinner.fadeIn(150);
        resultBox.hide();

        $.ajax({
            url: wsb_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsb_test_stripe_connection',
                nonce: wsb_admin_ajax.nonce,
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
    $(document).on('click', '.wsb-clickable-row', function(e) {
        // Don't trigger if clicking an actual link, button, or the action container
        if ($(e.target).closest('.wsb-row-actions, a, button, input, select').length) return;
        
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
            $('.wsb-nav-item').removeClass('active');
            $('.wsb-nav-item[data-tab="' + tab + '"]').addClass('active');

            loadTab(tab, extra);
        }
    });

    // Handle History (Back/Forward)
    window.onpopstate = function(event) {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || 'dashboard';
        $('.wsb-nav-item').removeClass('active');
        $('.wsb-nav-item[data-tab="' + tab + '"]').addClass('active');
        loadTab(tab);
    };
});
