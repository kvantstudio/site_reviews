/**
 * @file
 * Misc JQuery scripts in this file
 */
(function($, window, Drupal, drupalSettings) {

    'use strict';

    if ($('.review-create-form').length) {
        var topPos = $('.review-create-form').first().offset().top;
        $(window).scroll(function() {
            if (screen.width > 767) {
                var top = $(document).scrollTop() - 100;
                if (top > topPos) $('.review-create-form').addClass('review-create-form_fixed');
                else $('.review-create-form').removeClass('review-create-form_fixed');
            }
        });
    }

})(jQuery, window, Drupal, drupalSettings);