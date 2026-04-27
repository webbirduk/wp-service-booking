jQuery(document).ready(function($) {
    // Featured Image Uploader (Event Delegation)
    var featuredFrame;
    $(document).on('click', '.wsb-select-image', function(e) {
        e.preventDefault();
        var button = $(this);
        var targetInput = $(button.data('target'));
        var previewDiv = $(button.data('preview'));

        if (featuredFrame) {
            featuredFrame.open();
            return;
        }

        featuredFrame = wp.media({
            title: 'Select Featured Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        featuredFrame.on('select', function() {
            var attachment = featuredFrame.state().get('selection').first().toJSON();
            targetInput.val(attachment.url);
            previewDiv.css('background', '#0f172a url(' + attachment.url + ') center/cover');
        });

        featuredFrame.open();
    });

    // Multiple Gallery Images Uploader
    var galleryFrame;
    $(document).on('click', '.wsb-select-gallery', function(e) {
        e.preventDefault();

        if (galleryFrame) {
            galleryFrame.open();
            return;
        }

        galleryFrame = wp.media({
            title: 'Select Gallery Images',
            button: { text: 'Add to Gallery' },
            multiple: true
        });

        galleryFrame.on('select', function() {
            var selection = galleryFrame.state().get('selection');
            var urls = [];
            var previewHtml = '';

            selection.map(function(attachment) {
                attachment = attachment.toJSON();
                urls.push(attachment.url);
                previewHtml += '<div style="width:50px; height:50px; border-radius:4px; background:url(' + attachment.url + ') center/cover; border:1px solid #334155;"></div>';
            });

            $('#service_gallery_urls').val(urls.join(','));
            $('#wsb-gallery-preview').html(previewHtml);
        });

        galleryFrame.open();
    });

    function showLoader() {
        if ($('#wsb-ajax-loader').length === 0) {
            $('body').append('<div id="wsb-ajax-loader" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.7); z-index:999999; display:flex; justify-content:center; align-items:center;"><div style="color:#3b82f6; font-size:24px; font-weight:bold; font-family:Inter, sans-serif;">Loading...</div></div>');
        }
        $('#wsb-ajax-loader').fadeIn('fast');
    }

    function hideLoader() {
        $('#wsb-ajax-loader').fadeOut('fast');
    }

    function navigateTo(href) {
        if (!href || href.indexOf('page=wp-service-booking') === -1) return false;
        
        showLoader();
        $.ajax({
            url: href,
            method: 'GET',
            success: function(response) {
                var newContent = $(response).find('.wsb-admin-wrap');
                if(newContent.length) {
                    $('.wsb-admin-wrap').replaceWith(newContent);
                    window.history.pushState(null, '', href);
                    // Scroll to top
                    window.scrollTo(0, 0);
                    // Force re-initialization of components if needed
                    $(document).trigger('wsb-page-loaded');
                } else {
                    window.location.href = href; // fallback
                }
                hideLoader();
            },
            error: function() {
                window.location.href = href; 
                hideLoader();
            }
        });
        return true;
    }

    // Intercept internal plugin navigation links (scoped carefully so it does not break WP Menu clicks)
    $(document).on('click', '.wsb-admin-wrap a', function(e) {
        var href = $(this).attr('href');
        // Only intercept if href contains our plugin page target
        if (href && href.indexOf('page=wp-service-booking') !== -1 && !$(this).hasClass('wsb-no-ajax')) {
            // Respect native JS confirms (Action links)
            if($(this).attr('onclick')) {
                // We let the onclick handle it. If it returns false, navigation shouldn't happen.
                // But for AJAX, we need to be careful.
            }
            e.preventDefault();
            navigateTo(href);
        }
    });

    // Intercept inline plugin form submissions
    $(document).on('submit', '.wsb-admin-wrap form', function(e) {
        e.preventDefault();
        var form = $(this);
        var action = form.attr('action') || window.location.href;
        var formData = new FormData(form[0]); // Support file uploads if any

        showLoader();
        $.ajax({
            url: action,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                 var newContent = $(response).find('.wsb-admin-wrap');
                 if(newContent.length) {
                     $('.wsb-admin-wrap').replaceWith(newContent);
                     // If form submission results in a redirect-like behavior (e.g. going back to list)
                     // we might want to update the URL if the response has a different origin action.
                 } else {
                     window.location.reload(); 
                 }
                 hideLoader();
            },
            error: function() {
                 window.location.reload();
            }
        });
    });

    // Clickable table rows
    $(document).on('click', '.wsb-clickable-row', function(e) {
        // Prevent click if clicking on an anchor tag, button, or input inside the row
        if ($(e.target).closest('a, button, input, select').length) return;
        
        var href = $(this).attr('data-href');
        if (href) {
            navigateTo(href);
        }
    });
});
