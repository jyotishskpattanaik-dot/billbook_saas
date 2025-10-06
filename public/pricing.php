<?php
require __DIR__ . '/../includes/public_db_helper.php';

$pdo = getPublicPDO();
$modules = [];

try {
    $stmt = $pdo->query("SELECT module_name FROM modules ORDER BY module_name ASC");
    $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Failed to fetch modules: " . $e->getMessage());
}
?>
<!-- Now your HTML starts here -->
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Pricing</title>
    <meta name="description" content="billbook.in.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Place favicon.ico in the root directory -->
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/logo/favicon.svg">
    <!-- CSS here -->
    <link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/animate.min.css">
    <link rel="stylesheet" href="../assets/css/plugins/swiper.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/magnific-popup.css">
    <link rel="stylesheet" href="../assets/css/vendor/icomoon.css">
    <link rel="stylesheet" href="../assets/css/vendor/spacing.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>

    <?php include 'preload.php'; ?>
    <?php include 'header.php'; ?>
   <?php include 'offcanvas_area.php'; ?>
      
    <!-- Body main wrapper start -->
    <main class="ap-main-area">

      <?php include 'pricing_module.php'; ?>
    <?php include 'testimonial_area.php'; ?>

           <!-- faq area start -->
        <section class="ap-faq-area section-space-bottom">
            <div class="ap-faq-shape d-none d-xl-block">
                <div class="ap-faq-shape-1"><img src="../assets/images/shape/faq-shape.webp" alt=""></div>
                <div class="ap-faq-shape-2"><img src="../assets/images/shape/faq-shape-2.webp" alt=""></div>
            </div>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xxl-6 col-xl-12">
                        <div class="wow apFadeInUp" data-wow-delay=".3s" data-wow-duration="1s">
                            <div class="ap-section-title-wrapper text-center section-title-space">
                                <h2 class="ap-section-title">Frequently asked questions</h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col-xl-10">
                        <div class="ap-common-faq ap-faq-content wow apFadeInUp" data-wow-delay=".4s" data-wow-duration="1s">
                            <div class="accordion" id="accordionExampleTwo">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="true" aria-controls="collapseThree">
                                            1. How does your pricing work?
                                        </button>
                                    </h2>
                                    <div id="collapseThree" class="accordion-collapse collapse show" data-bs-parent="#accordionExampleTwo">
                                        <div class="accordion-body">
                                            Our pricing is flexible and tailored to your needs. We offer multiple plans to suit
                                            different business sizes, with transparent costs and no hidden fees. Choose a monthly
                                            or annual subscription, and scale as your requirements grow. Contact us for custom
                                            solutions.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                            2. What forms of payments are available?
                                        </button>
                                    </h2>
                                    <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionExampleTwo">
                                        <div class="accordion-body">
                                            Our pricing is flexible and tailored to your needs. We offer multiple plans to suit
                                            different business sizes, with transparent costs and no hidden fees. Choose a monthly
                                            or annual subscription, and scale as your requirements grow. Contact us for custom
                                            solutions.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                            3. Is my data secure with your platform?
                                        </button>
                                    </h2>
                                    <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionExampleTwo">
                                        <div class="accordion-body">
                                            Our pricing is flexible and tailored to your needs. We offer multiple plans to suit
                                            different business sizes, with transparent costs and no hidden fees. Choose a monthly
                                            or annual subscription, and scale as your requirements grow. Contact us for custom
                                            solutions.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                                            4. Can i customize the design to match my brand?
                                        </button>
                                    </h2>
                                    <div id="collapseSix" class="accordion-collapse collapse" data-bs-parent="#accordionExampleTwo">
                                        <div class="accordion-body">
                                            Our pricing is flexible and tailored to your needs. We offer multiple plans to suit
                                            different business sizes, with transparent costs and no hidden fees. Choose a monthly
                                            or annual subscription, and scale as your requirements grow. Contact us for custom
                                            solutions.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                                            5. Can your solution integrate with my existing tools?
                                        </button>
                                    </h2>
                                    <div id="collapseSeven" class="accordion-collapse collapse" data-bs-parent="#accordionExampleTwo">
                                        <div class="accordion-body">
                                            Our pricing is flexible and tailored to your needs. We offer multiple plans to suit
                                            different business sizes, with transparent costs and no hidden fees. Choose a monthly
                                            or annual subscription, and scale as your requirements grow. Contact us for custom
                                            solutions.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="ap-faq-more"><a href="#">Still Have Questions? <i
                              class="icon-arrow-right-long"></i></a></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- faq area end -->

    </main>
    <!-- Body main wrapper end -->
    <?php include 'footer.php'; ?>
    
    <!-- back to top -->
    <div class="backtotop-wrap cursor-pointer">
        <svg class="backtotop-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>
    <!-- Backtotop end -->

    <!-- JS here -->
    <script src="../assets/js/vendor/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/vendor/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/plugins/rangeslider.min.js"></script>
    <script src="../assets/js/vendor/magnific-popup.min.js"></script>
    <script src="../assets/js/vendor/isotope.pkgd.min.js"></script>
    <script src="../assets/js/vendor/imagesloaded.pkgd.min.js"></script>
    <script src="../assets/js/vendor/ajax-form.js"></script>
    <script src="../assets/js/vendor/purecounter.js"></script>
    <script src="../assets/js/plugins/waypoints.min.js"></script>
    <script src="../assets/js/plugins/swiper.min.js"></script>
    <script src="../assets/js/plugins/wow.js"></script>
    <script src="../assets/js/plugins/nice-select.min.js"></script>
    <script src="../assets/js/plugins/easypie.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Free Trial with module
    const freeTrialBtn = document.querySelector('a[href^="checkout.php?plan=free_trial"]');
    const freeTrialModule = freeTrialBtn.closest('.ap-pricing-plan-card').querySelector('.module-select');
    
    freeTrialBtn.addEventListener('click', function(e) {
        const selectedModule = freeTrialModule.value;
        if (!selectedModule) {
            e.preventDefault();
            alert('Please select a module first');
            return false;
        }
        // Add module to URL
        this.href = 'cart.php?plan=free_trial&price=0&module=' + encodeURIComponent(selectedModule);
    });
    
    // Handle paid plans with module
    document.querySelectorAll('.ap-pricing-plan-card').forEach(card => {
        const moduleSelect = card.querySelector('.module-select');
        const planSelect = card.querySelector('select[onchange]');
        
        if (planSelect && moduleSelect) {
            planSelect.addEventListener('change', function(e) {
                const selectedModule = moduleSelect.value;
                if (!selectedModule) {
                    e.preventDefault();
                    this.value = '';
                    alert('Please select a module first');
                    return false;
                }
                
                // Add module parameter to URL
                if (this.value) {
                    const url = new URL(this.value, window.location.origin);
                    url.searchParams.set('module', selectedModule);
                    window.location.href = url.toString();
                }
            });
        }
    });
});
</script>
</body>

</html>