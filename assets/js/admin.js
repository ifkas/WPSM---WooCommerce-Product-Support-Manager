(function ($) {
  "use strict";

  var TicketAdmin = {
    init: function () {
      this.bindEvents();
    },

    bindEvents: function () {
      $("#submit_ticket_reply").on("click", this.handleReplySubmission);
      $("#update_ticket_details").on("click", this.handleDetailsUpdate);
    },

    handleReplySubmission: function (e) {
      e.preventDefault();
      var $button = $(this);
      var $spinner = $button.siblings(".spinner");
      var reply = $("#ticket_reply").val();

      if (!reply) {
        alert(wpsmAdmin.i18n.pleaseEnterReply || "Please enter a reply message.");
        return;
      }

      TicketAdmin.sendAjaxRequest({
        button: $button,
        spinner: $spinner,
        data: {
          action: "wpsm_submit_reply",
          post_id: $("#post_ID").val(),
          reply: reply,
          mark_resolved: $("#mark_resolved").is(":checked") ? 1 : 0,
          nonce: wpsmAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#ticket_reply").val("");
            location.reload();
          }
        },
      });
    },

    handleDetailsUpdate: function (e) {
      e.preventDefault();
      var $button = $(this);
      var $spinner = $button.siblings(".spinner");

      TicketAdmin.sendAjaxRequest({
        button: $button,
        spinner: $spinner,
        data: {
          action: "wpsm_update_ticket_details",
          post_id: $("#post_ID").val(),
          priority: $("#ticket_priority").val(),
          status: $("#ticket_status").val(),
          nonce: wpsmAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            TicketAdmin.showNotice("success", response.data.message);
          }
        },
      });
    },

    sendAjaxRequest: function (options) {
      options.button.prop("disabled", true);
      options.spinner.addClass("is-active");

      $.ajax({
        url: wpsmAdmin.ajaxurl,
        type: "POST",
        data: options.data,
        success: function (response) {
          if (response.success) {
            if (options.success) {
              options.success(response);
            }
          } else {
            alert(response.data.message || "An error occurred");
          }
        },
        error: function () {
          alert("An error occurred while processing your request");
        },
        complete: function () {
          options.button.prop("disabled", false);
          options.spinner.removeClass("is-active");
        },
      });
    },

    showNotice: function (type, message) {
      var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + "</p></div>");
      var $parentDiv = $("#update_ticket_details").closest(".wpsm-ticket-details");

      // Remove any existing notices
      $(".notice", $parentDiv).remove();

      // Add new notice
      $notice.insertBefore($parentDiv.find(".wpsm-details-actions"));

      // Auto dismiss after 3 seconds
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 3000);
    },
  };

  $(document).ready(function () {
    TicketAdmin.init();
  });
})(jQuery);
