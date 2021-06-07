var STAXVisibilityAdmin = STAXVisibilityAdmin || {};

(function ($) {
  // USE STRICT
  "use strict";

  STAXVisibilityAdmin.fn = {
    init: function () {
      STAXVisibilityAdmin.fn.rocketAnimation();
    },

    rocketAnimation: function () {
      const createSVGElement = (s) =>
        document.createElementNS("http://www.w3.org/2000/svg", s);

      // alias the querySelector
      const $ = (s) => document.querySelector(s);

      // used to easily give a number between x and y
      const randomRange = (min, max) => Math.random() * (max - min) + min;

      // generate a <circle> element with a random radius and x and y position
      const createCircle = () => {
        const $circle = createSVGElement("circle");
        $circle.setAttribute("r", randomRange(0.5, 2));
        $circle.setAttribute("fill", "#AAB7C4");
        $circle.setAttribute("cx", randomRange(0, 75));
        $circle.setAttribute("cy", randomRange(0, 75));
        return $circle;
      };

      // grab some of the DOM elements needed
      const $rocket = $("#go-pro-rocket-icon");

      if ($rocket == null) {
        return;
      }

      const $top = $rocket.querySelector("*");
      const $flame = $(".rocket-flame");

      // generate a set of a transforms that randomly scales the width and height
      // of the rocket’s flame
      const flicker = Array.from({ length: 20 }).map(() => ({
        transform: `scale(${randomRange(0.9, 1.2)}, ${randomRange(0.9, 1.2)})`,
      }));
      $flame.animate(flicker, { duration: 750, iterations: Infinity });

      // create and insert the stars (circles) to the SVG
      const $stars = Array.from({ length: 10 }).map(() => createCircle());
      $stars.forEach(($star) => $rocket.insertBefore($star, $top));

      // animate the stars
      const across = [
        { cx: "75px", fillOpacity: 0 },
        { fillOpacity: 1 },
        { cx: "0", fillOpacity: 0 },
      ];
      $stars.forEach(($star) => {
        const duration = randomRange(1000, 2000);
        $star.animate(across, { duration, iterations: Infinity });
      });
    },
  };

  STAXVisibilityAdmin.fn.init();
})(jQuery);
