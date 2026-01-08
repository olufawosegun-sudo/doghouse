document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
        document.body.classList.toggle('sidebar-open');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }

    // Handle touch events for mobile swipe
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    document.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);

    function handleSwipe() {
        const SWIPE_THRESHOLD = 50;
        const difference = touchStartX - touchEndX;
        
        if (Math.abs(difference) < SWIPE_THRESHOLD) return;
        
        if (difference > 0) {
            // Swipe left - close sidebar
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        } else {
            // Swipe right - open sidebar
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
        }
    }
});
