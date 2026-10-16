<!-- footer.php -->
</div><!-- Closing main-content div from header -->
</div><!-- Closing container div -->

<footer class="footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> Unravel. All rights reserved.</p>
        <p class="footer-links">
            <a href="privacy.php">Privacy Policy</a> | 
            <a href="terms.php">Terms of Service</a> | 
            <a href="help.php">Help Center</a>
        </p>
        <div class="system-info">
            <small>Version 1.0.0 | Load time: <?php echo round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3); ?>s</small>
        </div>
    </div>
</footer>

<!-- Modal for general purposes -->
<div id="globalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title"></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary modal-cancel">Cancel</button>
            <button class="btn btn-primary modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<!-- Notification toast -->
<div id="toast" class="toast"></div>

<script>
// Global modal handler
const globalModal = {
    show: function(title, content, onConfirm = null) {
        const modal = document.getElementById('globalModal');
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').innerHTML = content;
        modal.style.display = 'flex';
        
        // Set up confirm handler
        const confirmBtn = modal.querySelector('.modal-confirm');
        if (onConfirm) {
            confirmBtn.onclick = function() {
                onConfirm();
                modal.style.display = 'none';
            };
        } else {
            confirmBtn.style.display = 'none';
        }
        
        // Set up close handlers
        modal.querySelector('.modal-close').onclick = function() {
            modal.style.display = 'none';
        };
        
        modal.querySelector('.modal-cancel').onclick = function() {
            modal.style.display = 'none';
        };
        
        // Close when clicking outside
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
};

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast toast-' + type;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key closes modal
    if (e.key === 'Escape') {
        const modal = document.getElementById('globalModal');
        if (modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    }
    
    // Ctrl/Cmd + / to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        const search = document.getElementById('global-search');
        if (search) search.focus();
    }
});
</script>
</body>
</html>