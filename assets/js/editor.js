var STAXVisibilityEditor = STAXVisibilityEditor || {};

(function ($) {
  // USE STRICT
  "use strict";

  STAXVisibilityEditor.vars = {
    checkCounter: 0,
  };

  STAXVisibilityEditor.fn = {
    initSelect2: function () {
      var ControlQuery = elementor.modules.controls.Select2.extend({
        cache: null,
        isTitlesReceived: false,

        getSelect2Placeholder: function getSelect2Placeholder() {
          var self = this;
          return {
            id: "",
            text: self.model.get("placeholder"),
          };
        },
        getSelect2DefaultOptions: function getSelect2DefaultOptions() {
          var self = this;
          return $.extend(
            elementor.modules.controls.Select2.prototype.getSelect2DefaultOptions.apply(
              this,
              arguments
            ),
            {
              ajax: {
                transport: function transport(params, success, failure) {
                  var data = {
                    q: params.data.q,
                    query_type: self.model.get("query_type"),
                    object_type: self.model.get("object_type"),
                  };
                  return elementorCommon.ajax.addRequest(
                    "stax_query_control_filter_autocomplete",
                    {
                      data: data,
                      success: success,
                      error: failure,
                    }
                  );
                },
                data: function data(params) {
                  return {
                    q: params.term,
                    page: params.page,
                  };
                },
                cache: true,
              },
              escapeMarkup: function escapeMarkup(markup) {
                return markup;
              },
              minimumInputLength: 1,
            }
          );
        },
        getValueTitles: function getValueTitles() {
          var self = this;
          var ids = this.getControlValue();
          var queryType = this.model.get("query_type");
          var objectType = this.model.get("object_type");

          if (!ids || !queryType) return;

          if (!_.isArray(ids)) {
            ids = [ids];
          }

          elementorCommon.ajax.loadObjects({
            action: "stax_query_control_value_titles",
            ids: ids,
            data: {
              query_type: queryType,
              object_type: objectType,
              unique_id: "" + self.cid + queryType,
            },
            success: function success(data) {
              self.isTitlesReceived = true;
              self.model.set("options", data);
              self.render();
            },
            before: function before() {
              self.addSpinner();
            },
          });
        },
        addSpinner: function addSpinner() {
          this.ui.select.prop("disabled", true);
          this.$el
            .find(".elementor-control-title")
            .after(
              '<span class="elementor-control-spinner stax-control-spinner">&nbsp;<i class="fa fa-spinner fa-spin"></i>&nbsp;</span>'
            );
        },
        onReady: function onReady() {
          setTimeout(
            elementor.modules.controls.Select2.prototype.onReady.bind(this)
          );
          if (this.ui.select) {
            var queryType = this.model.get("query_type");
            var objectType = this.model.get("object_type");

            $(this.ui.select).attr("data-query_type", queryType);

            if (objectType) {
              $(this.ui.select).attr("data-object_type", objectType);
            }
          }

          if (!this.isTitlesReceived) {
            this.getValueTitles();
          }
        },
        onBeforeDestroy: function onBeforeDestroy() {
          if (this.ui.select.data("select2")) {
            this.ui.select.select2("destroy");
          }

          this.$el.remove();
        },
      });

      // Add Control Handlers
      elementor.addControlView("stax_query", ControlQuery);
    },

    initStateDisplay: function () {
      // Update state on switcher toggle and section click
      $(document).on(
        "click",
        ".elementor-control-type-switcher .elementor-switch, .elementor-panel-heading",
        function () {
          setTimeout(function () {
            STAXVisibilityEditor.fn.checkState();
          }, 100);
        }
      );

      // Update state when element is selected in the panel
      elementor.channels.editor.on("section:activated", function () {
        setTimeout(function () {
          STAXVisibilityEditor.fn.checkState();
        }, 100);
      });

    },

    /**
     * Opening a section re-renders every control of the tab (Elementor's _renderChildren), so the panel loses its
     * scroll position and, with the long Visibility list, the opened section ends up below the fold. Remember
     * where the clicked heading was (capture phase, before Elementor re-renders) and put it back there.
     */
    initSectionScroll: function () {
      document.addEventListener(
        "click",
        function (event) {
          var heading =
            event.target && event.target.closest
              ? event.target.closest(
                  '#elementor-controls .elementor-control-type-section[class*="elementor-control-stax_visibility_"] .elementor-panel-heading'
                )
              : null;

          if (!heading) {
            return;
          }

          var wrapper = document.getElementById("elementor-panel-content-wrapper");
          var section = heading.closest(".elementor-control-type-section");
          var match = section.className.match(/elementor-control-(stax_visibility_[a-z0-9_]+)/);

          if (!wrapper || !match) {
            return;
          }

          var offset =
            heading.getBoundingClientRect().top - wrapper.getBoundingClientRect().top;

          STAXVisibilityEditor.fn.restoreSectionOffset(wrapper, match[1], offset);
        },
        true
      );
    },

    restoreSectionOffset: function (wrapper, name, offset) {
      var restore = function () {
        var heading = document.querySelector(
          "#elementor-controls .elementor-control-" + name + " .elementor-panel-heading"
        );

        if (!heading) {
          return;
        }

        var top = heading.getBoundingClientRect().top - wrapper.getBoundingClientRect().top;
        // Same place as before the click, but never so low that the opened controls start below the fold.
        var keep = Math.max(0, Math.min(offset, wrapper.clientHeight * 0.4));
        var target = wrapper.scrollTop + top - keep;
        var max = wrapper.scrollHeight - wrapper.clientHeight;

        wrapper.scrollTop = Math.max(0, Math.min(target, max));
      };

      // After Elementor's click handler (same tick) and once more after the controls laid out.
      setTimeout(restore, 0);
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(restore);
      });
    },

    checkState: function () {
      let options = window.visibility_widgets;

      if (options === undefined) {
        return;
      }

      let currentElement = elementor.getCurrentElement();

      if (currentElement) {
        $.each(options, function (i, item) {
          if (
            currentElement.$el.hasClass("stax-" + item.name + "_enabled-yes")
          ) {
            item.status = "active";
          } else {
            item.status = "";
          }
        });

        $("#elementor-controls .elementor-control-type-section").each(function (
          index,
          item
        ) {
          $(item).removeClass("stax-status-active");

          let _this = $(item);

          $.each(options, function (i, optionItem) {
            if (
              optionItem.status === "active" &&
              _this.hasClass(
                "elementor-control-stax_visibility_" +
                  optionItem.name +
                  "_section"
              )
            ) {
              _this.addClass("stax-status-active");
            }
          });
        });
      }

      STAXVisibilityEditor.vars.checkCounter = 0;
    },
  };

  $(window).on("elementor:init", function () {
    STAXVisibilityEditor.fn.initSelect2();
    STAXVisibilityEditor.fn.initStateDisplay();
    STAXVisibilityEditor.fn.initSectionScroll();

    // Check state when any element panel opens
    elementor.hooks.addAction('panel/open_editor/widget', function () {
      setTimeout(function () {
        STAXVisibilityEditor.fn.checkState();
      }, 200);
    });
    elementor.hooks.addAction('panel/open_editor/section', function () {
      setTimeout(function () {
        STAXVisibilityEditor.fn.checkState();
      }, 200);
    });
    elementor.hooks.addAction('panel/open_editor/container', function () {
      setTimeout(function () {
        STAXVisibilityEditor.fn.checkState();
      }, 200);
    });
  });


  $(window).on("panel:init", function () {
    var targetNode = document.getElementById('elementor-controls');
    
    if (targetNode) {
      var config = { childList: true, subtree: true };

      var callback = function (mutationsList, observer) {
        for (var mutation of mutationsList) {
          if (mutation.type === 'childList') {
            if (STAXVisibilityEditor.vars.checkCounter < 1) {
              setTimeout(function () {
                STAXVisibilityEditor.fn.checkState();
              }, 100);

              STAXVisibilityEditor.vars.checkCounter = 1;
            }
          }
        }
      };

      var observer = new MutationObserver(callback);
      observer.observe(targetNode, config);
    }
    
  });


})(jQuery);
