
<!-- pricing plan area start -->
<section class="ap-pricing-plan-area section-space">
  <div class="container">
    <div class="row justify-center">
      <div class="col-xxl-7 col-xl-7 col-lg-10">
        <div class="wow apFadeInUp" data-wow-delay=".3s" data-wow-duration="1s">
          <div class="ap-section-title-wrapper text-center section-title-space">
            <h4 class="ap-section-title">Get plan that perfectly suits <br class="d-none d-md-block">
              your requirements</h4>
            <p class="ap-section-desc">Choose a plan tailored to your needs — flexible, scalable, and cost-effective.
              All prices are inclusive of applicable GST.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-12">
      <div class="ap-pricing-plan-box wow apFadeInUp" data-wow-delay=".4s" data-wow-duration="1s">

      

       <!-- Free Trial -->
        <div class="ap-pricing-plan-card" style="border: 2px solid #28a745;">
          <span class="badge bg-success" style="position: absolute; top: 20px; right: 20px; font-size: 0.9rem; padding: 6px 12px;">Popular</span>
          <h4 class="ap-pricing-plan-title">Free Trial</h4>
          <p class="ap-pricing-plan-desc">Try all features free for 14 days. No card required.</p>
          <h3 class="ap-pricing-plan-price">₹0<span>/14 days</span></h3>
          <div class="ap-pricing-plan-list">
            <ul>
              <li class="available"><i class="icon-circle-check"></i> Full access for 14 days</li>
              <li class="available"><i class="icon-circle-check"></i> No payment required</li>
              <li class="available"><i class="icon-circle-check"></i> All features unlocked</li>
              <li class="available"><i class="icon-circle-check"></i> Upgrade anytime</li>
            </ul>
          </div>
          <div class="ap-pricing-plan-btn">
           <select class="form-select module-select">

    <option value="">Select Module</option>
    <?php foreach ($modules as $mod): ?>
      <option value="<?php echo htmlspecialchars($mod); ?>">
        <?php echo ucfirst(str_replace('_', ' ', $mod)); ?>
      </option>
    <?php endforeach; ?>
  </select>
            <a href="#" class="ap-btn btn-primary start-trial-btn" style="background: #28a745; border-color: #28a745;">Start Free Trial</a>

          </div>
        </div>

        <!-- Free Trial -->
        <!-- <div class="ap-pricing-plan-card">
          <h4 class="ap-pricing-plan-title">Free Trial</h4>
          <p class="ap-pricing-plan-desc">Try all features free for 14 days. No card required.</p>
          <h3 class="ap-pricing-plan-price">₹0<span>/14 days</span></h3>
          <div class="ap-pricing-plan-list">
            <ul>
              <li class="available"><i class="icon-circle-check"></i> Full access for 14 days</li>
              <li class="available"><i class="icon-circle-check"></i> No payment required</li>
              <li class="available"><i class="icon-circle-check"></i> Upgrade anytime</li>
            </ul>
          </div>
          <div class="ap-pricing-plan-btn">
            <a href="checkout.php?plan=free_trial&price=0" class="ap-btn btn-outline-primary w-100">Start Free Trial</a>
          </div>
        </div> -->

        <!-- Bronze Plan -->
        <div class="ap-pricing-plan-card">
          <h4 class="ap-pricing-plan-title">Bronze</h4>
          <p class="ap-pricing-plan-desc">Single user access with limited features.</p>
          <h3 class="ap-pricing-plan-price">₹150<span>/month</span></h3>
          <div class="ap-pricing-plan-list">
            <ul>
              <li class="available"><i class="icon-circle-check"></i> 1 user</li>
              <li class="available"><i class="icon-circle-check"></i> Limited module access</li>
              <li class="available"><i class="icon-circle-check"></i> Validity 30 days (monthly)</li>
              <li class="available"><i class="icon-circle-check"></i> Validity up to 3 years (yearly)</li>
            </ul>
          </div>
          <div class="ap-pricing-plan-btn d-flex flex-column gap-2">
            <select class="form-select module-select">
    <option value="">Select Module</option>
    <?php foreach ($modules as $mod): ?>
      <option value="<?php echo htmlspecialchars($mod); ?>">
        <?php echo ucfirst(str_replace('_', ' ', $mod)); ?>
      </option>
    <?php endforeach; ?>
  </select>
           <a class="ap-btn btn-primary w-100 choose-plan-btn">Choose Plan</a>
            <select class="form-select plan-select">

              <option value="">Plans</option>
              <option value="cart.php?plan=bronze&period=monthly&month=1">1 Month – ₹150</option>
              <option value="cart.php?plan=bronze&period=yearly&years=1">1 Year – ₹1200</option>
              <option value="cart.php?plan=bronze&period=yearly&years=2">2 Years – ₹2160 (Save 10%)</option>
              <option value="cart.php?plan=bronze&period=yearly&years=3">3 Years – ₹3060 (Save 15%)</option>
            </select>
          </div>
        </div>

        <!-- Silver Plan -->
        <div class="ap-pricing-plan-card">
          <h4 class="ap-pricing-plan-title">Silver</h4>
          <p class="ap-pricing-plan-desc">Three user access with medium features.</p>
          <h3 class="ap-pricing-plan-price">₹250<span>/month</span></h3>
          <div class="ap-pricing-plan-list">
            <ul>
              <li class="available"><i class="icon-circle-check"></i> 3 user</li>
              <li class="available"><i class="icon-circle-check"></i> Medium module access</li>
              <li class="available"><i class="icon-circle-check"></i> Validity 30 days (monthly)</li>
              <li class="available"><i class="icon-circle-check"></i> Validity up to 3 years (yearly)</li>
            </ul>
          </div>
          <div class="ap-pricing-plan-btn d-flex flex-column gap-2">
            <select class="form-select module-select">
    <option value="">Select Module</option>
    <?php foreach ($modules as $mod): ?>
      <option value="<?php echo htmlspecialchars($mod); ?>">
        <?php echo ucfirst(str_replace('_', ' ', $mod)); ?>
      </option>
    <?php endforeach; ?>
  </select>
            <a class="ap-btn btn-primary w-100 choose-plan-btn">Choose Plan</a>
            <select class="form-select plan-select">

              <option value="">Plans</option>
              <option value="cart.php?plan=silver&period=monthly&month=1">1 Month – ₹250</option>
              <option value="cart.php?plan=silver&period=yearly&years=1">1 Year – ₹2500</option>
              <option value="cart.php?plan=silver&period=yearly&years=2">2 Years – ₹4500 (Save 10%)</option>
              <option value="cart.php?plan=silver&period=yearly&years=3">3 Years – ₹6375 (Save 15%)</option>
            </select>
          </div>
        </div>

        <!-- Gold Plan -->
       <div class="ap-pricing-plan-card">
          <h4 class="ap-pricing-plan-title">Gold</h4>
          <p class="ap-pricing-plan-desc">Five user access with maximum features.</p>
          <h3 class="ap-pricing-plan-price">₹350<span>/month</span></h3>
          <div class="ap-pricing-plan-list">
            <ul>
              <li class="available"><i class="icon-circle-check"></i> 5 user</li>
              <li class="available"><i class="icon-circle-check"></i> Maximum module access</li>
              <li class="available"><i class="icon-circle-check"></i> Validity 30 days (monthly)</li>
              <li class="available"><i class="icon-circle-check"></i> Validity up to 3 years (yearly)</li>
            </ul>
          </div>
          <div class="ap-pricing-plan-btn d-flex flex-column gap-2">
            <select class="form-select module-select">
    <option value="">Select Module</option>
    <?php foreach ($modules as $mod): ?>
      <option value="<?php echo htmlspecialchars($mod); ?>">
        <?php echo ucfirst(str_replace('_', ' ', $mod)); ?>
      </option>
    <?php endforeach; ?>
  </select>
            <a class="ap-btn btn-primary w-100 choose-plan-btn">Choose Plan</a>
           <select class="form-select plan-select">

              <option value="">Plans</option>
              <option value="cart.php?plan=gold&period=monthly&month=1">1 Month – ₹350</option>
              <option value="cart.php?plan=gold&period=yearly&years=1">1 Year – ₹3500</option>
              <option value="cart.php?plan=gold&period=yearly&years=2">2 Years – ₹6300 (Save 10%)</option>
              <option value="cart.php?plan=gold&period=yearly&years=3">3 Years – ₹8925 (Save 15%)</option>
            </select>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
<!-- pricing plan area end -->

<script>
document.addEventListener("DOMContentLoaded", function () {
  // Handle "Choose Plan" buttons
  document.querySelectorAll(".choose-plan-btn").forEach(function (button) {
    button.addEventListener("click", function () {
      const card = button.closest(".ap-pricing-plan-card");
      const moduleSelect = card.querySelector(".module-select");
      const planDropdown = card.querySelector(".plan-select");

      const selectedModule = moduleSelect.value;
      const selectedPlanOption = planDropdown.value;

      if (!selectedModule) {
        alert("Please select a module first.");
        return;
      }

      if (!selectedPlanOption) {
        alert("Please choose a plan duration.");
        return;
      }

      // Redirect directly to checkout.php instead of cart.php
      const checkoutUrl = selectedPlanOption.replace("cart.php", "checkout.php") +
        "&module=" + encodeURIComponent(selectedModule);

      window.location.href = checkoutUrl;
    });
  });

  // Handle Free Trial button
const trialButton = document.querySelector(".start-trial-btn");
if (trialButton) {
    trialButton.addEventListener("click", function (e) {
      e.preventDefault();
      const card = trialButton.closest(".ap-pricing-plan-card");
      const moduleSelect = card.querySelector(".module-select"); // CHANGE THIS LINE
      const selectedModule = moduleSelect.value;

      if (!selectedModule) {
        alert("Please select a module first.");
        return;
      }

      window.location.href =
        "checkout.php?plan=free_trial&price=0&module=" + encodeURIComponent(selectedModule);
    });
  }
});
</script>


<!-- Fix double dropdown arrow -->
<style>
  .form-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
  }
</style>

