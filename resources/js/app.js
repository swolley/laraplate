import './bootstrap';

// Sticky tabs width management
document.addEventListener('DOMContentLoaded', function() {
    const stickyElements = document.querySelectorAll('.fi-grid-col:has(.fi-sc-tabs)');
    
    stickyElements.forEach(element => {
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.intersectionRatio < 1) {
                    // Element is sticky
                    element.classList.add('is-sticky');
                } else {
                    // Element is not sticky
                    element.classList.remove('is-sticky');
                }
            },
            {
                threshold: [1],
                rootMargin: '-66px 0px 0px 0px' // Account for the top offset
            }
        );
        
        observer.observe(element);
    });
});
