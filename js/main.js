// Main JavaScript functionality
class StrangeSkiesApp {
    constructor() {
        this.isLoaded = false;
        this.currentSection = 'home';
        this.formSubmitted = false;
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupCustomCursor();
        this.setupNavigation();
        this.setupFormHandling();
        this.setupScrollEffects();
        this.setupKeyboardShortcuts();
        this.setupAccessibility();
    }
    
    setupEventListeners() {
        // Window events
        window.addEventListener('load', () => {
            this.isLoaded = true;
            this.updateThreeScene();
        });
        
        window.addEventListener('resize', () => {
            this.handleResize();
        });
        
        window.addEventListener('scroll', () => {
            this.handleScroll();
        }, { passive: true });
        
        // Visibility change for performance optimization
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAnimations();
            } else {
                this.resumeAnimations();
            }
        });
    }
    
    setupCustomCursor() {
        if (window.innerWidth <= 768) return; // Skip on mobile
        
        const cursor = document.getElementById('cursor');
        const cursorDot = document.getElementById('cursor-dot');
        
        if (!cursor || !cursorDot) return;
        
        let mouseX = 0, mouseY = 0;
        let cursorX = 0, cursorY = 0;
        let dotX = 0, dotY = 0;
        
        // Update mouse position
        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
        });
        
        // Animate cursor
        const animateCursor = () => {
            // Smooth cursor following
            cursorX += (mouseX - cursorX) * 0.1;
            cursorY += (mouseY - cursorY) * 0.1;
            
            dotX += (mouseX - dotX) * 0.8;
            dotY += (mouseY - dotY) * 0.8;
            
            cursor.style.transform = `translate(${cursorX - 20}px, ${cursorY - 20}px)`;
            cursorDot.style.transform = `translate(${dotX - 2}px, ${dotY - 2}px)`;
            
            requestAnimationFrame(animateCursor);
        };
        
        animateCursor();
        
        // Cursor hover effects
        const hoverElements = document.querySelectorAll('a, button, .feature-card, .info-card, input, textarea, select');
        
        hoverElements.forEach(element => {
            element.addEventListener('mouseenter', () => {
                cursor.classList.add('hover');
                cursorDot.style.opacity = '0';
            });
            
            element.addEventListener('mouseleave', () => {
                cursor.classList.remove('hover');
                cursorDot.style.opacity = '1';
            });
        });
    }
    
    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link');
        const sections = document.querySelectorAll('section[id]');
        
        // Smooth scroll navigation
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                const targetSection = document.getElementById(targetId);
                
                if (targetSection) {
                    this.scrollToSection(targetSection);
                    this.updateActiveNav(targetId);
                    
                    // Close mobile menu when clicking a link
                    const navMenu = document.querySelector('.nav-menu');
                    const navToggle = document.getElementById('nav-toggle');
                    if (navMenu && navToggle) {
                        navMenu.classList.remove('active');
                        navToggle.classList.remove('active');
                    }
                }
            });
        });
        
        // Mobile navigation toggle
        const navToggle = document.getElementById('nav-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (navMenu.classList.contains('active') && 
                    !navMenu.contains(e.target) && 
                    !navToggle.contains(e.target)) {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                }
            });
        }
        
        // Update active navigation on scroll
        const observerOptions = {
            threshold: 0.3,
            rootMargin: '-100px 0px -50% 0px'
        };
        
        const navObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.updateActiveNav(entry.target.id);
                }
            });
        }, observerOptions);
        
        sections.forEach(section => {
            navObserver.observe(section);
        });
    }
    
    setupFormHandling() {
        const contactForm = document.getElementById('contact-form');
        const formMessage = document.getElementById('form-message');
        
        if (!contactForm) return;
        
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (this.formSubmitted) {
                this.showFormMessage('You have already submitted the form. Thank you!', 'success');
                return;
            }
            
            const formData = new FormData(contactForm);
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                interest: formData.get('interest'),
                message: formData.get('message') || ''
            };
            
            // Validate form data
            if (!this.validateFormData(data)) {
                this.showFormMessage('Please fill in all required fields correctly.', 'error');
                return;
            }
            
            try {
                            // Skip form animations to prevent button disappearing
            // if (window.animationController) {
            //     window.animationController.animateFormSubmission(contactForm, true);
            // }
                
                // Send to PHP script
                const response = await fetch('contact.php', {
                    method: 'POST',
                    body: new FormData(contactForm)
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message);
                }
                
                this.formSubmitted = true;
                this.showFormMessage('✓ We\'ve got your message!', 'success');
                contactForm.reset();
                
                // Create particle burst effect if Three.js is loaded
                if (window.threeScene) {
                    window.threeScene.createParticleBurst(0, 0);
                }
                
                // Store submission in localStorage
                localStorage.setItem('strangeSkiesSubmitted', 'true');
                localStorage.setItem('strangeSkiesEmail', data.email);
                
            } catch (error) {
                console.error('Form submission error:', error);
                this.showFormMessage('There was an error submitting your information. Please try again.', 'error');
                
                // Skip error animations too
                // if (window.animationController) {
                //     window.animationController.animateFormSubmission(contactForm, false);
                // }
            }
        });
        
        // Check if user already submitted (but allow reset for development)
        // Comment out this check for now to allow testing
        // if (localStorage.getItem('strangeSkiesSubmitted')) {
        //     this.formSubmitted = true;
        // }
        
        // Real-time form validation
        const inputs = contactForm.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
            
            input.addEventListener('input', () => {
                if (input.classList.contains('error')) {
                    this.validateField(input);
                }
            });
        });
    }
    
    setupScrollEffects() {
        let ticking = false;
        
        const updateScrollEffects = () => {
            const scrollProgress = window.pageYOffset / (document.documentElement.scrollHeight - window.innerHeight);
            
            // Update Three.js particle intensity based on scroll
            if (window.threeScene) {
                window.threeScene.updateParticleIntensity(1 - scrollProgress * 0.3);
            }
            
            // Update scroll indicator
            const scrollIndicator = document.querySelector('.scroll-indicator');
            if (scrollIndicator) {
                scrollIndicator.style.opacity = Math.max(0, 1 - scrollProgress * 3);
            }
            
            ticking = false;
        };
        
        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(updateScrollEffects);
                ticking = true;
            }
        }, { passive: true });
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Escape key to close mobile menu
            if (e.key === 'Escape') {
                const navMenu = document.querySelector('.nav-menu');
                const navToggle = document.getElementById('nav-toggle');
                
                if (navMenu && navMenu.classList.contains('active')) {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                }
            }
            
            // Arrow keys for navigation
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const sections = ['home', 'about', 'contact'];
                const currentIndex = sections.indexOf(this.currentSection);
                
                let nextIndex;
                if (e.key === 'ArrowDown') {
                    nextIndex = Math.min(currentIndex + 1, sections.length - 1);
                } else {
                    nextIndex = Math.max(currentIndex - 1, 0);
                }
                
                const targetSection = document.getElementById(sections[nextIndex]);
                if (targetSection) {
                    this.scrollToSection(targetSection);
                }
            }
        });
    }
    
    setupAccessibility() {
        // Skip to main content link
        const skipLink = document.createElement('a');
        skipLink.href = '#home';
        skipLink.textContent = 'Skip to main content';
        skipLink.className = 'skip-link';
        skipLink.style.cssText = `
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: 4px;
            z-index: 10000;
            transition: top 0.3s;
        `;
        
        skipLink.addEventListener('focus', () => {
            skipLink.style.top = '6px';
        });
        
        skipLink.addEventListener('blur', () => {
            skipLink.style.top = '-40px';
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
        
        // Announce page changes for screen readers
        this.announcer = document.createElement('div');
        this.announcer.setAttribute('aria-live', 'polite');
        this.announcer.setAttribute('aria-atomic', 'true');
        this.announcer.style.cssText = `
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        `;
        document.body.appendChild(this.announcer);
    }
    
    // Helper methods
    scrollToSection(section) {
        const headerHeight = document.querySelector('.nav').offsetHeight;
        const targetPosition = section.offsetTop - headerHeight;
        
        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
    }
    
    updateActiveNav(sectionId) {
        if (this.currentSection === sectionId) return;
        
        this.currentSection = sectionId;
        
        // Update navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${sectionId}`) {
                link.classList.add('active');
            }
        });
        
        // Announce section change
        const sectionTitles = {
            home: 'Strange Skies Home',
            about: 'About Strange Skies',
            contact: 'Contact and Signup'
        };
        
        if (this.announcer && sectionTitles[sectionId]) {
            this.announcer.textContent = `Navigated to ${sectionTitles[sectionId]} section`;
        }
    }
    
    validateFormData(data) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        return (
            data.name.trim().length >= 2 &&
            emailRegex.test(data.email) &&
            data.interest.trim() !== ''
        );
    }
    
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        
        // Remove existing error styling
        field.classList.remove('error');
        
        // Validation rules
        switch (field.type) {
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                isValid = emailRegex.test(value);
                break;
            case 'text':
                isValid = value.length >= 2;
                break;
            default:
                if (field.required) {
                    isValid = value !== '';
                }
        }
        
        if (!isValid) {
            field.classList.add('error');
        }
        
        return isValid;
    }
    
    async submitForm(data) {
        // Simulate API call - replace with actual endpoint
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                // Simulate 90% success rate
                if (Math.random() > 0.1) {
                    console.log('Form submitted:', data);
                    resolve({ success: true });
                } else {
                    reject(new Error('Simulated server error'));
                }
            }, 1500);
        });
    }
    
    showFormMessage(message, type) {
        const formMessage = document.getElementById('form-message');
        if (!formMessage) return;
        
        formMessage.textContent = message;
        formMessage.className = `form-message ${type}`;
        formMessage.style.display = 'block';
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                formMessage.style.display = 'none';
            }, 5000);
        }
    }
    
    handleResize() {
        // Update Three.js scene if it exists
        if (window.threeScene) {
            window.threeScene.onWindowResize();
        }
        
        // Update mobile menu state
        if (window.innerWidth > 768) {
            const navMenu = document.querySelector('.nav-menu');
            const navToggle = document.getElementById('nav-toggle');
            
            if (navMenu && navToggle) {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            }
        }
    }
    
    handleScroll() {
        // Update scroll progress for various effects
        const scrollProgress = window.pageYOffset / (document.documentElement.scrollHeight - window.innerHeight);
        
        // Custom scroll events can be added here
        this.updateScrollProgress(scrollProgress);
    }
    
    updateScrollProgress(progress) {
        // Update any scroll-based UI elements
        const progressBar = document.querySelector('.scroll-progress');
        if (progressBar) {
            progressBar.style.width = `${progress * 100}%`;
        }
    }
    
    updateThreeScene() {
        // Update Three.js scene when app is fully loaded
        if (window.threeScene) {
            window.threeScene.updateParticleIntensity(1);
        }
    }
    
    pauseAnimations() {
        // Pause resource-intensive animations when tab is not visible
        if (window.threeScene) {
            // Three.js scene will automatically pause when tab is hidden
        }
    }
    
    resumeAnimations() {
        // Resume animations when tab becomes visible
        if (window.threeScene) {
            // Three.js scene will automatically resume
        }
    }
    
    // Public methods for external access
    triggerParticleBurst(x = 0, y = 0) {
        if (window.threeScene) {
            window.threeScene.createParticleBurst(x, y);
        }
    }
    
    showNotification(message, type = 'info', duration = 3000) {
        // Create notification system
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? 'var(--danger-color)' : 'var(--primary-color)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, duration);
    }
}

// Initialize app when DOM is loaded
let strangeSkiesApp;

document.addEventListener('DOMContentLoaded', () => {
    strangeSkiesApp = new StrangeSkiesApp();
    
    // Make app globally available
    window.strangeSkiesApp = strangeSkiesApp;
    
    // Add some fun Easter eggs
    console.log(`
    ╔═══════════════════════════════════════╗
    ║              Strange Skies            ║
    ║         The truth is out there        ║
    ║                                       ║
    ║   Built with cutting-edge technology: ║
    ║   • Three.js for 3D effects          ║
    ║   • GSAP for smooth animations       ║
    ║   • Modern CSS with custom props     ║
    ║   • Responsive design principles     ║
    ║                                       ║
    ║        Ready to join the mission?     ║
    ╚═══════════════════════════════════════╝
    `);
});

// Global error handling
window.addEventListener('error', (e) => {
    console.error('Application error:', e.error);
    
    if (window.strangeSkiesApp) {
        window.strangeSkiesApp.showNotification('An unexpected error occurred. Please refresh the page.', 'error');
    }
});

// Service Worker registration for PWA features (if needed in future)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // navigator.serviceWorker.register('/sw.js')
        //     .then(registration => console.log('SW registered'))
        //     .catch(error => console.log('SW registration failed'));
    });
}
