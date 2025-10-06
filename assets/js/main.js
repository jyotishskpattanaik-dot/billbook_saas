(function ($) {
    "use strict";
    var windowOn = $(window);

    /* Windows Load */
    windowOn.on('load', function () {
        preLoader();
        wowAnimation();
    });

    /* Preloader activation */
    function preLoader() {
        $('#loading').delay(500).fadeOut(500);
    }

    /* Wow Active */
    function wowAnimation() {
        var wow = new WOW({
            boxClass: 'wow',
            animateClass: 'animated',
            offset: 0,
            mobile: false,
            live: true
        });
        wow.init();
    }

    /* footer year */
    var yearElement = document.getElementById("year");
    if (yearElement) { yearElement.innerHTML = new Date().getFullYear(); }
    /* footer year */

    /* Menu nav activation */
    document.addEventListener('DOMContentLoaded', function () {
        var pgurl = window.location.href.substr(window.location.href.lastIndexOf("/") + 1);
        const navLinks = document.querySelectorAll('.ap-main-menu ul li a');
        navLinks.forEach(link => {
            if (link.getAttribute('href') === pgurl || link.getAttribute('href') === '') {
                link.classList.add('active');
                let parent = link.closest('li');
                while (parent) {
                    const parentLink = parent.querySelector('a');
                    if (parentLink && !parentLink.classList.contains('active')) {
                        parentLink.classList.add('active');
                    }
                    parent = parent.closest('ul')?.closest('li');
                }
            }
        });
    });

    /* Mobile Menu Js */
    $(function () {
        var apMenuWrap = $('.ap-mobile-menu-active > ul').clone();
        var apSideMenu = $('.ap-offcanvas-menu nav');
        apSideMenu.append(apMenuWrap);
        if ($(apSideMenu).find('.submenu, .mega-menu').length != 0) {
            $(apSideMenu).find('.submenu, .mega-menu').parent().append('<div class="ap-menu-close"><i class="icon-angle-right"></i></div>');
        }
        var sideMenuList = $('.ap-offcanvas-menu nav > ul > li div.ap-menu-close, .ap-offcanvas-menu nav > ul li.has-dropdown > a');
        $(sideMenuList).on('click', function (e) {
            e.preventDefault();
            var $this = $(this).parent();
            var $siblings = $this.siblings('li');
            if (!$this.hasClass('active')) {
                $('.ap-offcanvas-menu nav > ul > li> ul > li.active').removeClass('active').children('.submenu, .mega-menu').slideUp();
                $this.addClass('active').children('.submenu, .mega-menu').slideDown();
                $siblings.removeClass('active').children('.submenu, .mega-menu').slideUp();
            } else {
                $this.removeClass('active').children('.submenu, .mega-menu').slideUp();
            }
        });

        /* Sidebar toggle */
        $('.ap-sidebar-toggle-icon .bar-icon').on('click', function () {
            $('.ap-offcanvas-menu').toggleClass('active');
        });
    });
    /* Menu add class */
    $('.ap-main-menu > ul > li>,submenu').slice(-2).addClass('last-children');
    /* One Page Scroll Js */
    if ($('.ap-onepage-menu ul li a').length > 0) {
        function scrollNav() {
            $('.ap-onepage-menu ul li a').on('click', function () {
                $(".ap-onepage-menu ul li a.active").removeClass("active");
                $(this).addClass("active");

                $('html, body').stop().animate({
                    scrollTop: $($(this).attr('href')).offset().top - 40
                }, 300);
                return false;
            });
        }
        scrollNav();
    }

    /* Sidebar Toggle */
    $(".ap-offcanvas-close,.ap-offcanvas-overlay").on("click", function () {
        $(".ap-offcanvas-area").removeClass("info-open");
        $(".ap-offcanvas-overlay").removeClass("overlay-open");
    });
    $(".ap-sidebar-toggle-icon").on("click", function () {
        $(".ap-offcanvas-area").addClass("info-open");
        $(".ap-offcanvas-overlay").addClass("overlay-open");
    });

    /* Body overlay Js */
    $(".body-overlay").on("click", function () {
        $(".ap-offcanvas-area").removeClass("opened");
        $(".body-overlay").removeClass("opened");
    });

    /* Sticky Header Js */
    windowOn.on('scroll', function () {
        var scroll = $(window).scrollTop();
        if (scroll < 250) {
            $("#header-sticky").removeClass("ap-header-sticky");
        } else {
            $("#header-sticky").addClass("ap-header-sticky");
        }
    });

    /* Data Css js */
    $("[data-background").each(function () {
        $(this).css(
            "background-image",
            "url( " + $(this).attr("data-background") + "  )"
        );
    });
    $("[data-width]").each(function () {
        $(this).css("width", $(this).attr("data-width"));
    });
    $("[data-bg-color]").each(function () {
        $(this).css("background-color", $(this).attr("data-bg-color"));
    });

    /* MagnificPopup image view */
    $(".popup-image").magnificPopup({
        type: "image",
        gallery: {
            enabled: true,
        },
    });

    /* MagnificPopup video view */
    $(".popup-video").magnificPopup({
        type: "iframe",
    });

    /* Nice Select Js */
    $("select").niceSelect();

    /* Button scroll up js */
    $(function () {
        var progressPath = document.querySelector(".backtotop-wrap path");
        var pathLength = progressPath.getTotalLength();
        progressPath.style.transition = progressPath.style.WebkitTransition = "none";
        progressPath.style.strokeDasharray = pathLength + " " + pathLength;
        progressPath.style.strokeDashoffset = pathLength;
        progressPath.getBoundingClientRect();
        progressPath.style.transition = progressPath.style.WebkitTransition = "stroke-dashoffset 10ms linear";
        var updateProgress = function () {
            var scroll = $(window).scrollTop();
            var height = $(document).height() - $(window).height();
            var progress = pathLength - (scroll * pathLength) / height;
            progressPath.style.strokeDashoffset = progress;
        };
        updateProgress();
        $(window).on('scroll', updateProgress);
        var offset = 150;
        var duration = 550;
        $(window).on("scroll", function () {
            if ($(this).scrollTop() > offset) {
                $(".backtotop-wrap").addClass("active-progress");
            } else {
                $(".backtotop-wrap").removeClass("active-progress");
            }
        });
        $(".backtotop-wrap").on("click", function (event) {
            event.preventDefault();
            $("html, body").animate({
                scrollTop: 0
            }, parseInt(duration, 10));
            return false;
        });
    });

    /* PureCounter Js */
    new PureCounter();
    new PureCounter({
        filesizing: true,
        selector: ".filesizecount",
        pulse: 2,
    });

    /* testimonial Slide Active */
    var testimonialSlideActive = new Swiper(".testimonialSlideActive", {
        slidesPerView: 3,
        spaceBetween: 30,
        loop: true,
        allowTouchMove: true,
        observer: true,
        pagination: {
            el: ".ap-dots-pagination",
            clickable: true,
        },
        breakpoints: {
            1400: { slidesPerView: 3 },
            1200: { slidesPerView: 2 },
            992: { slidesPerView: 2 },
            768: { slidesPerView: 1 },
            576: { slidesPerView: 1 },
            0: { slidesPerView: 1 },
        },
    });

    /* testimonial Slide ActiveTwo */
    var testimonialSlideActiveTwoThumbs = new Swiper(".testimonialSlideActiveTwoThumbs", {
        spaceBetween: 20,
        slidesPerView: 4,
    });
    var testimonialSlideActiveTwo = new Swiper(".testimonialSlideActiveTwo", {
        spaceBetween: 10,
        autoplay: true,
        loop: true,
        thumbs: { swiper: testimonialSlideActiveTwoThumbs },
    });

    /* testimonial Slide ActiveThree */
    var testimonialSlideActiveThree = new Swiper(".testimonialSlideActiveThree", {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: true,
        allowTouchMove: true,
        observer: true,
        navigation: {
            nextEl: ".testimonial-navigation-next",
            prevEl: ".testimonial-navigation-prev",
        },
    });

    /* pricing monthly & yearly activation */
    $(function () {
        $('#monthly-price-content.ap-pricing-wrapper').show();
        $('#yearly-price-content.ap-pricing-wrapper').hide();
        $('.ap-pricing-switch-title #monthly-price').addClass('toggler-price-active');
        $('.ap-pricing-switch-title #yearly-price').removeClass('toggler-price-active');

        $('.ap-pricing-switch-btn input[type="checkbox"]').on('change', function () {
            if ($(this).is(':checked')) {
                $('#monthly-price-content.ap-pricing-wrapper').show();
                $('#yearly-price-content.ap-pricing-wrapper').hide();
                $('.ap-pricing-switch-title #monthly-price').addClass('toggler-price-active');
                $('.ap-pricing-switch-title #yearly-price').removeClass('toggler-price-active');
            } else {
                $('#monthly-price-content.ap-pricing-wrapper').hide();
                $('#yearly-price-content.ap-pricing-wrapper').show();
                $('.ap-pricing-switch-title #monthly-price').removeClass('toggler-price-active');
                $('.ap-pricing-switch-title #yearly-price').addClass('toggler-price-active');
            }
        });

        $('.ap-pricing-switch-title #monthly-price').on('click', function () {
            $('.ap-pricing-switch-btn input[type="checkbox"]').prop('checked', true).trigger('change');
        });

        $('.ap-pricing-switch-title #yearly-price').on('click', function () {
            $('.ap-pricing-switch-btn input[type="checkbox"]').prop('checked', false).trigger('change');
        });
    });

    /* brand activation slide active */
    var brandActivation = new Swiper(".brandActivation", {
        slidesPerView: 6,
        spaceBetween: 30,
        loop: true,
        allowTouchMove: true,
        observer: true,
        pagination: {
            el: ".ap-dots-pagination",
            clickable: true,
        },
        breakpoints: {
            1400: { slidesPerView: 6 },
            1200: { slidesPerView: 6 },
            992: { slidesPerView: 4 },
            768: { slidesPerView: 3 },
            576: { slidesPerView: 3 },
            0: { slidesPerView: 2 },
        },
    });

    /* ap-radial-progress activation */
    $(function () {
        $(window).on('scroll', function () {
            $('.ap-radial-progress').each(function () {
                var bottom_of_object = $(this).offset().top + $(this).outerHeight();
                var bottom_of_window = $(window).scrollTop() + $(window).height();
                if (bottom_of_window > bottom_of_object) {
                    $(this).easyPieChart({
                        lineWidth: 6,
                        scaleLength: 0,
                        rotate: 0,
                        trackColor: false,
                        lineCap: 'round',
                        size: 70,
                        onStep: function (from, to, percent) {
                            $(this.el).closest('.ap-radial-progress-single').find('.count').text(Math.round(percent));
                        }
                    });
                }
            });
        });
        $(window).trigger('scroll');
    });

    /* Post Slider Activation */
    var blogGalleryActivation = new Swiper(".blogGalleryActivation", {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: true,
        allowTouchMove: true,
        observer: true,
        pagination: {
            el: ".ap-dots-pagination",
            clickable: true,
        },
        navigation: {
            nextEl: ".post-navigation-next",
            prevEl: ".post-navigation-prev",
        },
    });

    /* Cart plus minus */
    $('.minus').on('click', function () {
        var $input = $(this).parent().find('input');
        var count = parseInt($input.val(), 10) - 1;
        count = count < 1 ? 1 : count;
        $input.val(count).change();
        return false;
    });

    $('.plus').on('click', function () {
        var $input = $(this).parent().find('input');
        $input.val(parseInt($input.val(), 10) + 1).change();
        return false;
    });

    /* Show Login Toggle Js */
    $('.ap-checkout-login-form-reveal-btn').on('click', function () {
        $('#returnCustomerLoginForm').slideToggle(400);
    });

    /* Show Coupon Toggle Js */
    $('.ap-checkout-coupon-form-reveal-btn').on('click', function () {
        $('#apCheckoutCouponForm').slideToggle(400);
    });

    /* Create An Account Toggle Js */
    $('#cbox').on('click', function () {
        $('#cbox_info').slideToggle(900);
    });

    /* Shipping Box Toggle Js */
    $('#ship-box').on('click', function () {
        $('#ship-box-info').slideToggle(1000);
    });

    /* Price Range Slider */
    $(function () {
        $("#slider-range").slider({
            range: true,
            min: 0,
            max: 500,
            values: [75, 300],
            slide: function (event, ui) {
                $("#amount").val("$" + ui.values[0] + " - $" + ui.values[1]);
            }
        });
        $("#amount").val("$" + $("#slider-range").slider("values", 0) +
            " - $" + $("#slider-range").slider("values", 1));
    });

    /* Team Skill bars on view */
    const bars = document.querySelectorAll('.ap-progress > i');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                const el = e.target; el.style.width = el.style.getPropertyValue('--target') || '0%';
            }
        });
    }, { threshold: 0.3 });
    bars.forEach(b => io.observe(b));



})(jQuery);