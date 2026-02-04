// GSAP Animations and Interactions
gsap.registerPlugin(ScrollTrigger);

class AnimationController {
    constructor() {
        this.isLoaded = false;
        this.init();
    }
    
    init() {
        this.setupLoadingAnimation();
        this.setupScrollAnimations();
        this.setupHoverAnimations();
        this.setupParallaxEffects();
        this.setupCounterAnimations();
    }
    
    setupLoadingAnimation() {
        const loadingScreen = document.getElementById('loading-screen');
        const loadingProgress = document.querySelector('.loading-progress');
        const loadingLetters = document.querySelectorAll('.loading-letter');
        
        // Animate loading letters
        gsap.fromTo(loadingLetters, 
            { opacity: 0, y: 20 },
            { 
                opacity: 1, 
                y: 0, 
                duration: 0.5, 
                stagger: 0.1,
                ease: "power2.out"
            }
        );
        
        // Animate progress bar
        gsap.to(loadingProgress, {
            width: '100%',
            duration: 3,
            ease: "power2.inOut",
            onComplete: () => {
                this.hideLoadingScreen();
            }
        });
    }
    
    hideLoadingScreen() {
        const loadingScreen = document.getElementById('loading-screen');
        
        gsap.to(loadingScreen, {
            opacity: 0,
            duration: 0.8,
            ease: "power2.inOut",
            onComplete: () => {
                loadingScreen.classList.add('hidden');
                this.isLoaded = true;
                this.startMainAnimations();
            }
        });
    }
    
    startMainAnimations() {
        // Hero entrance animation
        const heroTimeline = gsap.timeline();
        
        heroTimeline
            .from('.hero-logo', {
                scale: 0,
                rotation: 180,
                duration: 1,
                ease: "back.out(1.7)"
            })
            .from('.hero-headline', {
                y: 50,
                opacity: 0,
                duration: 0.8,
                ease: "power3.out"
            }, "-=0.5")

            .from('.hero-subtitle', {
                y: 30,
                opacity: 0,
                duration: 0.6,
                ease: "power2.out"
            }, "-=0.4")
            .from('.status-indicator', {
                y: 30,
                opacity: 0,
                duration: 0.6,
                ease: "power2.out"
            }, "-=0.3")
            // Disabled to prevent form button issues
            // .from('.cta-button', {
            //     scale: 0,
            //     duration: 0.5,
            //     ease: "back.out(1.7)"
            // }, "-=0.1")
            .from('.scroll-indicator', {
                y: 20,
                opacity: 0,
                duration: 0.5,
                ease: "power2.out"
            }, "-=0.1");
    }
    
    setupScrollAnimations() {
        // Navigation scroll effect
        ScrollTrigger.create({
            trigger: "body",
            start: "100px top",
            end: "bottom bottom",
            onEnter: () => {
                gsap.to('.nav', {
                    backgroundColor: "rgba(0, 0, 0, 0.95)",
                    backdropFilter: "blur(20px)",
                    duration: 0.3
                });
                document.getElementById('nav').classList.add('scrolled');
            },
            onLeaveBack: () => {
                gsap.to('.nav', {
                    backgroundColor: "rgba(0, 0, 0, 0.8)",
                    backdropFilter: "blur(16px)",
                    duration: 0.3
                });
                document.getElementById('nav').classList.remove('scrolled');
            }
        });
        
        // Section animations
        gsap.utils.toArray('.section-header').forEach(header => {
            gsap.fromTo(header.querySelector('.section-title'), 
                { y: 50, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 1,
                    ease: "power3.out",
                    scrollTrigger: {
                        trigger: header,
                        start: "top 80%",
                        end: "bottom 20%"
                    }
                }
            );
            
            gsap.fromTo(header.querySelector('.section-line'), 
                { scaleX: 0 },
                {
                    scaleX: 1,
                    duration: 0.8,
                    ease: "power2.out",
                    scrollTrigger: {
                        trigger: header,
                        start: "top 80%",
                        end: "bottom 20%"
                    }
                }
            );
        });
        
        // About section animations
        gsap.fromTo('.about-content', 
            { x: -100, opacity: 0 },
            {
                x: 0,
                opacity: 1,
                duration: 1,
                ease: "power3.out",
                scrollTrigger: {
                    trigger: '.about-grid',
                    start: "top 70%"
                }
            }
        );
        
        gsap.fromTo('.feature-card', 
            { x: 100, opacity: 0 },
            {
                x: 0,
                opacity: 1,
                duration: 0.8,
                stagger: 0.2,
                ease: "power3.out",
                scrollTrigger: {
                    trigger: '.about-features',
                    start: "top 70%"
                }
            }
        );
        
        // Contact section animations
        gsap.fromTo('.contact-form-container', 
            { y: 100, opacity: 0 },
            {
                y: 0,
                opacity: 1,
                duration: 1,
                ease: "power3.out",
                scrollTrigger: {
                    trigger: '.contact-content',
                    start: "top 70%"
                }
            }
        );
        
        gsap.fromTo('.info-card', 
            { y: 50, opacity: 0 },
            {
                y: 0,
                opacity: 1,
                duration: 0.6,
                stagger: 0.15,
                ease: "power2.out",
                scrollTrigger: {
                    trigger: '.contact-info',
                    start: "top 70%"
                }
            }
        );
    }
    
    setupHoverAnimations() {
        // Button hover effects
        document.querySelectorAll('.cta-button').forEach(button => {
            button.addEventListener('mouseenter', () => {
                gsap.to(button, {
                    scale: 1.05,
                    duration: 0.3,
                    ease: "power2.out"
                });
                
                // Particle effect for primary buttons
                if (button.classList.contains('primary')) {
                    this.createButtonParticles(button);
                }
            });
            
            button.addEventListener('mouseleave', () => {
                gsap.to(button, {
                    scale: 1,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
            
            button.addEventListener('click', () => {
                gsap.to(button, {
                    scale: 0.95,
                    duration: 0.1,
                    yoyo: true,
                    repeat: 1,
                    ease: "power2.inOut"
                });
            });
        });
        
        // Feature card hover effects
        document.querySelectorAll('.feature-card').forEach(card => {
            const icon = card.querySelector('.feature-icon');
            
            card.addEventListener('mouseenter', () => {
                gsap.to(card, {
                    y: -10,
                    duration: 0.4,
                    ease: "power2.out"
                });
                
                gsap.to(icon, {
                    scale: 1.1,
                    rotation: 5,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
            
            card.addEventListener('mouseleave', () => {
                gsap.to(card, {
                    y: 0,
                    duration: 0.4,
                    ease: "power2.out"
                });
                
                gsap.to(icon, {
                    scale: 1,
                    rotation: 0,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
        });
        
        // Info card hover effects
        document.querySelectorAll('.info-card').forEach(card => {
            const icon = card.querySelector('.info-icon');
            
            card.addEventListener('mouseenter', () => {
                gsap.to(icon, {
                    scale: 1.2,
                    duration: 0.3,
                    ease: "back.out(1.7)"
                });
            });
            
            card.addEventListener('mouseleave', () => {
                gsap.to(icon, {
                    scale: 1,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
        });
    }
    
    setupParallaxEffects() {
        // Logo parallax
        gsap.to('.logo-main', {
            scale: 1.2,
            scrollTrigger: {
                trigger: '.hero',
                start: "top top",
                end: "bottom top",
                scrub: 1
            }
        });
        
        // Background elements parallax
        gsap.to('.logo-glow', {
            scale: 1.5,
            opacity: 0,
            scrollTrigger: {
                trigger: '.hero',
                start: "top top",
                end: "bottom top",
                scrub: 1
            }
        });
        

    }
    
    setupCounterAnimations() {
        document.querySelectorAll('.stat-number').forEach(counter => {
            const target = parseInt(counter.getAttribute('data-count'));
            
            ScrollTrigger.create({
                trigger: counter,
                start: "top 80%",
                onEnter: () => {
                    gsap.fromTo(counter, 
                        { textContent: 0 },
                        {
                            textContent: target,
                            duration: 2,
                            ease: "power2.out",
                            snap: { textContent: 1 },
                            onUpdate: function() {
                                counter.textContent = Math.ceil(counter.textContent).toLocaleString();
                            }
                        }
                    );
                }
            });
        });
    }
    
    createButtonParticles(button) {
        const rect = button.getBoundingClientRect();
        const particles = [];
        
        for (let i = 0; i < 6; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: #3b82f6;
                border-radius: 50%;
                pointer-events: none;
                z-index: 9999;
                left: ${rect.left + rect.width / 2}px;
                top: ${rect.top + rect.height / 2}px;
            `;
            
            document.body.appendChild(particle);
            particles.push(particle);
            
            gsap.to(particle, {
                x: (Math.random() - 0.5) * 100,
                y: (Math.random() - 0.5) * 100,
                opacity: 0,
                scale: 0,
                duration: 0.8,
                delay: Math.random() * 0.2,
                ease: "power2.out",
                onComplete: () => {
                    document.body.removeChild(particle);
                }
            });
        }
    }
    
    // Method to trigger form submission animation
    animateFormSubmission(form, success = true) {
        const button = form.querySelector('button[type="submit"]');
        const buttonText = button.querySelector('.button-text');
        const buttonLoader = button.querySelector('.button-loader');
        
        // Show loading state
        gsap.set(buttonLoader, { display: 'block' });
        gsap.to(buttonText, { opacity: 0, duration: 0.2 });
        gsap.to(buttonLoader, { opacity: 1, duration: 0.2 });
        
        // Simulate processing time
        setTimeout(() => {
            gsap.to(buttonLoader, { 
                opacity: 0, 
                duration: 0.2,
                onComplete: () => {
                    gsap.set(buttonLoader, { display: 'none' });
                }
            });
            
            if (success) {
                buttonText.textContent = '✓ Success!';
                gsap.to(button, {
                    backgroundColor: '#10b981',
                    duration: 0.3,
                    ease: "power2.out"
                });
            } else {
                buttonText.textContent = '✗ Error';
                gsap.to(button, {
                    backgroundColor: '#ef4444',
                    duration: 0.3,
                    ease: "power2.out"
                });
            }
            
            gsap.to(buttonText, { opacity: 1, duration: 0.2 });
            
            // Reset after delay
            setTimeout(() => {
                buttonText.textContent = 'Sign Up for Early Access';
                gsap.to(button, {
                    backgroundColor: '',
                    duration: 0.3,
                    ease: "power2.out"
                });
            }, 2000);
            
        }, 1500);
    }
    
    // Method to create screen shake effect
    screenShake(intensity = 1) {
        gsap.to('body', {
            x: Math.random() * intensity * 2 - intensity,
            y: Math.random() * intensity * 2 - intensity,
            duration: 0.1,
            repeat: 5,
            yoyo: true,
            ease: "power2.inOut",
            onComplete: () => {
                gsap.set('body', { x: 0, y: 0 });
            }
        });
    }
}

// Initialize animations when DOM is loaded
let animationController;

document.addEventListener('DOMContentLoaded', () => {
    animationController = new AnimationController();
});

// Export for use in other scripts
window.animationController = animationController;
