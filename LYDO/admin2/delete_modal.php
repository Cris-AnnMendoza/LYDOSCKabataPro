<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;backdrop-filter:blur(4px);justify-content:center;align-items:center;">
  <div style="background:#fff;border-radius:16px;padding:0;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:modalSlideIn 0.3s ease-out;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#ef5350,#e53935);padding:24px 28px;border-radius:16px 16px 0 0;display:flex;align-items:center;gap:14px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-exclamation-triangle" style="color:#fff;font-size:24px;"></i>
      </div>
      <div>
        <h3 style="margin:0;color:#fff;font-size:1.25rem;font-weight:700;" id="deleteModalTitle">Confirm Delete</h3>
        <p style="margin:4px 0 0;color:rgba(255,255,255,0.9);font-size:0.875rem;">This action cannot be undone</p>
      </div>
    </div>
    
    <!-- Body -->
    <div style="padding:28px;">
      <p style="margin:0 0 8px;color:#424242;font-size:1rem;line-height:1.6;" id="deleteModalMessage">
        Are you sure you want to delete this item?
      </p>
      <p style="margin:0;color:#757575;font-size:0.875rem;line-height:1.5;" id="deleteModalWarning">
        This action will permanently remove all associated data.
      </p>
    </div>
    
    <!-- Footer -->
    <div style="padding:0 28px 28px;display:flex;gap:12px;justify-content:flex-end;">
      <button onclick="closeDeleteModal()" style="padding:11px 24px;background:#f5f5f5;color:#424242;border:none;border-radius:8px;font-size:0.938rem;font-weight:600;cursor:pointer;transition:all 0.2s;">
        Cancel
      </button>
      <form method="POST" id="deleteForm" style="margin:0;">
        <div id="deleteFormInputs"></div>
        <button type="submit" style="padding:11px 24px;background:#e53935;color:#fff;border:none;border-radius:8px;font-size:0.938rem;font-weight:600;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-trash"></i> <span id="deleteButtonText">Delete</span>
        </button>
      </form>
    </div>
  </div>
</div>

<style>
@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

#deleteModal button:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

#deleteModal form button:hover {
  background: #c62828;
}
</style>

<script>
function showDeleteModal(config) {
  // config: { title, message, warning, buttonText, formData }
  document.getElementById('deleteModalTitle').textContent = config.title || 'Confirm Delete';
  document.getElementById('deleteModalMessage').innerHTML = config.message || 'Are you sure you want to delete this item?';
  document.getElementById('deleteModalWarning').textContent = config.warning || 'This action will permanently remove all associated data.';
  document.getElementById('deleteButtonText').textContent = config.buttonText || 'Delete';
  
  // Build form inputs
  const inputsContainer = document.getElementById('deleteFormInputs');
  inputsContainer.innerHTML = '';
  if (config.formData) {
    for (const [key, value] of Object.entries(config.formData)) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = value;
      inputsContainer.appendChild(input);
    }
  }
  
  document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeDeleteModal();
  }
});

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeDeleteModal();
  }
});
</script>
