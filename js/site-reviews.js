/**
 * @file
 * Misc JQuery scripts in this file
 */
(function($, window, Drupal, drupalSettings) {

    'use strict';

    if ($('.reviews__form-wrapper').length) {
        var topPos = $('.reviews__form-wrapper').first().offset().top;
        $(window).scroll(function() {
            if (screen.width > 1199) {
                var top = $(document).scrollTop() - 100;
                if (top > topPos) $('.reviews__form-wrapper').addClass('reviews__form-wrapper_fixed');
                else $('.reviews__form-wrapper').removeClass('reviews__form-wrapper_fixed');
            }
        });
    }

})(jQuery, window, Drupal, drupalSettings);