document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize admin/theme dynamic Testimonials block carousels
    var testimonialsBlocks = document.querySelectorAll('.block-testimonials');
    testimonialsBlocks.forEach(function(carousel) {
        if (carousel.dataset.initialized) return;
        carousel.dataset.initialized = 'true';
        
        // Find the wrapper using either standard or unique ID classes
        var wrapper = carousel.querySelector('.testimonials-slides-wrapper');
        if (!wrapper) {
            // Match any layout class starting with testimonials-slides-wrapper-
            var wrapperClass = Array.from(carousel.classList).find(function(c) {
                return c.indexOf('testimonials-slides-wrapper-') === 0;
            });
            if (!wrapperClass) {
                // Try to find by partial class match on any child
                wrapper = carousel.querySelector('[class^="testimonials-slides-wrapper-"]');
            } else {
                wrapper = carousel.querySelector('.' + wrapperClass);
            }
        }
        
        // Sourced backup lookups
        if (!wrapper) {
            wrapper = carousel.querySelector('div > div');
        }
        
        var slides = carousel.querySelectorAll('.testimonial-slide');
        var duration = parseInt(carousel.getAttribute('data-duration')) || 5000;
        
        if (wrapper && slides.length > 0) {
            var currentIdx = 0;
            setInterval(function() {
                slides[currentIdx].classList.remove('active');
                currentIdx = (currentIdx + 1) % slides.length;
                wrapper.style.left = '-' + (currentIdx * 100) + '%';
                slides[currentIdx].classList.add('active');
            }, duration);
        }
    });

    // 2. Initialize portfolio static Home page testimonials carousel
    var homeTestimonials = document.querySelectorAll('.testimonials-section');
    homeTestimonials.forEach(function(carousel) {
        if (carousel.dataset.initialized) return;
        carousel.dataset.initialized = 'true';
        
        var wrapper = carousel.querySelector('.testimonials-slides-wrapper');
        var slides = carousel.querySelectorAll('.testimonial-slide');
        if (wrapper && slides.length > 0) {
            var currentIdx = 0;
            setInterval(function() {
                currentIdx = (currentIdx + 1) % slides.length;
                wrapper.style.left = '-' + (currentIdx * 100) + '%';
            }, 5000); // Default 5 seconds duration
        }
    });
});
