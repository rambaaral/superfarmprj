(function reviewDashboard($) {
  "use strict";

  function saveReviewCompletion(response) {
    try {
      const reviewKey =
        "rankingcoach-review-dashboard:" + window.location.pathname;
      window.sessionStorage.removeItem(reviewKey);
      if (
        response &&
        Number.isInteger(response.scanned) &&
        response.scanned > 0 &&
        response.review &&
        response.review.completed === true &&
        response.review.milestone === true
      ) {
        window.sessionStorage.setItem(
          reviewKey,
          JSON.stringify({
            completedAt: Date.now(),
            nonce: window.RankingCoachDashboardWidget.nonce,
          }),
        );
      }
    } catch {}
  }

  $(document).ready(function initializeReviewScan() {
    $("#rankingcoach-scan-button").on("click", function handleReviewScan(e) {
      e.preventDefault();
      const $button = $(this);
      if ($button.prop("disabled")) {
        return;
      }

      saveReviewCompletion(null);
      $button
        .prop("disabled", true)
        .text(window.RankingCoachDashboardWidget.scanningText);

      $.ajax({
        url: window.RankingCoachDashboardWidget.restUrl,
        type: "POST",
        beforeSend: function prepareReviewScan(xhr) {
          xhr.setRequestHeader(
            "X-WP-Nonce",
            window.RankingCoachDashboardWidget.nonce,
          );
        },
        success: function completeReviewScan(response, textStatus, xhr) {
          saveReviewCompletion(xhr.status === 200 ? response : null);
          window.location.reload();
        },
        error: function failReviewScan() {
          saveReviewCompletion(null);
          window.location.reload();
        },
      });
    });
  });
})(window.jQuery);
