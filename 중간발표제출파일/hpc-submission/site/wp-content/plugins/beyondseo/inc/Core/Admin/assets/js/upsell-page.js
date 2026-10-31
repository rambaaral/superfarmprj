/**
 * RankingCoach Upsell Page JS
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Function to disable all upsell buttons
    function disableAllUpsellButtons() {
      $(".rankingcoach-plan .upgrade-button").each(function () {
        const $button = $(this);
        $button.addClass("disabled").prop("disabled", true);
        $button.text("Processing...");
      });
    }

    // Show more functionality for plan features
    function initShowMoreFunctionality() {
      let isExpanded = false;

      $(".show-more-btn").on("click", function (e) {
        e.preventDefault();
        
        const $button = $(this);
        const $icon = $button.find(".show-more-icon");
        const $text = $button.find(".show-more-text");
        
        if (!isExpanded) {
          // Expand all cards with hidden features
          $(".feature-hidden").addClass("show");
          $(".show-more-btn").addClass("expanded");
          $(".show-more-text").text("Show less");
          isExpanded = true;
        } else {
          // Collapse all cards
          $(".feature-hidden").removeClass("show");
          $(".show-more-btn").removeClass("expanded");
          $(".show-more-text").text("Show more");
          isExpanded = false;
        }
      });
    }

    // Initialize show more functionality
    initShowMoreFunctionality();

    // Swipeable assistant tabs (visibility / reputation / social)
    function initAssistantTabs() {
      $(".rankingcoach-assistant-tabs").each(function () {
        const container = this;
        const $tabs = $(container).find(".assistant-tab");
        const $panels = $(container).find(".assistant-tab-panel");
        const $track = $(container).find(".assistant-tabs-track");
        const $viewport = $(container).find(".assistant-tabs-viewport");
        const count = $tabs.length;

        if (!count) {
          return;
        }

        let activeIndex = parseInt(container.getAttribute("data-active-index"), 10) || 0;

        function syncHeight() {
          const panel = $panels.get(activeIndex);
          if (panel) {
            $viewport.css("height", panel.offsetHeight + "px");
          }
        }

        function setTransition(on) {
          $track.css("transition", on ? "" : "none");
        }

        function render() {
          $track.css("transform", "translateX(" + -activeIndex * 100 + "%)");
          $tabs.each(function (i) {
            const isActive = i === activeIndex;
            $(this).toggleClass("active", isActive).attr("aria-selected", isActive ? "true" : "false");
          });
          $panels.each(function (i) {
            const isActive = i === activeIndex;
            $(this).toggleClass("active", isActive);
            if (isActive) {
              $(this).removeAttr("aria-hidden");
            } else {
              $(this).attr("aria-hidden", "true");
            }
          });
          syncHeight();
          container.setAttribute("data-active-index", activeIndex);
        }

        function goTo(index) {
          activeIndex = Math.max(0, Math.min(count - 1, index));
          setTransition(true);
          render();
        }

        // Delegated click so switching is robust even if the DOM is re-rendered
        $(container).on("click", ".assistant-tab", function () {
          goTo($tabs.index(this));
        });

        $tabs.on("keydown", function (e) {
          if (e.key === "ArrowRight") {
            e.preventDefault();
            goTo(activeIndex + 1);
            $tabs.get(activeIndex).focus();
          } else if (e.key === "ArrowLeft") {
            e.preventDefault();
            goTo(activeIndex - 1);
            $tabs.get(activeIndex).focus();
          }
        });

        // Pointer/touch swipe
        let dragging = false;
        let startX = 0;
        let deltaX = 0;
        let width = 0;

        function onDown(e) {
          dragging = true;
          startX = (e.touches ? e.touches[0].clientX : e.clientX);
          deltaX = 0;
          width = $viewport.get(0).clientWidth || 1;
          setTransition(false);
        }

        function onMove(e) {
          if (!dragging) {
            return;
          }
          const x = (e.touches ? e.touches[0].clientX : e.clientX);
          deltaX = x - startX;
          // Add resistance at the edges
          if ((activeIndex === 0 && deltaX > 0) || (activeIndex === count - 1 && deltaX < 0)) {
            deltaX *= 0.3;
          }
          const percent = (-activeIndex * width + deltaX) / width * 100;
          $track.css("transform", "translateX(" + percent + "%)");
        }

        function onUp() {
          if (!dragging) {
            return;
          }
          dragging = false;
          const threshold = Math.max(60, width * 0.15);
          if (deltaX <= -threshold) {
            goTo(activeIndex + 1);
          } else if (deltaX >= threshold) {
            goTo(activeIndex - 1);
          } else {
            goTo(activeIndex);
          }
        }

        const vp = $viewport.get(0);
        vp.addEventListener("touchstart", onDown, { passive: true });
        vp.addEventListener("touchmove", onMove, { passive: true });
        vp.addEventListener("touchend", onUp);
        vp.addEventListener("mousedown", function (e) {
          onDown(e);
          e.preventDefault();
        });
        $(document).on("mousemove.assistantTabs", onMove);
        $(document).on("mouseup.assistantTabs", onUp);

        $(window).on("resize.assistantTabs", function () {
          setTransition(false);
          render();
          // Re-enable transition on next frame
          window.requestAnimationFrame(function () {
            setTransition(true);
          });
        });

        // Recompute height once images/fonts have loaded
        $(window).on("load", syncHeight);

        render();
        // Height needs a layout pass to measure correctly
        window.requestAnimationFrame(syncHeight);
      });
    }

    initAssistantTabs();

    $(".rankingcoach-plan .upgrade-button").each(function () {
      const $button = $(this);
      const $plan = $button.closest(".rankingcoach-plan");
      const $checkbox = $plan.find('input[type="checkbox"]');
      const $termsDiv = $plan.find(".plan-terms");

      // Function to remove error message
      function removeErrorMessage() {
        $termsDiv.find(".terms-error-message").remove();
      }

      if ($checkbox.length > 0) {
        $button.on("click", function (e) {
          removeErrorMessage();
          if (!$checkbox.is(":checked")) {
            e.preventDefault();
            // Create and display the error message
            const $errorMessage = $("<div>")
              .addClass("terms-error-message")
              .text(
                "Please accept the Terms & Conditions and Privacy Policy to proceed."
              );
            $termsDiv.append($errorMessage);
            $checkbox.focus();
          } else {
            // Terms are accepted, disable all buttons and proceed
            disableAllUpsellButtons();
            // Add a small delay to show the "Processing..." state before redirect
            setTimeout(function () {
              window.location.href = $button.attr("href");
            }, 500);
            e.preventDefault();
          }
        });

        // Remove error message when checkbox is checked
        $checkbox.on("change", function () {
          if ($(this).is(":checked")) {
            removeErrorMessage();
          }
        });
      } else {
        // No checkbox required, just disable on click
        $button.on("click", function (e) {
          disableAllUpsellButtons();
          // Add a small delay to show the "Processing..." state before redirect
          setTimeout(function () {
            window.location.href = $button.attr("href");
          }, 500);
          e.preventDefault();
        });
      }
    });
  });
})(jQuery);
