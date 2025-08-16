document.addEventListener("DOMContentLoaded", () => {
  /* =========================
     1. Navigation Tab Switch
  ========================== */
  const tabs = document.querySelectorAll(".nav-item");
  const contents = document.querySelectorAll(".content");

  window.switchTab = (tabId, el) => {
    contents.forEach(c => c.classList.add("hidden"));
    document.getElementById(tabId).classList.remove("hidden");

    tabs.forEach(t => t.classList.remove("active"));
    el.classList.add("active");
  };

  /* =========================
     2. Payment Modal Handling
  ========================== */
  const modal = document.getElementById("paymentModal");
  const packageNameInput = document.getElementById("packageName");
  const packagePriceInput = document.getElementById("packagePrice");
  const displayName = document.getElementById("displayPackageName");
  const displayPrice = document.getElementById("displayPackagePrice");

  window.openPaymentModal = (name, price) => {
    packageNameInput.value = name;
    packagePriceInput.value = price;
    displayName.value = name;
    displayPrice.value = price;
    modal.style.display = "flex";

    // Clear previous messages
    document.getElementById('paymentResult').innerHTML = '';
  };

  window.closePaymentModal = () => {
    modal.style.display = "none";
  };

  /* Close modal if clicked outside */
  window.addEventListener("click", (e) => {
    if (e.target === modal) {
      closePaymentModal();
    }
  });

  /* =========================
     3. Form Submission Handling
  ========================== */
  const mpesaForm = document.getElementById("mpesaPaymentForm");
  if (mpesaForm) {
    mpesaForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      const phoneInput = document.getElementById("mpesaPhone");
      const phone = phoneInput.value.trim();
      const pattern = /^254[17]\d{8}$/;

      if (!pattern.test(phone)) {
        alert("Please enter a valid Kenyan phone number starting with 2547 or 2541.");
        return;
      }

      // Show spinner
      const spinner = document.getElementById("paymentSpinner");
      spinner.style.display = "block";

      try {
        const formData = new FormData(mpesaForm);
        const response = await fetch('process_payment.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // Display voucher details to user
          const voucherHTML = `
            <div class="voucher-result success">
              <h3>Payment Successful!</h3>
              <p>Transaction Code: ${result.transaction_code}</p>
              <p>Voucher Code: <strong>${result.voucher_code}</strong></p>
              <p>Expiration: ${result.expiration}</p>
              <p>Package: ${result.package}</p>
              <button class="btn" onclick="closePaymentModal()">Continue</button>
            </div>
          `;
          document.getElementById('paymentResult').innerHTML = voucherHTML;
        } else {
          document.getElementById('paymentResult').innerHTML = `
            <div class="voucher-result error">
              <h3>Payment Failed</h3>
              <p>${result.message}</p>
              ${result.errors ? `<ul>${result.errors.map(e => `<li>${e}</li>`).join('')}</ul>` : ''}
              <button class="btn" onclick="document.getElementById('paymentResult').innerHTML=''">Try Again</button>
            </div>
          `;
        }
      } catch (error) {
        document.getElementById('paymentResult').innerHTML = `
          <div class="voucher-result error">
            <h3>Network Error</h3>
            <p>Please check your connection and try again</p>
          </div>
        `;
      } finally {
        spinner.style.display = "none";
      }
    });
  }

  /* =========================
     4. Voucher Connection
  ========================== */
  const voucherForm = document.querySelector("#voucher form");
  if (voucherForm) {
    voucherForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const voucherInput = voucherForm.querySelector('input[type="text"]');
      const voucherCode = voucherInput.value.trim();

      if (!voucherCode) {
        alert("Please enter a voucher code");
        return;
      }

      // Simulate connection process
      const response = await fetch('connect.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ voucher_code: voucherCode })
      });

      const result = await response.json();

      if (result.success) {
        alert(`Connected successfully!\nExpires: ${result.expiration}`);
      } else {
        alert(`Connection failed: ${result.message}`);
      }
    });
  }

  /* =========================
     5. UI Enhancements
  ========================== */
  // Scroll to top button
  const scrollTopBtn = document.createElement("button");
  scrollTopBtn.id = "scroll-top";
  scrollTopBtn.innerHTML = "↑";
  document.body.appendChild(scrollTopBtn);

  scrollTopBtn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  window.addEventListener("scroll", () => {
    scrollTopBtn.style.display = window.scrollY > 200 ? "block" : "none";
  });

  // Fade-in animations
  const fadeElements = document.querySelectorAll(".fade-in");
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
      }
    });
  }, { threshold: 0.2 });

  fadeElements.forEach(el => observer.observe(el));
});