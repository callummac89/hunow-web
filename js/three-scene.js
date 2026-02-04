// Three.js Scene Setup
class ThreeScene {
    constructor() {
        this.container = document.getElementById('three-container');
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.particles = null;
        this.stars = null;
        this.mouse = { x: 0, y: 0 };
        this.windowHalf = { x: window.innerWidth / 2, y: window.innerHeight / 2 };
        
        this.init();
        this.createParticles();
        this.createStars();
        this.addEventListeners();
        this.animate();
    }
    
    init() {
        // Scene
        this.scene = new THREE.Scene();
        
        // Camera
        this.camera = new THREE.PerspectiveCamera(
            75,
            window.innerWidth / window.innerHeight,
            1,
            3000
        );
        this.camera.position.z = 1000;
        
        // Renderer
        this.renderer = new THREE.WebGLRenderer({ 
            alpha: true, 
            antialias: true 
        });
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.container.appendChild(this.renderer.domElement);
    }
    
    createCircleTexture() {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const size = 32;
        
        canvas.width = size;
        canvas.height = size;
        
        const center = size / 2;
        const radius = size / 2;
        
        const gradient = context.createRadialGradient(center, center, 0, center, center, radius);
        gradient.addColorStop(0, 'rgba(255, 255, 255, 1)');
        gradient.addColorStop(0.2, 'rgba(255, 255, 255, 1)');
        gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
        
        context.fillStyle = gradient;
        context.fillRect(0, 0, size, size);
        
        const texture = new THREE.CanvasTexture(canvas);
        return texture;
    }

    createTriangleTexture() {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const size = 32;
        canvas.width = size;
        canvas.height = size;
        
        const centerX = size / 2;
        const centerY = size / 2;
        const radius = size / 3;
        
        // Create triangle path
        context.beginPath();
        context.moveTo(centerX, centerY - radius);
        context.lineTo(centerX - radius * Math.cos(Math.PI / 6), centerY + radius * Math.sin(Math.PI / 6));
        context.lineTo(centerX + radius * Math.cos(Math.PI / 6), centerY + radius * Math.sin(Math.PI / 6));
        context.closePath();
        
        // Create gradient for soft edges
        const gradient = context.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
        gradient.addColorStop(0, 'rgba(255, 255, 255, 1)');
        gradient.addColorStop(0.7, 'rgba(255, 255, 255, 0.8)');
        gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
        
        context.fillStyle = gradient;
        context.fill();
        
        return new THREE.CanvasTexture(canvas);
    }
    
    createParticles() {
        // Create circular particles
        this.createCircularParticles();
        // Create triangle particles
        this.createTriangleParticles();
    }

    createCircularParticles() {
        const particleCount = 800;
        const positions = new Float32Array(particleCount * 3);
        const velocities = new Float32Array(particleCount * 3);
        const colors = new Float32Array(particleCount * 3);
        
        const color1 = new THREE.Color(0xffffff); // White
        const color2 = new THREE.Color(0x8b5cf6); // Purple
        const color3 = new THREE.Color(0x1e3a8a); // Dark Blue
        const color4 = new THREE.Color(0x60a5fa); // Light Blue
        
        for (let i = 0; i < particleCount; i++) {
            const i3 = i * 3;
            
            // Positions
            positions[i3] = (Math.random() - 0.5) * 2000;
            positions[i3 + 1] = (Math.random() - 0.5) * 2000;
            positions[i3 + 2] = (Math.random() - 0.5) * 2000;
            
            // Velocities
            velocities[i3] = (Math.random() - 0.5) * 0.5;
            velocities[i3 + 1] = (Math.random() - 0.5) * 0.5;
            velocities[i3 + 2] = (Math.random() - 0.5) * 0.5;
            
            // Colors
            const colorChoice = Math.floor(Math.random() * 4);
            const selectedColor = colorChoice === 0 ? color1 : colorChoice === 1 ? color2 : colorChoice === 2 ? color3 : color4;
            
            colors[i3] = selectedColor.r;
            colors[i3 + 1] = selectedColor.g;
            colors[i3 + 2] = selectedColor.b;
        }
        
        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('velocity', new THREE.BufferAttribute(velocities, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        
        const material = new THREE.PointsMaterial({
            size: 3,
            transparent: true,
            opacity: 0.9,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            sizeAttenuation: true,
            map: this.createCircleTexture()
        });
        
        this.particles = new THREE.Points(geometry, material);
        this.scene.add(this.particles);
        
        // Store reference for animation
        this.particlePositions = positions;
        this.particleVelocities = velocities;
    }

    createTriangleParticles() {
        const triangleCount = 200;
        const positions = new Float32Array(triangleCount * 3);
        const velocities = new Float32Array(triangleCount * 3);
        const colors = new Float32Array(triangleCount * 3);
        
        const whiteColor = new THREE.Color(0xffffff); // White triangles
        
        for (let i = 0; i < triangleCount; i++) {
            const i3 = i * 3;
            
            // Positions
            positions[i3] = (Math.random() - 0.5) * 2000;
            positions[i3 + 1] = (Math.random() - 0.5) * 2000;
            positions[i3 + 2] = (Math.random() - 0.5) * 2000;
            
            // Velocities (slightly slower than circles)
            velocities[i3] = (Math.random() - 0.5) * 0.3;
            velocities[i3 + 1] = (Math.random() - 0.5) * 0.3;
            velocities[i3 + 2] = (Math.random() - 0.5) * 0.3;
            
            // All white for triangles
            colors[i3] = whiteColor.r;
            colors[i3 + 1] = whiteColor.g;
            colors[i3 + 2] = whiteColor.b;
        }
        
        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('velocity', new THREE.BufferAttribute(velocities, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        
        const material = new THREE.PointsMaterial({
            size: 4,
            transparent: true,
            opacity: 0.8,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            sizeAttenuation: true,
            map: this.createTriangleTexture()
        });
        
        this.triangleParticles = new THREE.Points(geometry, material);
        this.scene.add(this.triangleParticles);
        
        // Store reference for animation
        this.trianglePositions = positions;
        this.triangleVelocities = velocities;
    }
    
    createStars() {
        const starCount = 500;
        const positions = new Float32Array(starCount * 3);
        
        for (let i = 0; i < starCount; i++) {
            const i3 = i * 3;
            positions[i3] = (Math.random() - 0.5) * 4000;
            positions[i3 + 1] = (Math.random() - 0.5) * 4000;
            positions[i3 + 2] = (Math.random() - 0.5) * 4000;
        }
        
        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        
        const material = new THREE.PointsMaterial({
            size: 1,
            transparent: true,
            opacity: 0.6,
            color: 0xffffff,
            blending: THREE.AdditiveBlending
        });
        
        this.stars = new THREE.Points(geometry, material);
        this.scene.add(this.stars);
    }
    
    addEventListeners() {
        window.addEventListener('resize', () => this.onWindowResize(), false);
        document.addEventListener('mousemove', (event) => this.onMouseMove(event), false);
        
        // Touch support
        document.addEventListener('touchmove', (event) => {
            if (event.touches.length > 0) {
                this.onMouseMove({
                    clientX: event.touches[0].clientX,
                    clientY: event.touches[0].clientY
                });
            }
        }, false);
    }
    
    onWindowResize() {
        this.windowHalf.x = window.innerWidth / 2;
        this.windowHalf.y = window.innerHeight / 2;
        
        this.camera.aspect = window.innerWidth / window.innerHeight;
        this.camera.updateProjectionMatrix();
        
        this.renderer.setSize(window.innerWidth, window.innerHeight);
    }
    
    onMouseMove(event) {
        this.mouse.x = (event.clientX - this.windowHalf.x) * 0.0005;
        this.mouse.y = (event.clientY - this.windowHalf.y) * 0.0005;
    }
    
    animate() {
        requestAnimationFrame(() => this.animate());
        this.render();
    }
    
    render() {
        const time = Date.now() * 0.00005;
        
        // Animate particles
        if (this.particles) {
            const positions = this.particles.geometry.attributes.position.array;
            const velocities = this.particleVelocities;
            
            for (let i = 0; i < positions.length; i += 3) {
                // Update positions based on velocities
                positions[i] += velocities[i];
                positions[i + 1] += velocities[i + 1];
                positions[i + 2] += velocities[i + 2];
                
                // Boundary checking and wrapping
                if (positions[i] > 1000) positions[i] = -1000;
                if (positions[i] < -1000) positions[i] = 1000;
                if (positions[i + 1] > 1000) positions[i + 1] = -1000;
                if (positions[i + 1] < -1000) positions[i + 1] = 1000;
                if (positions[i + 2] > 1000) positions[i + 2] = -1000;
                if (positions[i + 2] < -1000) positions[i + 2] = 1000;
            }
            
            this.particles.geometry.attributes.position.needsUpdate = true;
            this.particles.rotation.y += 0.0005;
        }
        
        // Animate triangle particles
        if (this.triangleParticles) {
            const positions = this.triangleParticles.geometry.attributes.position.array;
            const velocities = this.triangleVelocities;
            
            for (let i = 0; i < positions.length; i += 3) {
                // Update positions based on velocities
                positions[i] += velocities[i];
                positions[i + 1] += velocities[i + 1];
                positions[i + 2] += velocities[i + 2];
                
                // Boundary checking and wrapping
                if (positions[i] > 1000) positions[i] = -1000;
                if (positions[i] < -1000) positions[i] = 1000;
                if (positions[i + 1] > 1000) positions[i + 1] = -1000;
                if (positions[i + 1] < -1000) positions[i + 1] = 1000;
                if (positions[i + 2] > 1000) positions[i + 2] = -1000;
                if (positions[i + 2] < -1000) positions[i + 2] = 1000;
            }
            
            this.triangleParticles.geometry.attributes.position.needsUpdate = true;
            this.triangleParticles.rotation.y -= 0.0003; // Rotate opposite direction
        }
        
        // Animate stars
        if (this.stars) {
            this.stars.rotation.x += 0.0001;
            this.stars.rotation.y += 0.0002;
        }
        
        // Camera movement based on mouse
        this.camera.position.x += (this.mouse.x * 100 - this.camera.position.x) * 0.05;
        this.camera.position.y += (-this.mouse.y * 100 - this.camera.position.y) * 0.05;
        this.camera.lookAt(this.scene.position);
        
        // Subtle camera sway
        this.camera.position.x += Math.sin(time * 0.5) * 10;
        this.camera.position.y += Math.cos(time * 0.3) * 5;
        
        this.renderer.render(this.scene, this.camera);
    }
    
    // Method to update particle intensity based on scroll or interactions
    updateParticleIntensity(intensity = 1) {
        if (this.particles && this.particles.material) {
            this.particles.material.opacity = Math.max(0.3, Math.min(1, intensity));
        }
    }
    
    // Method to add particle burst effect
    createParticleBurst(x, y) {
        const burstCount = 50;
        const positions = new Float32Array(burstCount * 3);
        const velocities = new Float32Array(burstCount * 3);
        const colors = new Float32Array(burstCount * 3);
        
        const color = new THREE.Color(0x3b82f6);
        
        for (let i = 0; i < burstCount; i++) {
            const i3 = i * 3;
            
            positions[i3] = x + (Math.random() - 0.5) * 50;
            positions[i3 + 1] = y + (Math.random() - 0.5) * 50;
            positions[i3 + 2] = (Math.random() - 0.5) * 100;
            
            velocities[i3] = (Math.random() - 0.5) * 5;
            velocities[i3 + 1] = (Math.random() - 0.5) * 5;
            velocities[i3 + 2] = (Math.random() - 0.5) * 5;
            
            colors[i3] = color.r;
            colors[i3 + 1] = color.g;
            colors[i3 + 2] = color.b;
        }
        
        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        
        const material = new THREE.PointsMaterial({
            size: 4,
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending
        });
        
        const burst = new THREE.Points(geometry, material);
        this.scene.add(burst);
        
        // Animate burst and remove after animation
        let opacity = 1;
        const animateBurst = () => {
            opacity -= 0.02;
            burst.material.opacity = opacity;
            
            if (opacity > 0) {
                requestAnimationFrame(animateBurst);
            } else {
                this.scene.remove(burst);
                geometry.dispose();
                material.dispose();
            }
        };
        
        animateBurst();
    }
}

// Initialize Three.js scene when DOM is loaded
let threeScene;

document.addEventListener('DOMContentLoaded', () => {
    // Wait for loading screen to finish
    setTimeout(() => {
        if (window.innerWidth > 768) { // Only on desktop for performance
            threeScene = new ThreeScene();
        }
    }, 1000);
});

// Export for use in other scripts
window.threeScene = threeScene;
