  <!-- Processing Modal to prevent multiple clicks -->
  <div id="processingModal" class="processing-modal-backdrop" role="dialog" aria-modal="true"
    aria-label="Processing request">
    <div class="processing-modal-card">
      <div class="processing-spinner"></div>
      <h3 class="processing-modal-title">Processing...</h3>
      <p class="processing-modal-subtitle">Saving your delineation, please wait.</p>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="processing-modal-backdrop" role="dialog" aria-modal="true" aria-label="Success">
    <div class="processing-modal-card">
      <div class="success-icon-wrapper">
        <i class="bi bi-check-lg"></i>
      </div>
      <h3 class="processing-modal-title" id="successModalTitle">Success!</h3>
      <p class="processing-modal-subtitle" id="successModalMessage">Delineation saved and submitted for expert review.
      </p>
      <button type="button" class="btn btn-g success-modal-btn" onclick="hideSuccessModal()">Got it</button>
    </div>
  </div>
