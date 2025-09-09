// Njoftimet Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    
    // FAQ Accordion Functionality
    const faqHeaders = document.querySelectorAll('.faq-header');
    
    faqHeaders.forEach((header, index) => {
        header.addEventListener('click', () => {
            const item = header.parentElement;
            const isActive = item.classList.contains('active');
            
            // Close all other FAQ items
            document.querySelectorAll('.faq-item').forEach((otherItem, otherIndex) => {
                if (otherIndex !== index) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle current item
            item.classList.toggle('active');
        });
    });

    // Newsletter Form Handling
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[type="email"]').value;
            const button = this.querySelector('button');
            const originalText = button.textContent;
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Ju lutemi shkruani një email të vlefshëm.');
                return;
            }
            
            // Show loading state
            button.textContent = 'Duke u regjistruar...';
            button.disabled = true;
            
            // Simulate form submission (replace with actual form submission)
            setTimeout(() => {
                alert('Faleminderit për regjistrimin! Do të merrni lajme dhe mundësi të reja punësimi.');
                this.querySelector('input[type="email"]').value = '';
                button.textContent = originalText;
                button.disabled = false;
            }, 1500);
        });
    }

    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Animate elements on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    // Observe elements for animation
    const animateElements = document.querySelectorAll('.post-item, .faq-item, .hero-text, .content-header');
    animateElements.forEach(el => {
        observer.observe(el);
    });

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        .post-item,
        .faq-item,
        .hero-text,
        .content-header {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        
        .animate-in {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        
        .hero-text {
            transition-delay: 0.2s;
        }
        
        .post-item:nth-child(1) { transition-delay: 0.1s; }
        .post-item:nth-child(2) { transition-delay: 0.2s; }
        .post-item:nth-child(3) { transition-delay: 0.3s; }
        .post-item:nth-child(4) { transition-delay: 0.4s; }
        .post-item:nth-child(5) { transition-delay: 0.5s; }
        .post-item:nth-child(6) { transition-delay: 0.6s; }
        
        .faq-item:nth-child(1) { transition-delay: 0.1s; }
        .faq-item:nth-child(2) { transition-delay: 0.2s; }
        .faq-item:nth-child(3) { transition-delay: 0.3s; }
        .faq-item:nth-child(4) { transition-delay: 0.4s; }
        .faq-item:nth-child(5) { transition-delay: 0.5s; }
        .faq-item:nth-child(6) { transition-delay: 0.6s; }
        .faq-item:nth-child(7) { transition-delay: 0.7s; }
        .faq-item:nth-child(8) { transition-delay: 0.8s; }
    `;
    document.head.appendChild(style);

    // CTA Button hover effect
    const ctaButton = document.querySelector('.cta-button');
    if (ctaButton) {
        ctaButton.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        ctaButton.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    }

    // Newsletter button hover effect
    const newsletterButton = document.querySelector('.newsletter-form button');
    if (newsletterButton) {
        newsletterButton.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        newsletterButton.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    }
});

// Lazy loading for images
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src || img.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    const images = document.querySelectorAll('img[data-src]');
    images.forEach(img => imageObserver.observe(img));
}