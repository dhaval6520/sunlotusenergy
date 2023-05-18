jQuery(function ($) {
    $.imageBox = function (el) {
        var $widget = $(el);
        var methods;

        // Store a reference to the object
        $.data(el, "imageBox", $widget);
        // Private methods
        methods = {
            init: function () {
                $widget.layzrInitialisation();
            }
        };

        $widget.delete = function () {
            $widget.removeData("imageBox");
        };
        methods.init();
    };
    $.fn.imageBox = function () {
        return this.each(function () {
            var widgetData = $(this).data('imageBox');
            if (widgetData !== undefined) {
                widgetData.delete();
            }
            new $.imageBox(this);
        });
    };

});
(function ($) {
    // Make sure you run this code under Elementor.
    $(window).on("elementor/frontend/init", function () {
        elementorFrontend.hooks.addAction("frontend/element_ready/the7_image_box_widget.default", function ($widget, $) {
            $(document).ready(function () {
                $widget.imageBox();
            })
        });
        elementorFrontend.hooks.addAction("frontend/element_ready/the7_image_box_grid_widget.default", function ($widget, $) {
            $(document).ready(function () {
                $widget.imageBox();
            })
        });
    });
})(jQuery);
