/* public/assets/js/blocks/baseline.js */
document.addEventListener('DOMContentLoaded', () => {
    // Non-blocking Lazy Load for Baseline Video Backgrounds
    const loadDeferredVideos = () => {
        const videos = document.querySelectorAll('.hero-video-bg');
        videos.forEach(video => {
            const sources = video.querySelectorAll('source[data-src]');
            sources.forEach(source => {
                source.src = source.getAttribute('data-src');
                source.removeAttribute('data-src');
            });
            video.load();
            
            // Defensively ensure play completes successfully (handling browser autoplay policies)
            video.play().catch(() => {
                // If autoplay fails, we add a listener to play on user interaction
                const playOnInteraction = () => {
                    video.play();
                    document.removeEventListener('click', playOnInteraction);
                    document.removeEventListener('scroll', playOnInteraction);
                };
                document.addEventListener('click', playOnInteraction);
                document.addEventListener('scroll', playOnInteraction);
            });
        });
    };

    // Delay loading slightly until after window.onload to keep critical path completely free!
    if (document.readyState === 'complete') {
        loadDeferredVideos();
    } else {
        window.addEventListener('load', loadDeferredVideos);
    }
});
