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
      $(document).on(
        "click",
        ".elementor-control-type-switcher .elementor-switch",
        function () {
          setTimeout(function () {
            STAXVisibilityEditor.fn.checkState();
          }, 100);
        }
      );

      $(document).on("DOMSubtreeModified", "#elementor-controls", function () {
        if (STAXVisibilityEditor.vars.checkCounter < 1) {
          setTimeout(function () {
            STAXVisibilityEditor.fn.checkState();
          }, 100);

          STAXVisibilityEditor.vars.checkCounter = 1;
        }
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
            item.status = "inactive";
          }

          if (
            currentElement.$el.hasClass("stax-" + item.name + "_enabled-error")
          ) {
            item.status = "error";
          }
        });

        $("#elementor-controls .elementor-control-type-section").each(function (
          index,
          item
        ) {
          $(item)
            .removeClass("stax-status-active")
            .removeClass("stax-status-inactive")
            .removeClass("stax-status-error");

          let _this = $(item);

          $.each(options, function (i, optionItem) {
            if (
              _this.hasClass(
                "elementor-control-stax_visibility_" +
                  optionItem.name +
                  "_section"
              )
            ) {
              _this.addClass("stax-status-" + optionItem.status);
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
  });

  $(document).on("ready", function () {});
})(jQuery);
