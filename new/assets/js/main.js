$(start);

function start() {
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Confirm delete actions
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Confirm end friendship
    $('.btn-end-friendship').on('click', function(e) {
        if (!confirm('This will also unshare all collections between you. Continue?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Date/time helper: convert datetime-local to display format
    window.formatDateTimeForDisplay = function(datetime) {
        if (!datetime) return '';
        const dt = new Date(datetime);
        const day = String(dt.getDate()).padStart(2, '0');
        const month = String(dt.getMonth() + 1).padStart(2, '0');
        const year = dt.getFullYear();
        const hours = String(dt.getHours()).padStart(2, '0');
        const minutes = String(dt.getMinutes()).padStart(2, '0');
        return `${day}.${month}.${year} ${hours}:${minutes}`;
    };
    
}
