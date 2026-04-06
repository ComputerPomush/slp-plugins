jQuery(function ($) {
    /**
     * Send and AJAX request and process the response.
     *
     * @param action
     * @param callback
     */
    slp.send_ajax = function (action, callback) {
        $.post(
            slplus.ajaxurl,
            action,
            function (response) {
                try {
                    response = JSON.parse(response);
                } catch (ex) {
                }
                callback(response);
            }
        );
    };

    $(document).ready(slp.run);
});
